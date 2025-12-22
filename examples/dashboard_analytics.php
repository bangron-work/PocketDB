<?php

require __DIR__.'/../vendor/autoload.php';
use PocketDB\Client;

$client = new Client(__DIR__.'/data');

// 1. Hitung Total Penjualan Sukses (Status: PAID)
$orders = $client->ecommerce->orders->find(['status' => 'PAID'])->toArray();
$totalSales = array_sum(array_column(array_column($orders, 'invoice_details'), 'grand_total'));

// 2. Hitung Total Pembatalan (Status: CANCELLED)
$cancelled = $client->ecommerce->orders->find(['status' => 'CANCELLED'])->toArray();
$totalRefund = array_sum(array_column(array_column($cancelled, 'invoice_details'), 'grand_total'));

// 3. Hitung Total Pajak yang Terkumpul (Dari tax.sqlite)
$taxes = $client->tax->vat_reports->find()->toArray();
$totalTax = array_sum(array_column($taxes, 'amount'));

// 4. Hitung Sisa Stok Global (Summary)
$products = $client->ecommerce->products->find()->toArray();

echo "==========================================\n";
echo "      ENTERPRISE ANALYTICS DASHBOARD      \n";
echo "==========================================\n";
echo "Ringkasan Keuangan:\n";
echo '  - Total Penjualan Aktif : Rp '.number_format($totalSales)."\n";
echo '  - Total Dana Refund     : Rp '.number_format($totalRefund)."\n";
echo '  - Kewajiban Pajak (PPN) : Rp '.number_format($totalTax)."\n";
echo "------------------------------------------\n";
echo "Inventori Produk:\n";
foreach ($products as $p) {
    echo '  - '.str_pad($p['name'], 20).': '.$p['stock']." unit\n";
}
echo "------------------------------------------\n";
echo "Statistik Transaksi:\n";
echo '  - Berhasil  : '.count($orders)."\n";
echo '  - Dibatalkan: '.count($cancelled)."\n";
echo "==========================================\n";
