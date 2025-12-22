<?php

require __DIR__.'/../vendor/autoload.php';
use PocketDB\Client;

$client = new Client(__DIR__.'/data');

$base = $client->base;
$ecom = $client->ecommerce;
$acc = $client->accounting;

// --- REGISTER HOOK (PENTING: Harus didaftarkan SEBELUM memanggil update) ---
$ecom->orders->on('afterUpdate', function ($old, $new) use ($ecom, $base, $acc) {
    // Debug: Pastikan hook terpanggil
    // echo "DEBUG: Status lama: {$old['status']}, Status baru: {$new['status']}\n";

    if ($old['status'] !== 'CANCELLED' && $new['status'] === 'CANCELLED') {
        echo "\n[SYSTEM] PROSES REFUND UNTUK: ".$new['_id']."\n";

        // 1. Kembalikan Stok
        $product = $ecom->products->findOne(['_id' => $new['product_id']]);
        if ($product) {
            $product['stock'] += $new['qty'];
            $ecom->products->save($product);
            echo " ✔️ Stok dikembalikan.\n";
        }

        // 2. Kembalikan Saldo (Refund Full)
        $user = $base->users->findOne(['_id' => $new['customer_id']]);
        if ($user) {
            $refundAmount = $new['invoice_details']['grand_total'];
            $user['wallet']['balance'] += $refundAmount;
            $base->users->save($user);
            echo ' ✔️ Saldo di-refund: Rp '.number_format($refundAmount)."\n";
        }

        // 3. Jurnal Balik
        $acc->ledger->insert([
            'type' => 'REVERSAL',
            'order_ref' => $new['_id'],
            'debit' => $new['invoice_details']['pricing']['base'],
            'desc' => 'Refund INV-001',
        ]);
        echo " ✔️ Jurnal pembalik dicatat.\n";
    }
});

echo "=== MEMULAI PROSES PEMBATALAN PESANAN ===\n";

// Pastikan data yang akan di-update ada
$orderExist = $ecom->orders->findOne(['_id' => 'INV-ENTERPRISE-001']);

if ($orderExist) {
    // Lakukan Update
    $ecom->orders->update(
        ['_id' => 'INV-ENTERPRISE-001'],
        ['status' => 'CANCELLED'],
        true // Merge mode
    );
} else {
    echo "ERR: Data INV-ENTERPRISE-001 tidak ditemukan! Jalankan script transaksi dulu.\n";
}

echo "\n=== VERIFIKASI DATA PASCA-REFUND ===\n";
$finalUser = $base->users->findOne(['_id' => 'user_rony']);
echo 'Saldo Akhir Rony : Rp '.number_format($finalUser['wallet']['balance'] ?? 0)."\n";

$finalProd = $ecom->products->findOne(['_id' => 'macbook_m3']);
echo 'Stok Akhir Produk: '.($finalProd['stock'] ?? 0)." unit\n";

$reversal = $acc->ledger->findOne(['type' => 'REVERSAL']);
echo 'Status Akuntansi : '.($reversal ? 'TERCATAT' : 'GAGAL')."\n";
