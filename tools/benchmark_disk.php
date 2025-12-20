<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PocketDB\Database;

function t()
{
    return microtime(true);
}

// CLI args: N, chunk, filePath
$N = isset($argv[1]) ? (int)$argv[1] : 100000;
$chunk = isset($argv[2]) ? (int)$argv[2] : 2000;
$filePath = isset($argv[3]) ? $argv[3] : __DIR__ . '/../test_databases/bench.sqlite';

// Ensure directory exists
$dir = dirname($filePath);
if (!is_dir($dir)) mkdir($dir, 0777, true);

echo "Benchmark: memory vs disk-backed (N={$N}, chunk={$chunk})\n";

function runBench($dbPath, $N, $chunk)
{
    $isMemory = ($dbPath === ':memory:');
    echo "\nRunning on " . ($isMemory ? 'memory' : $dbPath) . "\n";

    if (!$isMemory && file_exists($dbPath)) {
        @unlink($dbPath);
    }

    $db = new Database($dbPath);
    $coll = $db->selectCollection('bench');

    $start = t();
    // bulk insert in chunks
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
    $insertDur = t() - $start;

    // simple find
    $start = t();
    $rows1 = $coll->find(['age' => 30])->toArray();
    $findDur = t() - $start;

    // complex criteria
    $start = t();
    $rows2 = $coll->find(['age' => ['$gt' => 30]])->toArray();
    $complexDur = t() - $start;

    // sort + limit
    $start = t();
    $rows3 = $coll->find()->sort(['age' => -1])->limit(100)->toArray();
    $sortDur = t() - $start;

    // projection
    $start = t();
    $rows4 = $coll->find(['age' => 30], ['name' => 1, 'age' => 1])->toArray();
    $projDur = t() - $start;

    // cleanup
    Database::closeAll();

    return [
        'insert' => $insertDur,
        'find' => $findDur,
        'complex' => $complexDur,
        'sort' => $sortDur,
        'proj' => $projDur,
        'counts' => [count($rows1), count($rows2), count($rows3), count($rows4)],
    ];
}

$mem = runBench(':memory:', $N, $chunk);
$disk = runBench($filePath, $N, $chunk);

echo "\nSummary (seconds):\n";
printf("%-12s %-10s %-10s %-10s\n", 'operation', 'memory', 'disk', 'ratio');
foreach (['insert', 'find', 'complex', 'sort', 'proj'] as $op) {
    $m = $mem[$op];
    $d = $disk[$op];
    $r = $d > 0 ? $d / $m : INF;
    printf("%-12s %-10.4f %-10.4f %-10.2f\n", $op, $m, $d, $r);
}

echo "\nCounts (find,complex,sort-limit,proj): ";
echo implode(', ', $mem['counts']) . " (memory) vs " . implode(', ', $disk['counts']) . " (disk)\n";

echo "\nBenchmark complete.\n";
