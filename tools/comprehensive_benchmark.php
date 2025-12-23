<?php

require_once __DIR__.'/../vendor/autoload.php';

use PocketDB\Client;
use PocketDB\Collection;
use PocketDB\Database;

function t()
{
    return microtime(true);
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);

    if ($bytes == 0) {
        return '0 B';
    }

    $pow = floor(log($bytes) / log(1024));
    $pow = max($pow, 0); // Ensure pow is not negative
    $pow = min($pow, count($units) - 1);

    // Use pow() instead of bit shift to avoid negative shift errors
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision).' '.$units[$pow];
}

function formatMemory($bytes)
{
    return formatBytes($bytes, 1);
}

echo "PocketDB Comprehensive Benchmark\n";
echo "=================================\n\n";

// Configuration
$config = [
    'document_count' => isset($argv[1]) ? (int) $argv[1] : 1000,
    'storage_mode' => isset($argv[2]) ? $argv[2] : 'disk',
    'test_encryption' => isset($argv[3]) && $argv[3] === 'encrypt',
    'test_hooks' => isset($argv[4]) && $argv[4] === 'hooks',
    'test_population' => isset($argv[5]) && $argv[5] === 'populate',
];

echo "Benchmark Configuration:\n";
echo "- Document count: {$config['document_count']}\n";
echo "- Storage mode: {$config['storage_mode']}\n";
echo '- Encryption: '.($config['test_encryption'] ? 'Enabled' : 'Disabled')."\n";
echo '- Hooks: '.($config['test_hooks'] ? 'Enabled' : 'Disabled')."\n";
echo '- Population: '.($config['test_population'] ? 'Enabled' : 'Disabled')."\n\n";

// Database setup
$baseDir = __DIR__.'/../test_databases/comprehensive_bench';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

$dbPath = $config['storage_mode'] === 'memory' ? ':memory:' : $baseDir.'/test.sqlite';
$client = new Client($config['storage_mode'] === 'memory' ? ':memory:' : $baseDir);

$results = [];

echo "Starting Comprehensive Benchmark...\n\n";

// Test 1: Basic CRUD Operations
echo "=== Test 1: Basic CRUD Operations ===\n";
$testStart = t();

$db = $client->selectDB('test_crud');
$users = $db->selectCollection('users');
$products = $db->selectCollection('products');

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

// Insert products
for ($i = 0; $i < $config['document_count'] / 10; ++$i) {
    $products->insert([
        'id' => 'prod_'.$i,
        'name' => 'Product_'.$i,
        'price' => rand(10000, 1000000) / 100,
        'category' => ['Electronics', 'Clothing', 'Food', 'Books'][rand(0, 3)],
        'in_stock' => rand(0, 100),
    ]);
}

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
$autoId = $autoCollection->findOne()['_id'];

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

echo '✓ Auto ID: '.($autoId ?? 'auto-generated')."\n";
echo "✓ Manual ID: {$manualId}\n";
echo "✓ Prefix IDs: {$prefixId1}, {$prefixId2}\n";
echo '✓ ID generation test completed in '.number_format($idTime, 4)."s\n\n";

// Test 3: Encryption (if enabled)
if ($config['test_encryption']) {
    echo "=== Test 3: Encryption ===\n";
    $testStart = t();

    $db = $client->selectDB('test_encryption');
    $encryptedCollection = $db->selectCollection('secure_data');
    $encryptedCollection->setEncryptionKey('my-secret-key-123');

    // Insert encrypted data
    for ($i = 0; $i < 1000; ++$i) {
        $encryptedCollection->insert([
            'id' => $i,
            'ssn' => 'SSN-'.str_pad($i, 9, '0', STR_PAD_LEFT),
            'bank_account' => 'ACC-'.rand(100000000, 999999999),
            'metadata' => ['secret' => 'secret_'.$i],
        ]);
    }

    // Query encrypted data
    $encryptedResults = $encryptedCollection->find(['metadata.secret' => 'secret_500'])->toArray();

    // Test searchable fields
    $encryptedCollection->setSearchableFields(['ssn'], true);
    $searchResults = $encryptedCollection->find(['ssn' => 'SSN-000500500'])->toArray();

    $encryptionTime = t() - $testStart;
    $results['encryption'] = $encryptionTime;

    echo "✓ Inserted 1000 encrypted documents\n";
    echo '✓ Found '.count($encryptedResults)." documents by encrypted field\n";
    echo '✓ Found '.count($searchResults)." documents by hashed searchable field\n";
    echo '✓ Encryption test completed in '.number_format($encryptionTime, 4)."s\n\n";
}

// Test 4: Indexing Performance
echo "=== Test 4: Indexing Performance ===\n";
$testStart = t();

$db = $client->selectDB('test_indexes');
$indexedCollection = $db->selectCollection('indexed_data');

// Insert data for indexing
for ($i = 0; $i < 5000; ++$i) {
    $indexedCollection->insert([
        'id' => $i,
        'name' => 'Item_'.$i,
        'category' => ['A', 'B', 'C', 'D'][rand(0, 3)],
        'price' => rand(100, 10000),
        'created_at' => date('Y-m-d H:i:s', strtotime('-'.rand(1, 365).' days')),
        'tags' => ['tag'.rand(1, 10), 'tag'.rand(1, 10)],
    ]);
}

// Create indexes
$indexStart = t();
$db->createJsonIndex('indexed_data', 'category');
$db->createJsonIndex('indexed_data', 'price');
$db->createJsonIndex('indexed_data', 'created_at');
$db->createJsonIndex('indexed_data', 'tags');
$indexTime = t() - $indexStart;

// Test indexed queries
$queryStart = t();
$categoryResults = $indexedCollection->find(['category' => 'A'])->toArray();
$priceResults = $indexedCollection->find(['price' => ['$gt' => 5000]])->toArray();
$tagResults = $indexedCollection->find(['tags' => ['$has' => 'tag5']])->toArray();
$queryTime = t() - $queryStart;

$indexTimeTotal = t() - $testStart;
$results['indexing'] = [
    'creation' => $indexTime,
    'query' => $queryTime,
    'total' => $indexTimeTotal,
];

echo '✓ Created 4 indexes in '.number_format($indexTime, 4)."s\n";
echo '✓ Category query (indexed): '.count($categoryResults).' results in '.number_format($queryTime / 3, 4)."s avg\n";
echo '✓ Price query (indexed): '.count($priceResults)." results\n";
echo '✓ Tag query (indexed): '.count($tagResults)." results\n\n";

// Test 5: Complex Queries and Aggregation
echo "=== Test 5: Complex Queries and Aggregation ===\n";
$testStart = t();

$db = $client->selectDB('test_complex');
$complexCollection = $db->selectCollection('complex_data');

// Insert complex nested data
for ($i = 0; $i < 2000; ++$i) {
    $complexCollection->insert([
        'id' => $i,
        'user' => [
            'id' => 'user_'.$i,
            'profile' => [
                'age' => rand(18, 80),
                'preferences' => ['sports', 'music', 'travel'][rand(0, 2)],
                'stats' => [
                    'login_count' => rand(10, 1000),
                    'session_time' => rand(100, 10000),
                ],
            ],
        ],
        'orders' => [
            ['id' => 'order_1', 'amount' => rand(100, 10000), 'status' => ['completed', 'pending', 'cancelled'][rand(0, 2)]],
            ['id' => 'order_2', 'amount' => rand(100, 10000), 'status' => ['completed', 'pending', 'cancelled'][rand(0, 2)]],
        ],
        'metadata' => [
            'created_at' => date('Y-m-d H:i:s', strtotime('-'.rand(1, 365).' days')),
            'updated_at' => date('Y-m-d H:i:s'),
            'version' => rand(1, 10),
        ],
    ]);
}

// Complex nested queries
$nestedStart = t();
$ageResults = $complexCollection->find(['user.profile.age' => ['$gt' => 40]])->toArray();
$preferenceResults = $complexCollection->find(['user.profile.preferences' => 'sports'])->toArray();
$orderResults = $complexCollection->find(['orders' => ['$has' => 'completed']])->toArray();
$nestedTime = t() - $nestedStart;

// Aggregation in PHP
$aggStart = t();
$allDocs = $complexCollection->find()->toArray();
$ageGroups = [];
foreach ($allDocs as $doc) {
    $age = $doc['user']['profile']['age'] ?? 0;
    $ageGroup = floor($age / 10) * 10;
    if (!isset($ageGroups[$ageGroup])) {
        $ageGroups[$ageGroup] = ['count' => 0, 'total_session_time' => 0];
    }
    ++$ageGroups[$ageGroup]['count'];
    $ageGroups[$ageGroup]['total_session_time'] += $doc['user']['profile']['stats']['session_time'] ?? 0;
}
$aggTime = t() - $aggStart;

$complexTime = t() - $testStart;
$results['complex'] = [
    'nested_query' => $nestedTime,
    'aggregation' => $aggTime,
    'total' => $complexTime,
];

echo '✓ Nested age query: '.count($ageResults).' results in '.number_format($nestedTime / 3, 4)."s avg\n";
echo '✓ Preference query: '.count($preferenceResults)." results\n";
echo '✓ Order status query: '.count($orderResults)." results\n";
echo '✓ Aggregation completed in '.number_format($aggTime, 4)."s\n\n";

// Test 6: Hooks (if enabled)
if ($config['test_hooks']) {
    echo "=== Test 6: Event Hooks ===\n";
    $testStart = t();

    $db = $client->selectDB('test_hooks');
    $hookCollection = $db->selectCollection('hooked_data');

    $beforeInsertCount = 0;
    $afterInsertCount = 0;
    $beforeUpdateCount = 0;
    $afterUpdateCount = 0;

    // Set up hooks
    $hookCollection->on('beforeInsert', function ($doc) use (&$beforeInsertCount) {
        ++$beforeInsertCount;
        // Modify document before insert
        $doc['processed_at'] = date('Y-m-d H:i:s');

        return $doc;
    });

    $hookCollection->on('afterInsert', function ($doc, $id) use (&$afterInsertCount) {
        ++$afterInsertCount;
    });

    $hookCollection->on('beforeUpdate', function ($criteria, $data) use (&$beforeUpdateCount) {
        ++$beforeUpdateCount;

        return $criteria;
    });

    $hookCollection->on('afterUpdate', function ($oldDoc, $newDoc) use (&$afterUpdateCount) {
        ++$afterUpdateCount;
    });

    // Insert with hooks
    for ($i = 0; $i < 500; ++$i) {
        $hookCollection->insert(['id' => $i, 'name' => 'Hooked User']);
    }

    // Update with hooks
    $hookCollection->update(['id' => ['$lt' => 100]], ['status' => 'updated']);

    $hookTime = t() - $testStart;
    $results['hooks'] = $hookTime;

    echo "✓ Before insert hooks called: {$beforeInsertCount} times\n";
    echo "✓ After insert hooks called: {$afterInsertCount} times\n";
    echo "✓ Before update hooks called: {$beforeUpdateCount} times\n";
    echo "✓ After update hooks called: {$afterUpdateCount} times\n";
    echo '✓ Hooks test completed in '.number_format($hookTime, 4)."s\n\n";
}

// Test 7: Data Population (if enabled)
if ($config['test_population']) {
    echo "=== Test 7: Data Population ===\n";
    $testStart = t();

    $db = $client->selectDB('test_population');
    $users = $db->selectCollection('users');
    $posts = $db->selectCollection('posts');
    $comments = $db->selectCollection('comments');

    // Insert users
    for ($i = 0; $i < 100; ++$i) {
        $users->insert([
            'id' => 'user_'.$i,
            'name' => 'User_'.$i,
            'email' => "user{$i}@example.com",
        ]);
    }

    // Insert posts with user references
    for ($i = 0; $i < 500; ++$i) {
        $userId = 'user_'.rand(0, 99);
        $posts->insert([
            'id' => 'post_'.$i,
            'user_id' => $userId,
            'title' => 'Post '.$i,
            'content' => 'Content for post '.$i,
        ]);
    }

    // Insert comments with post references
    for ($i = 0; $i < 2000; ++$i) {
        $postId = 'post_'.rand(0, 499);
        $comments->insert([
            'id' => 'comment_'.$i,
            'post_id' => $postId,
            'content' => 'Comment '.$i,
        ]);
    }

    // Test population
    $populateStart = t();
    $postsWithUsers = $posts->find()->populate('user_id', $users)->toArray();
    $commentWithPosts = $comments->find()->populate('post_id', $posts)->toArray();
    $populateTime = t() - $populateStart;

    $populationTime = t() - $testStart;
    $results['population'] = [
        'populate' => $populateTime,
        'total' => $populationTime,
    ];

    echo '✓ Populated '.count($postsWithUsers)." posts with user data\n";
    echo '✓ Populated '.count($commentWithPosts)." comments with post data\n";
    echo '✓ Population test completed in '.number_format($populateTime, 4)."s\n\n";
}

// Test 8: Memory Usage and Performance
echo "=== Test 8: Memory Usage and Performance ===\n";
$testStart = t();

$memoryStart = memory_get_usage(true);
$peakMemory = memory_get_peak_usage(true);

// Large dataset operations
$db = $client->selectDB('test_memory');
$largeCollection = $db->selectCollection('large_dataset');

// Insert large documents
for ($i = 0; $i < 1000; ++$i) {
    $largeCollection->insert([
        'id' => $i,
        'data' => str_repeat('x', rand(1000, 5000)),
        'metadata' => [
            'large_array' => array_fill(0, rand(10, 100), 'item_'.$i),
            'nested' => ['level1' => ['level2' => ['level3' => 'deep_value']]],
        ],
    ]);
}

// Memory intensive operations
$memoryOpsStart = t();
$largeResults = $largeCollection->find(['metadata.nested.level1.level2.level3' => 'deep_value'])->toArray();
$memoryOpsTime = t() - $memoryOpsStart;

$memoryEnd = memory_get_usage(true);
$memoryTime = t() - $testStart;
$results['memory'] = [
    'peak_memory' => $peakMemory,
    'current_memory' => $memoryEnd,
    'memory_growth' => $memoryEnd - $memoryStart,
    'operations' => $memoryOpsTime,
    'total' => $memoryTime,
];

echo '✓ Peak memory usage: '.formatMemory($peakMemory)."\n";
echo '✓ Memory growth: '.formatMemory($memoryEnd - $memoryStart)."\n";
echo '✓ Memory operations completed in '.number_format($memoryOpsTime, 4)."s\n\n";

// Test 9: Cursor Operations
echo "=== Test 9: Cursor Operations ===\n";
$testStart = t();

$db = $client->selectDB('test_cursor');
$cursorCollection = $db->selectCollection('cursor_data');

// Insert test data
for ($i = 0; $i < 5000; ++$i) {
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

// Test 10: Database Operations
echo "=== Test 10: Database Operations ===\n";
$testStart = t();

$db = $client->selectDB('test_db_ops');
$testCollection = $db->selectCollection('db_test');

// Test various database operations
$dbOpsStart = t();

// Collection operations
$db->createCollection('temp_collection');
$db->dropCollection('temp_collection');

// Database operations
$db->vacuum();

// JSON Index operations
$db->createJsonIndex('db_test', 'id');
$db->createJsonIndex('db_test', 'name');

$dbOpsTime = t() - $dbOpsStart;
$dbTimeTotal = t() - $testStart;
$results['database'] = [
    'operations' => $dbOpsTime,
    'total' => $dbTimeTotal,
];

echo "✓ Collection create/drop operations completed\n";
echo "✓ Database vacuum completed\n";
echo "✓ Index operations completed\n";
echo '✓ Database operations completed in '.number_format($dbOpsTime, 4)."s\n\n";

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

echo 'Total benchmark time: '.number_format($totalTime, 4)." seconds\n";
echo 'Average operations per second: '.number_format($config['document_count'] / $totalTime, 0)."\n";

// Memory summary
if (isset($results['memory'])) {
    echo 'Peak memory usage: '.formatMemory($results['memory']['peak_memory'])."\n";
    $memoryEfficiency = $results['memory']['peak_memory'] > 0 ? $config['document_count'] / $results['memory']['peak_memory'] : 0;
    echo 'Memory efficiency: '.number_format($memoryEfficiency, 2)." documents per MB\n";
}

echo "\nComprehensive benchmark completed successfully!\n";

// Cleanup
$client->close();
Database::closeAll();
