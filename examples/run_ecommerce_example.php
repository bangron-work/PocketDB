<?php

require __DIR__.'/../vendor/autoload.php';
use PocketDB\Client;

@array_map('unlink', glob(__DIR__.'/data/*.sqlite'));
$client = new Client(__DIR__.'/data');

// Inisialisasi 4 Database
$ecom = $client->ecommerce;
$base = $client->base;
$account = $client->accounting;
$taxDB = $client->tax;

// --- MASTER DATA ---
$base->users->insert(['_id' => 'user_rony', 'name' => 'Rony Herdian', 'balance' => 100000000]);
$ecom->products->insert(['_id' => 'iphone_15', 'name' => 'iPhone 15', 'price' => 15000000, 'stock' => 10]);

// --- ADVANCED LOGIC HOOK ---
$ecom->orders->on('beforeInsert', function (&$order) use ($ecom, $base) {
    $product = $ecom->products->findOne(['_id' => $order['product_id']]);
    $user = $base->users->findOne(['_id' => $order['customer_id']]);

    // 1. Perhitungan Diskon
    $subtotal = $product['price'] * $order['qty'];
    $discount = ($order['promo_code'] === 'HEMAT') ? ($subtotal * 0.1) : 0; // Diskon 10%
    $afterDiscount = $subtotal - $discount;

    // 2. Perhitungan Pajak (PPN 11%)
    $taxAmount = $afterDiscount * 0.11;
    $grandTotal = $afterDiscount + $taxAmount;

    // 3. Cek Saldo
    if ($user['balance'] < $grandTotal) {
        return false;
    }

    // 4. Eksekusi Perubahan
    $product['stock'] -= $order['qty'];
    $ecom->products->save($product);

    $user['balance'] -= $grandTotal;
    $base->users->save($user);

    // 5. Simpan Data ke Record Order
    $order['details'] = [
        'base_price' => $subtotal,
        'discount' => $discount,
        'tax' => $taxAmount,
        'grand_total' => $grandTotal,
    ];
    $order['timestamp'] = date('Y-m-d H:i:s');

    return $order;
});

// Hook After Insert: Sebar data ke Accounting & Tax
$ecom->orders->on('afterInsert', function ($order) use ($account, $taxDB) {
    // Masuk ke Kas Akuntansi (Neto)
    $account->ledger->insert([
        'ref' => $order['_id'],
        'income' => $order['details']['grand_total'] - $order['details']['tax'],
        'desc' => 'Penjualan Net',
    ]);

    // Masuk ke Kas Pajak (PPN)
    $taxDB->vat_collection->insert([
        'order_id' => $order['_id'],
        'tax_collected' => $order['details']['tax'],
        'type' => 'PPN_11_PERSEN',
    ]);
});

// --- EKSEKUSI ---
echo "--- TRANSAKSI DENGAN PROMO 'HEMAT' ---\n";
$ecom->orders->insert([
    '_id' => 'INV-TX-99',
    'product_id' => 'iphone_15',
    'customer_id' => 'user_rony',
    'qty' => 2,
    'promo_code' => 'HEMAT',
]);

// --- TAMPILKAN HASIL ---
$finalOrder = $ecom->orders->findOne(['_id' => 'INV-TX-99']);
echo "Item: iPhone 15 (x2)\n";
echo 'Subtotal : Rp'.number_format($finalOrder['details']['base_price'])."\n";
echo 'Diskon   : Rp'.number_format($finalOrder['details']['discount'])." (KODE: HEMAT)\n";
echo 'PPN (11%): Rp'.number_format($finalOrder['details']['tax'])."\n";
echo 'TOTAL    : Rp'.number_format($finalOrder['details']['grand_total'])."\n";

echo "\n--- DISTRIBUSI DANA ---\n";
echo 'Kas Perusahaan (accounting.sqlite): Rp'.number_format($account->ledger->findOne()['income'])."\n";
echo 'Kas Negara (tax.sqlite)           : Rp'.number_format($taxDB->vat_collection->findOne()['tax_collected'])."\n";
