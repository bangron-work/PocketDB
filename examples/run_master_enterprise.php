<?php

require __DIR__.'/../vendor/autoload.php';
use PocketDB\Client;

// 1. CLEANUP & INITIALIZATION
@array_map('unlink', glob(__DIR__.'/data/*.sqlite'));
$client = new Client(__DIR__.'/data');

$ecom = $client->ecommerce;
$base = $client->base;
$acc = $client->accounting;
$tax = $client->tax;
$log = $client->log;

// --- 2. REGISTER SEMUA LOGIKA BISNIS (HOOKS) ---

// HOOK A: Validasi Berlapis & Snapshotting (Veto Logic)
$ecom->orders->on('beforeInsert', function (&$order) use ($ecom, $base) {
    $product = $ecom->products->findOne(['_id' => $order['product_id']]);
    $user = $base->users->findOne(['_id' => $order['customer_id']]);

    // Validasi Stok
    if (!$product || $product['stock'] < $order['qty']) {
        echo '❌ [VETO] Stok tidak cukup untuk '.($product['name'] ?? 'Produk Unknown')."\n";

        return false;
    }

    // Kalkulasi Finansial (PPN 11% + Admin)
    $subtotal = $product['price'] * $order['qty'];
    $ppn = $subtotal * 0.11;
    $grandTotal = $subtotal + $ppn + 50000;

    // Validasi Saldo
    if ($user['wallet']['balance'] < $grandTotal) {
        echo "❌ [VETO] Saldo {$user['name']} tidak cukup. Butuh Rp ".number_format($grandTotal)."\n";

        return false;
    }

    // EKSEKUSI POTONG (Atomic-like)
    $product['stock'] -= $order['qty'];
    $ecom->products->save($product);

    $user['wallet']['balance'] -= $grandTotal;
    $base->users->save($user);

    // Snapshot data untuk Invoice
    $order['billing'] = ['subtotal' => $subtotal, 'tax' => $ppn, 'total' => $grandTotal];
    $order['item_specs'] = $product['specs'] ?? [];
    $order['status'] = 'PAID';
    $order['created_at'] = date('Y-m-d H:i:s');

    return $order;
});

// HOOK B: Otomasi Cross-DB (Accounting & Tax)
$ecom->orders->on('afterInsert', function ($order) use ($acc, $tax) {
    $acc->ledger->insert(['ref' => $order['_id'], 'type' => 'INCOME', 'amount' => $order['billing']['subtotal']]);
    $tax->vat_reports->insert(['ref' => $order['_id'], 'amount' => $order['billing']['tax']]);
});

// HOOK C: Lifecycle Refund (afterUpdate)
$ecom->orders->on('afterUpdate', function ($old, $new) use ($ecom, $base, $acc) {
    if ($old['status'] === 'PAID' && $new['status'] === 'CANCELLED') {
        echo "🔄 [LIFECYCLE] Refund terdeteksi! Mengembalikan data...\n";

        $product = $ecom->products->findOne(['_id' => $new['product_id']]);
        $product['stock'] += $new['qty'];
        $ecom->products->save($product);

        $user = $base->users->findOne(['_id' => $new['customer_id']]);
        $user['wallet']['balance'] += $new['billing']['total'];
        $base->users->save($user);

        $acc->ledger->insert(['ref' => $new['_id'], 'type' => 'REVERSAL', 'amount' => $new['billing']['total']]);
    }
});

// --- 3. EKSEKUSI SKENARIO ---

// Setup Master Data
$base->users->insert(['_id' => 'u1', 'name' => 'Rony', 'wallet' => ['balance' => 100000000]]);
$ecom->products->insert(['_id' => 'p1', 'name' => 'MacBook M3', 'price' => 40000000, 'stock' => 2, 'specs' => ['ram' => '32GB']]);

echo "--- STARTING ENTERPRISE SIMULATION ---\n\n";

// Transaksi 1: BERHASIL
echo "🛒 Transaksi 1: Membeli 1 MacBook...\n";
$ecom->orders->insert(['_id' => 'INV-001', 'product_id' => 'p1', 'customer_id' => 'u1', 'qty' => 1]);

// Transaksi 2: GAGAL (Veto - Stok Habis)
echo "🛒 Transaksi 2: Mencoba membeli 5 MacBook (Stok sisa 1)...\n";
$ecom->orders->insert(['_id' => 'INV-002', 'product_id' => 'p1', 'customer_id' => 'u1', 'qty' => 5]);

// Transaksi 3: REFUND (Lifecycle)
echo "🛒 Transaksi 3: Membatalkan INV-001...\n";
$ecom->orders->update(['_id' => 'INV-001'], ['status' => 'CANCELLED', 'reason' => 'User mis-click']);

// --- 4. DASHBOARD ANALYTICS ---

echo "\n==========================================\n";
echo "      FINAL ENTERPRISE REPORT DASHBOARD    \n";
echo "==========================================\n";

$orders = $ecom->orders->find()->toArray();
foreach ($orders as $o) {
    echo sprintf("[%s] ID: %s | Total: Rp %s | Status: %s\n",
        $o['created_at'], $o['_id'], number_format($o['billing']['total']), $o['status']);
}

echo "------------------------------------------\n";
$u = $base->users->findOne(['_id' => 'u1']);
$p = $ecom->products->findOne(['_id' => 'p1']);
echo 'SALDO AKHIR USER : Rp '.number_format($u['wallet']['balance'])."\n";
echo 'STOK AKHIR PRODUK: '.$p['stock']." Unit\n";
echo 'TOTAL JURNAL ACC : '.$acc->ledger->count()." Entri\n";
echo "==========================================\n";
