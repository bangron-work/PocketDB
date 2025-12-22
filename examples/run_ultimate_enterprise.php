<?php

require __DIR__.'/../vendor/autoload.php';
use PocketDB\Client;

// 1. Inisialisasi & Bersihkan Data Lama
@array_map('unlink', glob(__DIR__.'/data/*.sqlite'));
$client = new Client(__DIR__.'/data');

// Shortcut Database
$base = $client->base;        // User, Role, Permission
$ecom = $client->ecommerce;   // Product, Order, Category
$acc = $client->accounting;  // Ledger/Keuangan
$tax = $client->tax;         // Perpajakan
$ship = $client->shipping;    // Logistik

// --- STEP 1: SETUP DATA MASTER BERJENJANG (NESTED) ---

// Insert Permission
$permId = $base->permissions->insert([
    'name' => 'premium_checkout',
    'scope' => 'global',
    'desc' => 'Akses pembelian produk High-End',
]);

// Insert Role (Mengacu ke Permission)
$roleId = $base->roles->insert([
    'name' => 'Gold Member',
    'badge' => '🥇',
    'permissions' => [$permId],
]);

// Insert User (Dengan struktur Wallet Nested)
$userId = $base->users->insert([
    '_id' => 'user_rony',
    'name' => 'Rony Herdian',
    'role_id' => $roleId,
    'wallet' => [
        'balance' => 100000000,
        'currency' => 'IDR',
        'last_topup' => date('Y-m-d'),
    ],
]);

// Insert Product (Dengan Specs yang sangat Nested)
$productId = $ecom->products->insert([
    '_id' => 'macbook_m3',
    'name' => 'MacBook Pro M3 Max',
    'price' => 50000000,
    'stock' => 5,
    'details' => [
        'manufacturer' => 'Apple Inc',
        'specs' => [
            'processor' => 'M3 Max 16-Core',
            'memory' => ['size' => '64GB', 'type' => 'Unified'],
            'storage' => ['size' => '1TB', 'type' => 'SSD NVMe'],
        ],
    ],
]);

// --- STEP 2: LOGIKA BUSINESS HOOK (ENTERPRISE LEVEL) ---

$ecom->orders->on('beforeInsert', function (&$order) use ($ecom, $base) {
    $product = $ecom->products->findOne(['_id' => $order['product_id']]);
    $user = $base->users->findOne(['_id' => $order['customer_id']]);

    // 1. Kalkulasi Pajak & Biaya
    $subtotal = $product['price'] * $order['qty'];
    $taxRate = 0.11; // PPN 11%
    $taxAmount = $subtotal * $taxRate;
    $serviceFee = 50000;
    $grandTotal = $subtotal + $taxAmount + $serviceFee;

    // 2. Validasi Saldo (Akses data Nested wallet.balance)
    if ($user['wallet']['balance'] < $grandTotal) {
        echo '!!! GAGAL: Saldo tidak cukup (Kurang Rp '.number_format($grandTotal - $user['wallet']['balance']).")\n";

        return false;
    }

    // 3. Eksekusi Atomic (Update Stock & Wallet)
    $product['stock'] -= $order['qty'];
    $ecom->products->save($product);

    $user['wallet']['balance'] -= $grandTotal;
    $base->users->save($user);

    // 4. Bangun Struktur Data Order yang Kaya (Snapshotting)
    $order['invoice_details'] = [
        'pricing' => [
            'base' => $subtotal,
            'tax' => $taxAmount,
            'service' => $serviceFee,
        ],
        'grand_total' => $grandTotal,
    ];
    $order['item_snapshot'] = $product['details']['specs']; // Simpan spek saat dibeli
    $order['processed_at'] = date('Y-m-d H:i:s');

    return $order;
});

// Hook After Insert: Sinkronisasi ke Database Eksternal
$ecom->orders->on('afterInsert', function ($order) use ($acc, $tax, $ship) {
    // Catat Pendapatan Bersih
    $acc->ledger->insert([
        'order_ref' => $order['_id'],
        'credit' => $order['invoice_details']['pricing']['base'],
        'desc' => 'Penjualan '.$order['product_id'],
    ]);

    // Catat Kewajiban Pajak ke Kas Negara
    $tax->vat_reports->insert([
        'invoice' => $order['_id'],
        'amount' => $order['invoice_details']['pricing']['tax'],
        'status' => 'UNPAID_TO_GOV',
    ]);

    // Inisialisasi Logistik
    $ship->tracking->insert([
        'order_id' => $order['_id'],
        'status' => 'PREPARING',
        'history' => [['time' => $order['processed_at'], 'note' => 'Pesanan diterima sistem']],
    ]);
});

// --- STEP 3: JALANKAN TRANSAKSI ---

echo ">> Memproses Pesanan High-End...\n";
$ecom->orders->insert([
    '_id' => 'INV-ENTERPRISE-001',
    'product_id' => 'macbook_m3',
    'customer_id' => 'user_rony',
    'qty' => 1,
]);

// --- STEP 4: DATA AGGREGATION & REPORTING ---

$orders = $ecom->orders->find()->toArray();

// Populate Level 1: Order -> User
$data = $ecom->orders->populate($orders, 'customer_id', 'base.users', '_id', 'user_info');

// Manual Deep Mapping: User -> Role -> Permission
foreach ($data as &$item) {
    if (isset($item['user_info']['role_id'])) {
        // Ambil Role
        $role = $base->roles->findOne(['_id' => $item['user_info']['role_id']]);
        $item['user_info']['role_detail'] = $role;

        // Ambil Permission pertama dari list role
        if (!empty($role['permissions'])) {
            $item['user_info']['perm_detail'] = $base->permissions->findOne(['_id' => $role['permissions'][0]]);
        }
    }
}

// --- STEP 5: OUTPUT LAPORAN DETAIL ---

foreach ($data as $o) {
    echo "\n==========================================================\n";
    echo "                ENTERPRISE SALES INVOICE                  \n";
    echo "==========================================================\n";
    echo 'No. Invoice : '.$o['_id']."\n";
    echo 'Tanggal     : '.$o['processed_at']."\n";
    echo "----------------------------------------------------------\n";
    echo "PELANGGAN DETAIL:\n";
    echo '  Nama      : '.$o['user_info']['name']."\n";
    echo '  Membership: '.$o['user_info']['role_detail']['badge'].' '.$o['user_info']['role_detail']['name']."\n";
    echo '  Hak Akses : '.$o['user_info']['perm_detail']['name']."\n";
    echo "----------------------------------------------------------\n";
    echo "ITEM DETAIL (SNAPSHOT):\n";
    echo '  Produk    : '.$o['product_id']."\n";
    echo '  Processor : '.$o['item_snapshot']['processor']."\n";
    echo '  Memory    : '.$o['item_snapshot']['memory']['size'].' '.$o['item_snapshot']['memory']['type']."\n";
    echo '  Storage   : '.$o['item_snapshot']['storage']['size'].' '.$o['item_snapshot']['storage']['type']."\n";
    echo "----------------------------------------------------------\n";
    echo "RINCIAN BIAYA:\n";
    echo '  Harga Barang : Rp '.number_format($o['invoice_details']['pricing']['base'])."\n";
    echo '  PPN (11%)    : Rp '.number_format($o['invoice_details']['pricing']['tax'])."\n";
    echo '  Biaya Admin  : Rp '.number_format($o['invoice_details']['pricing']['service'])."\n";
    echo '  GRAND TOTAL  : Rp '.number_format($o['invoice_details']['grand_total'])."\n";
    echo "----------------------------------------------------------\n";
    echo "STATUS LOGISTIK:\n";
    $tracking = $ship->tracking->findOne(['order_id' => $o['_id']]);
    echo '  Current Status: '.$tracking['status']."\n";
    echo '  Last Update   : '.end($tracking['history'])['note']."\n";
    echo "==========================================================\n";
}

echo "\nRINGKASAN SALDO AKHIR:\n";
$finalUser = $base->users->findOne(['_id' => 'user_rony']);
echo '- Sisa Saldo Rony: Rp '.number_format($finalUser['wallet']['balance'])."\n";
echo '- Kas Perusahaan : Rp '.number_format($acc->ledger->findOne(['order_ref' => 'INV-ENTERPRISE-001'])['credit'])."\n";
echo '- Kas Pajak (PPN): Rp '.number_format($tax->vat_reports->findOne()['amount'])."\n";
