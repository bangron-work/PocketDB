<?php

require __DIR__.'/../vendor/autoload.php';
use PocketDB\Client;

// 1. Inisialisasi
@array_map('unlink', glob(__DIR__.'/data/*.sqlite'));
$client = new Client(__DIR__.'/data');

$ecom = $client->ecommerce;
$base = $client->base;
$acc = $client->accounting;

// --- STEP 2: REGISTER SEMUA HOOK ---

// Hook saat BELI (Mengurangi stok & saldo)
$ecom->orders->on('beforeInsert', function (&$order) use ($ecom, $base) {
    $product = $ecom->products->findOne(['_id' => $order['product_id']]);
    $user = $base->users->findOne(['_id' => $order['customer_id']]);

    $total = ($product['price'] * $order['qty']) + 50000; // + biaya admin

    // Update data terkait
    $product['stock'] -= $order['qty'];
    $ecom->products->save($product);

    $user['wallet']['balance'] -= $total;
    $base->users->save($user);

    $order['grand_total'] = $total;
    $order['status'] = 'PAID';

    return $order;
});

// Hook saat CANCEL (Mengembalikan stok & saldo)
$ecom->orders->on('afterUpdate', function ($old, $new) use ($ecom, $base, $acc) {
    if ($old['status'] === 'PAID' && $new['status'] === 'CANCELLED') {
        echo "\n[HOOK] Refund terdeteksi! Mengembalikan dana...\n";

        // Kembalikan Stok
        $product = $ecom->products->findOne(['_id' => $new['product_id']]);
        $product['stock'] += $new['qty'];
        $ecom->products->save($product);

        // Kembalikan Saldo
        $user = $base->users->findOne(['_id' => $new['customer_id']]);
        $user['wallet']['balance'] += $new['grand_total'];
        $base->users->save($user);

        // Catat Jurnal
        $acc->ledger->insert(['type' => 'REFUND', 'amount' => $new['grand_total']]);
    }
});

// --- STEP 3: JALANKAN SKENARIO ---

// 1. Setup Data Awal
$base->users->insert(['_id' => 'user_rony', 'name' => 'Rony', 'wallet' => ['balance' => 100000000]]);
$ecom->products->insert(['_id' => 'macbook', 'name' => 'MacBook', 'price' => 50000000, 'stock' => 5]);

echo "--- 1. MELAKUKAN PEMBELIAN ---\n";
$ecom->orders->insert(['_id' => 'INV-001', 'product_id' => 'macbook', 'customer_id' => 'user_rony', 'qty' => 1]);

$u = $base->users->findOne(['_id' => 'user_rony']);
echo 'Saldo setelah beli: Rp '.number_format($u['wallet']['balance'])."\n";

echo "\n--- 2. MELAKUKAN PEMBATALAN (REFUND) ---\n";
// Update trigger hook afterUpdate
$ecom->orders->update(['_id' => 'INV-001'], ['status' => 'CANCELLED']);

// --- STEP 4: HASIL AKHIR ---
echo "\n--- 3. VERIFIKASI AKHIR ---";
$uFinal = $base->users->findOne(['_id' => 'user_rony']);
$pFinal = $ecom->products->findOne(['_id' => 'macbook']);
$lFinal = $acc->ledger->findOne(['type' => 'REFUND']);

echo "\nSaldo Rony Akhir : Rp ".number_format($uFinal['wallet']['balance']);
echo "\nStok MacBook Akhir: ".$pFinal['stock'];
echo "\nStatus Akuntansi : ".($lFinal ? 'BERHASIL REFUND' : 'GAGAL');
echo "\n";
