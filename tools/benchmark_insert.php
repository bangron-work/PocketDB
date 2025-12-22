<?php

require __DIR__ . '/../vendor/autoload.php';

use PocketDB\Client;

$client = new Client(':memory:');
$db = $client->selectDB('bench');
$coll = $db->selectCollection('items');

// generate dataset
$docs = [];
for ($i = 0; $i < 1000; $i++) {
    $docs[] = ['name' => 'Item '.$i, 'value' => rand(1, 100000), 'tags' => ['t'.($i%10)]];
}

$start = microtime(true);
$count = $coll->insertMany($docs);
$elapsed = microtime(true) - $start;

echo "Inserted: {$count} documents in {".round($elapsed,4)."}s\n";

// simple throughput
echo "Throughput: ".round($count / max($elapsed, 0.000001), 2)." ops/s\n";

