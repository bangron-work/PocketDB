<?php

require_once __DIR__.'/../vendor/autoload.php';

use PocketDB\Client;
use PocketDB\Database;

function t()
{
    return microtime(true);
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision).' '.$units[$pow];
}

echo "PocketDB Focused Benchmark\n";
echo "==========================\n\n";

$config = [
    'document_count' => isset($argv[1]) ? (int) $argv[1] : 1000,
    'storage_mode' => isset($argv[2]) ? $argv[2] : 'disk',
];

echo "Benchmark Configuration:\n";
echo "- Document count: {$config['document_count']}\n";
echo "- Storage mode: {$config['storage_mode']}\n\n";

$baseDir = __DIR__.'/../test_databases/focused_bench';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

$client = new Client($config['storage_mode'] === 'memory' ? ':memory:' : $baseDir);
$results = [];

echo "Starting Focused Benchmark...\n\n";

// Test 1: Basic CRUD Operations
echo "=== Test 1: Basic CRUD Operations ===\n";
$testStart = t();

$db = $client->selectDB('test_crud');
$users = $db->selectCollection('users');

// Insert users
$insertStart = t();
for ($i = 0; $i < $config['document_count']; ++$i) {
    $users->insert([
        'id' => $i,
        'name' => 'User_'.$i,
        'email' => "user{$i}@example.com",
        'age' => rand(18, 80),
        'city' => ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Makassar'][rand(0, 4)],
        'created_at' => date('Y-m-d H:i:s', strtotime('-'.rand(1, 365).' days')),
    ]);
}
$insertTime = t() - $insertStart;

// Read operations
$readStart = t();
$youngUsers = $users->find(['age' => ['$lt' => 30]])->toArray();
$readTime = t() - $readStart;

// Update operations
$updateStart = t();
$updated = $users->update(['age' => ['$gte' => 50]], ['age' => 50]);
$updateTime = t() - $updateStart;

// Delete operations
$deleteStart = t();
$deleted = $users->remove(['id' => ['$gte' => $config['document_count'] - 100]]);
$deleteTime = t() - $deleteStart;

$crudTime = t() - $testStart;
$results['crud'] = [
    'insert' => $insertTime,
    'read' => $readTime,
    'update' => $updateTime,
    'delete' => $deleteTime,
    'total' => $crudTime,
];

echo "✓ Inserted {$config['document_count']} users in ".number_format($insertTime, 4).'s ('.number_format($config['document_count'] / $insertTime, 0)." docs/sec)\n";
echo '✓ Read '.count($youngUsers).' young users in '.number_format($readTime, 4)."s\n";
echo "✓ Updated {$updated} users in ".number_format($updateTime, 4)."s\n";
echo "✓ Deleted {$deleted} users in ".number_format($deleteTime, 4)."s\n\n";

// Test 2: ID Generation Modes
echo "=== Test 2: ID Generation Modes ===\n";
$testStart = t();

$db = $client->selectDB('test_id_modes');
$autoCollection = $db->selectCollection('auto_ids');
$manualCollection = $db->selectCollection('manual_ids');
$prefixCollection = $db->selectCollection('prefix_ids');

// Auto mode (default)
$autoCollection->insert(['name' => 'Auto User']);
$autoId = $autoCollection->findOne()['id'];

// Manual mode
$manualCollection->setIdModeManual();
$manualCollection->insert(['_id' => 'manual123', 'name' => 'Manual User']);
$manualId = $manualCollection->findOne()['_id'];

// Prefix mode
$prefixCollection->setIdModePrefix('USR');
$prefixId1 = $prefixCollection->insert(['name' => 'Prefix User 1']);
$prefixId2 = $prefixCollection->insert(['name' => 'Prefix User 2']);

$idTime = t() - $testStart;
$results['id_modes'] = $idTime;

echo "✓ Auto ID: {$autoId}\n";
echo "✓ Manual ID: {$manualId}\n";
echo "✓ Prefix IDs: {$prefixId1}, {$prefixId2}\n";
echo '✓ ID generation test completed in '.number_format($idTime, 4)."s\n\n";

// Test 3: Encryption
echo "=== Test 3: Encryption ===\n";
$testStart = t();

$db = $client->selectDB('test_encryption');
$encryptedCollection = $db->selectCollection('secure_data');
$encryptedCollection->setEncryptionKey('my-secret-key-123');

// Insert encrypted data
for ($i = 0; $i < 500; ++$i) {
    $encryptedCollection->insert([
        'id' => $i,
        'ssn' => 'SSN-'.str_pad($i, 9, '0', STR_PAD_LEFT),
        'metadata' => ['secret' => 'secret_'.$i],
    ]);
}

// Query encrypted data
$encryptedResults = $encryptedCollection->find(['metadata.secret' => 'secret_250'])->toArray();

// Test searchable fields
$encryptedCollection->setSearchableFields(['ssn'], true);
$searchResults = $encryptedCollection->find(['ssn' => 'SSN-000250250'])->toArray();

$encryptionTime = t() - $testStart;
$results['encryption'] = $encryptionTime;

echo "✓ Inserted 500 encrypted documents\n";
echo '✓ Found '.count($encryptedResults)." documents by encrypted field\n";
echo '✓ Found '.count($searchResults)." documents by hashed searchable field\n";
echo '✓ Encryption test completed in '.number_format($encryptionTime, 4)."s\n\n";

// Test 4: Indexing Performance
echo "=== Test 4: Indexing Performance ===\n";
$testStart = t();

$db = $client->selectDB('test_indexes');
$indexedCollection = $db->selectCollection('indexed_data');

// Insert data for indexing
for ($i = 0; $i < 2000; ++$i) {
    $indexedCollection->insert([
        'id' => $i,
        'name' => 'Item_'.$i,
        'category' => ['A', 'B', 'C', 'D'][rand(0, 3)],
        'price' => rand(100, 10000),
        'created_at' => date('Y-m-d H:i:s', strtotime('-'.rand(1, 365).' days')),
    ]);
}

// Create indexes
$indexStart = t();
$db->createJsonIndex('indexed_data', 'category');
$db->createJsonIndex('indexed_data', 'price');
$indexTime = t() - $indexStart;

// Test indexed queries
$queryStart = t();
$categoryResults = $indexedCollection->find(['category' => 'A'])->toArray();
$priceResults = $indexedCollection->find(['price' => ['$gt' => 5000]])->toArray();
$queryTime = t() - $queryStart;

$indexTimeTotal = t() - $testStart;
$results['indexing'] = [
    'creation' => $indexTime,
    'query' => $queryTime,
    'total' => $indexTimeTotal,
];

echo '✓ Created 2 indexes in '.number_format($indexTime, 4)."s\n";
echo '✓ Category query: '.count($categoryResults).' results in '.number_format($queryTime / 2, 4)."s avg\n";
echo '✓ Price query: '.count($priceResults)." results\n";
echo '✓ Indexing test completed in '.number_format($indexTimeTotal, 4)."s\n\n";

// Test 5: Complex Queries
echo "=== Test 5: Complex Queries ===\n";
$testStart = t();

$db = $client->selectDB('test_complex');
$complexCollection = $db->selectCollection('complex_data');

// Insert complex nested data
for ($i = 0; $i < 1000; ++$i) {
    $complexCollection->insert([
        'id' => $i,
        'user' => [
            'id' => 'user_'.$i,
            'profile' => [
                'age' => rand(18, 80),
                'preferences' => ['sports', 'music', 'travel'][rand(0, 2)],
            ],
        ],
        'orders' => [
            ['id' => 'order_1', 'amount' => rand(100, 10000), 'status' => ['completed', 'pending'][rand(0, 1)]],
        ],
    ]);
}

// Complex nested queries
$nestedStart = t();
$ageResults = $complexCollection->find(['user.profile.age' => ['$gt' => 40]])->toArray();
$preferenceResults = $complexCollection->find(['user.profile.preferences' => 'sports'])->toArray();
$nestedTime = t() - $nestedStart;

$complexTime = t() - $testStart;
$results['complex'] = [
    'nested_query' => $nestedTime,
    'total' => $complexTime,
];

echo '✓ Nested age query: '.count($ageResults).' results in '.number_format($nestedTime / 2, 4)."s avg\n";
echo '✓ Preference query: '.count($preferenceResults)." results\n";
echo '✓ Complex queries test completed in '.number_format($complexTime, 4)."s\n\n";

// Test 6: Cursor Operations
echo "=== Test 6: Cursor Operations ===\n";
$testStart = t();

$db = $client->selectDB('test_cursor');
$cursorCollection = $db->selectCollection('cursor_data');

// Insert test data
for ($i = 0; $i < 2000; ++$i) {
    $cursorCollection->insert([
        'id' => $i,
        'name' => 'Item_'.$i,
        'value' => rand(1, 1000),
        'category' => ['A', 'B', 'C'][rand(0, 2)],
    ]);
}

// Test cursor operations
$cursorStart = t();
$cursor = $cursorCollection->find(['value' => ['$gt' => 500]])
    ->sort(['value' => -1])
    ->skip(100)
    ->limit(50);

$cursorResults = $cursor->toArray();
$cursorCount = $cursor->count();
$cursorTime = t() - $cursorStart;

$cursorTimeTotal = t() - $testStart;
$results['cursor'] = [
    'operations' => $cursorTime,
    'total' => $cursorTimeTotal,
];

echo '✓ Cursor pagination: '.count($cursorResults)." results from {$cursorCount} total\n";
echo '✓ Cursor operations completed in '.number_format($cursorTime, 4)."s\n\n";

// Test 7: Hooks
echo "=== Test 7: Event Hooks ===\n";
$testStart = t();

$db = $client->selectDB('test_hooks');
$hookCollection = $db->selectCollection('hooked_data');

$beforeInsertCount = 0;
$afterInsertCount = 0;

// Set up hooks
$hookCollection->on('beforeInsert', function ($doc) use (&$beforeInsertCount) {
    ++$beforeInsertCount;
    $doc['processed_at'] = date('Y-m-d H:i:s');

    return $doc;
});

$hookCollection->on('afterInsert', function ($doc, $id) use (&$afterInsertCount) {
    ++$afterInsertCount;
});

// Insert with hooks
for ($i = 0; $i < 200; ++$i) {
    $hookCollection->insert(['id' => $i, 'name' => 'Hooked User']);
}

$hookTime = t() - $testStart;
$results['hooks'] = $hookTime;

echo "✓ Before insert hooks called: {$beforeInsertCount} times\n";
echo "✓ After insert hooks called: {$afterInsertCount} times\n";
echo '✓ Hooks test completed in '.number_format($hookTime, 4)."s\n\n";

// Test 8: Data Population
echo "=== Test 8: Data Population ===\n";
$testStart = t();

$db = $client->selectDB('test_population');
$users = $db->selectCollection('users');
$posts = $db->selectCollection('posts');

// Insert users
for ($i = 0; $i < 50; ++$i) {
    $users->insert([
        'id' => 'user_'.$i,
        'name' => 'User_'.$i,
        'email' => "user{$i}@example.com",
    ]);
}

// Insert posts with user references
for ($i = 0; $i < 200; ++$i) {
    $userId = 'user_'.rand(0, 49);
    $posts->insert([
        'id' => 'post_'.$i,
        'user_id' => $userId,
        'title' => 'Post '.$i,
        'content' => 'Content for post '.$i,
    ]);
}

// Test population
$populateStart = t();
$postsWithUsers = $posts->find()->populate('user_id', $users)->toArray();
$populateTime = t() - $populateStart;

$populationTime = t() - $testStart;
$results['population'] = [
    'populate' => $populateTime,
    'total' => $populationTime,
];

echo '✓ Populated '.count($postsWithUsers)." posts with user data\n";
echo '✓ Population test completed in '.number_format($populateTime, 4)."s\n\n";

// Final Results
echo "=== Benchmark Results Summary ===\n\n";

$totalTime = 0;
foreach ($results as $category => $data) {
    if (is_array($data)) {
        echo ucfirst(str_replace('_', ' ', $category)).":\n";
        foreach ($data as $operation => $time) {
            if (is_numeric($time)) {
                echo "  - {$operation}: ".number_format($time, 4)."s\n";
                $totalTime += $time;
            }
        }
        echo "\n";
    } else {
        echo ucfirst(str_replace('_', ' ', $category)).': '.number_format($data, 4)."s\n";
        $totalTime += $data;
    }
}

echo 'Total benchmark time: '.number_format($totalTime, 2)." seconds\n";
echo 'Average operations per second: '.number_format($config['document_count'] / $totalTime, 0)."\n";

echo "\nFocused benchmark completed successfully!\n";

// Cleanup
$client->close();
Database::closeAll();
