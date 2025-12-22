<?php
/**
 * Simple benchmark comparing insert/read performance for encrypted vs plain collections.
 * Run: php tools/benchmark_encryption.php [count].
 */
$count = isset($argv[1]) ? (int) $argv[1] : 2000;
require __DIR__.'/../vendor/autoload.php';
use PocketDB\Database;

function nowMillis()
{
    return microtime(true) * 1000;
}

$db = new Database(':memory:');
$plain = $db->selectCollection('plain');
$enc = $db->selectCollection('enc');
$enc->setEncryptionKey('bench-key');

// prepare small payload
$payload = [];
for ($i = 0; $i < 10; ++$i) {
    $payload['k'.$i] = str_repeat((string) $i, 20);
}

echo "Running benchmark with {$count} inserts per collection...\n";

// Plain inserts
$start = nowMillis();
for ($i = 0; $i < $count; ++$i) {
    $plain->insert(['_id' => 'p'.$i, 'email' => "user{$i}@example.com"] + $payload);
}
$plainInsertMs = nowMillis() - $start;

// Encrypted inserts
$start = nowMillis();
for ($i = 0; $i < $count; ++$i) {
    $enc->insert(['_id' => 'e'.$i, 'email' => "user{$i}@example.com"] + $payload);
}
$encInsertMs = nowMillis() - $start;

// Reads: random read 1000 times
$reads = min(1000, $count);
$start = nowMillis();
for ($i = 0; $i < $reads; ++$i) {
    $idx = rand(0, $count - 1);
    $plain->findOne(['_id' => 'p'.$idx]);
}
$plainReadMs = nowMillis() - $start;

$start = nowMillis();
for ($i = 0; $i < $reads; ++$i) {
    $idx = rand(0, $count - 1);
    $enc->findOne(['_id' => 'e'.$idx]);
}
$encReadMs = nowMillis() - $start;

echo "Results (ms):\n";
echo "Plain inserts: {$plainInsertMs}\n";
echo "Enc inserts:   {$encInsertMs}\n";
echo "Plain reads:   {$plainReadMs}\n";
echo "Enc reads:     {$encReadMs}\n";

echo 'Insert ratio (enc/plain): '.round($encInsertMs / max(1, $plainInsertMs), 2)."\n";
echo 'Read ratio (enc/plain): '.round($encReadMs / max(1, $plainReadMs), 2)."\n";
