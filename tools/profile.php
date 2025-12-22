<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PocketDB\Database;

function t()
{
    return microtime(true);
}

echo "PocketDB lightweight profiler\n";

$db = new Database(':memory:');
$coll = $db->selectCollection('bench');

// CLI args: N (count), mode ('bulk'|'single'), chunk size for bulk
$N = isset($argv[1]) ? (int)$argv[1] : 2000;
$mode = isset($argv[2]) ? $argv[2] : 'bulk';
$chunk = isset($argv[3]) ? (int)$argv[3] : 1000;
echo "Inserting $N documents (mode=$mode, chunk=$chunk)...\n";
$start = t();
if ($mode === 'bulk') {
    $batch = [];
    for ($i = 0; $i < $N; $i++) {
        $batch[] = [
            'name' => 'name' . $i,
            'age' => ($i % 80) + 1,
            'city' => 'city' . ($i % 100),
            'active' => ($i % 2) == 0,
            'tags' => ['tag' . ($i % 5), 'tag' . (($i + 1) % 5)],
            'meta' => ['nested' => ['count' => $i]]
        ];

        if (count($batch) >= $chunk) {
            $coll->insert($batch);
            $batch = [];
        }
    }
    if (!empty($batch)) $coll->insert($batch);
} else {
    for ($i = 0; $i < $N; $i++) {
        $doc = [
            'name' => 'name' . $i,
            'age' => ($i % 80) + 1,
            'city' => 'city' . ($i % 100),
            'active' => ($i % 2) == 0,
            'tags' => ['tag' . ($i % 5), 'tag' . (($i + 1) % 5)],
            'meta' => ['nested' => ['count' => $i]]
        ];
        $coll->insert($doc);
    }
}
$dur = t() - $start;
echo sprintf("Insert: %.4fs, memory: %.2fMB\n", $dur, memory_get_peak_usage(true) / 1024 / 1024);

// Simple find
echo "Simple find (age=30) toArray...\n";
$start = t();
$rows = $coll->find(['age' => 30])->toArray();
$dur = t() - $start;
echo sprintf("Found %d rows in %.4fs\n", count($rows), $dur);

// Complex criteria ($gt)
echo "Complex criteria (age > 30) toArray...\n";
$start = t();
$rows = $coll->find(['age' => ['$gt' => 30]])->toArray();
$dur = t() - $start;
echo sprintf("Found %d rows in %.4fs\n", count($rows), $dur);

// Sort and limit
echo "Sort by age desc, limit 100, toArray...\n";
$start = t();
$rows = $coll->find()->sort(['age' => -1])->limit(100)->toArray();
$dur = t() - $start;
echo sprintf("Sort+Limit returned %d rows in %.4fs\n", count($rows), $dur);

// Iteration using each
echo "Iterate using each (age=30)...\n";
$start = t();
$count = 0;
$coll->find(['age' => 30])->each(function ($d) use (&$count) {
    $count++;
});
$dur = t() - $start;
echo sprintf("Iterated %d rows in %.4fs\n", $count, $dur);

// Projection
echo "Projection include name,age...\n";
$start = t();
$rows = $coll->find(['age' => 30], ['name' => 1, 'age' => 1])->toArray();
$dur = t() - $start;
echo sprintf("Projection rows %d in %.4fs\n", count($rows), $dur);

echo "Profiler done.\n";

// Close DB connections
\PocketDB\Database::closeAll();
