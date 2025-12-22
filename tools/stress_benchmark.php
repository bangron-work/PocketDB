<?php

require_once __DIR__.'/../vendor/autoload.php';

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

echo "PocketDB Stress Benchmark\n";
echo "=========================\n\n";

// CLI args: N (count), mode ('memory'|'disk'), chunk size, concurrent operations
$N = isset($argv[1]) ? (int) $argv[1] : 50000;
$mode = isset($argv[2]) ? $argv[2] : 'disk';
$chunk = isset($argv[3]) ? (int) $argv[3] : 5000;
$concurrent = isset($argv[4]) ? (int) $argv[4] : 1;

// Detect child worker mode: script can be invoked with `--child <dbPath> <start> <count> <chunk> <workerId>`
$isChild = false;
$childParams = null;
foreach ($argv as $i => $a) {
    if ($a === '--child') {
        $isChild = true;
        $childParams = [
            'dbPath' => $argv[$i + 1] ?? null,
            'start' => isset($argv[$i + 2]) ? (int) $argv[$i + 2] : 0,
            'count' => isset($argv[$i + 3]) ? (int) $argv[$i + 3] : 0,
            'chunk' => isset($argv[$i + 4]) ? (int) $argv[$i + 4] : $chunk,
            'workerId' => isset($argv[$i + 5]) ? (int) $argv[$i + 5] : 0,
        ];
        break;
    }
}

$filePath = __DIR__.'/../test_databases/stress_bench.sqlite';
$baseDir = dirname($filePath);

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

echo "Stress Test Configuration:\n";
echo "- Document count: $N\n";
echo "- Storage mode: $mode\n";
echo "- Chunk size: $chunk\n";
echo "- Concurrent operations: $concurrent\n";
echo '- Database file: '.($mode === 'memory' ? ':memory:' : $filePath)."\n\n";

function runStressTest($dbPath, $N, $chunk, $testName)
{
    $isMemory = ($dbPath === ':memory:');
    echo "Running $testName...\n";

    if (!$isMemory && file_exists($dbPath)) {
        @unlink($dbPath);
    }

    $db = new Database($dbPath);
    $coll = $db->selectCollection('stress_test');

    $results = [];

    // 1. Bulk Insert Test
    echo "  1. Bulk Insert Test...\n";
    $start = t();
    $batch = [];
    for ($i = 0; $i < $N; ++$i) {
        $batch[] = [
            'id' => $i,
            'name' => 'user_'.$i,
            'email' => 'user_'.$i.'@example.com',
            'age' => rand(18, 80),
            'city' => ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Makassar'][rand(0, 4)],
            'tags' => ['tag'.rand(1, 10), 'tag'.rand(1, 10)],
            'metadata' => [
                'created_at' => date('Y-m-d H:i:s', strtotime('-'.rand(1, 365).' days')),
                'updated_at' => date('Y-m-d H:i:s'),
                'score' => rand(1, 1000) / 10,
                'active' => rand(0, 1) === 1,
                'profile' => [
                    'level' => rand(1, 50),
                    'experience' => rand(1000, 100000),
                    'achievements' => array_fill(0, rand(1, 20), 'achievement_'.rand(1, 100)),
                ],
            ],
        ];

        if (count($batch) >= $chunk) {
            $coll->insert($batch);
            $batch = [];
        }
    }
    if (!empty($batch)) {
        $coll->insert($batch);
    }
    $insertTime = t() - $start;
    $results['insert'] = $insertTime;
    echo "    ✓ Inserted $N documents in ".number_format($insertTime, 4)."s\n";
    echo '    ✓ Average insert rate: '.number_format($N / $insertTime)." docs/sec\n";

    // 2. Memory Usage Test
    $memoryPeak = memory_get_peak_usage(true);
    echo '    ✓ Peak memory usage: '.formatBytes($memoryPeak)."\n";

    // 3. Simple Query Test
    echo "  2. Simple Query Test...\n";
    $start = t();
    $simpleResults = $coll->find(['age' => 25])->toArray();
    $simpleTime = t() - $start;
    $results['simple_query'] = $simpleTime;
    echo '    ✓ Simple query found '.count($simpleResults).' documents in '.number_format($simpleTime, 4)."s\n";

    // 4. Complex Query Test
    echo "  3. Complex Query Test...\n";
    $start = t();
    $complexResults = $coll->find([
        'age' => ['$gt' => 30, '$lt' => 60],
        'metadata.active' => true,
        'metadata.profile.level' => ['$gt' => 10],
    ])->toArray();
    $complexTime = t() - $start;
    $results['complex_query'] = $complexTime;
    echo '    ✓ Complex query found '.count($complexResults).' documents in '.number_format($complexTime, 4)."s\n";

    // 4. Aggregation Test (implemented in PHP using native find())
    echo "  4. Aggregation Test...\n";
    $start = t();
    $rows = $coll->find(['age' => ['$gt' => 25]])->toArray();
    $groups = [];
    foreach ($rows as $r) {
        $city = $r['city'] ?? '(none)';
        if (!isset($groups[$city])) {
            $groups[$city] = ['count' => 0, 'sum_age' => 0, 'sum_score' => 0];
        }
        ++$groups[$city]['count'];
        $groups[$city]['sum_age'] += ($r['age'] ?? 0);
        $groups[$city]['sum_score'] += ($r['metadata']['score'] ?? 0);
    }
    $aggregationResults = [];
    foreach ($groups as $city => $g) {
        $aggregationResults[] = [
            '_id' => $city,
            'count' => $g['count'],
            'avg_age' => $g['count'] ? ($g['sum_age'] / $g['count']) : 0,
            'avg_score' => $g['count'] ? ($g['sum_score'] / $g['count']) : 0,
        ];
    }
    usort($aggregationResults, function ($a, $b) { return $b['count'] <=> $a['count']; });
    $aggregationResults = array_slice($aggregationResults, 0, 10);
    $aggregationTime = t() - $start;
    $results['aggregation'] = $aggregationTime;
    echo '    ✓ Aggregation returned '.count($aggregationResults).' groups in '.number_format($aggregationTime, 4)."s\n";

    // 6. Index Creation Test
    echo "  5. Index Creation Test...\n";
    $start = t();
    try {
        $db->createJsonIndex('stress_test', 'age');
        $db->createJsonIndex('stress_test', 'metadata.active');
        $db->createJsonIndex('stress_test', 'metadata.profile.level');
        $indexTime = t() - $start;
        $results['index_creation'] = $indexTime;
        echo '    ✓ Created 3 indexes in '.number_format($indexTime, 4)."s\n";
    } catch (Exception $e) {
        $results['index_creation'] = -1;
        echo '    ✗ Index creation failed: '.$e->getMessage()."\n";
    }

    // 6. Update Test
    echo "  6. Update Test...\n";
    $start = t();
    $updateCount = 0;
    // Use existing update() API in batches to avoid relying on updateMany
    for ($i = 0; $i < min(1000, $N); $i += 100) {
        $criteria = ['id' => ['$gte' => $i, '$lt' => $i + 100]];
        $data = ['metadata' => array_merge(['last_updated' => date('Y-m-d H:i:s')], [])];
        $updated = $coll->update($criteria, ['$set' => ['metadata.last_updated' => date('Y-m-d H:i:s')]], true);
        // update() returns number of updated rows
        $updateCount += is_int($updated) ? $updated : 0;
    }
    $updateTime = t() - $start;
    $results['update'] = $updateTime;
    echo "    ✓ Updated $updateCount documents in ".number_format($updateTime, 4)."s\n";

    // 7. Delete Test
    echo "  7. Delete Test...\n";
    $start = t();
    $deleteCount = min(5000, (int) ($N / 10));
    $removed = $coll->remove(['id' => ['$lt' => $deleteCount]]);
    $deleteTime = t() - $start;
    $results['delete'] = $deleteTime;
    echo "    ✓ Deleted $removed documents in ".number_format($deleteTime, 4)."s\n";

    // Final document count
    $finalCount = $coll->count();
    echo '  ✓ Final document count: '.number_format($finalCount)."\n\n";

    Database::closeAll();

    return $results;
}

/**
 * Worker implementation used when script is started with --child.
 * Performs insert for assigned id range and runs a small set of reads/updates/deletes.
 * Emits JSON result line prefixed with WORKER_RESULT for master parsing.
 */
function runWorker(string $dbPath, int $start, int $count, int $chunk, int $workerId)
{
    echo "Worker {$workerId} starting (start={$start}, count={$count})\n";

    $db = new Database($dbPath);
    $coll = $db->selectCollection('stress_test');

    $inserted = 0;
    $batch = [];
    $t0 = t();
    for ($i = $start; $i < $start + $count; ++$i) {
        $batch[] = [
            'id' => $i,
            'name' => 'worker_'.$workerId.'_user_'.$i,
            'value' => rand(1, 1000),
            'metadata' => ['worker' => $workerId],
        ];

        if (count($batch) >= $chunk) {
            $coll->insert($batch);
            $inserted += count($batch);
            $batch = [];
        }
    }
    if (!empty($batch)) {
        $coll->insert($batch);
        $inserted += count($batch);
    }
    $insertTime = t() - $t0;

    // perform a few reads and updates in this worker's id range
    $readStart = $start;
    $readCount = min(100, $count);
    $tR = t();
    $rows = $coll->find(['id' => ['$gte' => $readStart, '$lt' => $readStart + $readCount]])->toArray();
    $readTime = t() - $tR;

    $tU = t();
    $updated = 0;
    if (!empty($rows)) {
        foreach ($rows as $r) {
            $coll->update(['id' => $r['id']], ['value' => ($r['value'] ?? 0) + 1], true);
            ++$updated;
        }
    }
    $updateTime = t() - $tU;

    // delete a small portion
    $tD = t();
    $delCount = (int) min((int) ($count * 0.02), 50);
    if ($delCount > 0) {
        $coll->remove(['id' => ['$gte' => $start, '$lt' => $start + $delCount]]);
    }
    $deleteTime = t() - $tD;

    $finalCount = $coll->count();

    $results = [
        'workerId' => $workerId,
        'inserted' => $inserted,
        'insertTime' => $insertTime,
        'readTime' => $readTime,
        'updateCount' => $updated,
        'updateTime' => $updateTime,
        'deletedApprox' => $delCount,
        'deleteTime' => $deleteTime,
        'finalCount' => $finalCount,
    ];

    echo 'WORKER_RESULT '.json_encode($results)."\n";

    Database::closeAll();

    return $results;
}

// If invoked as child worker, run worker and exit
if ($isChild && $childParams) {
    $dbPathChild = $childParams['dbPath'] === ':memory:' ? ':memory:' : $childParams['dbPath'];
    runWorker($dbPathChild, $childParams['start'], $childParams['count'], $childParams['chunk'], $childParams['workerId']);
    exit(0);
}

// If concurrency requested >1, spawn worker processes and wait
if ($concurrent > 1) {
    echo "Master: spawning {$concurrent} workers...\n";

    // prepare DB file (remove existing)
    if ($mode !== 'memory' && file_exists($filePath)) {
        @unlink($filePath);
    }

    // Create DB and JSON indexes before spawning workers so queries use indexes
    echo "Master: creating initial database and indexes...\n";
    $initDbPath = $mode === 'memory' ? ':memory:' : $filePath;
    $initDb = new Database($initDbPath);
    // Ensure collection/table exists before creating indexes
    $initDb->createCollection('stress_test');
    // id and value used in worker criteria/updates; metadata.worker helps partitioned reads
    try {
        $initDb->createJsonIndex('stress_test', 'id');
        $initDb->createJsonIndex('stress_test', 'value');
        $initDb->createJsonIndex('stress_test', 'metadata.worker');
    } catch (Throwable $e) {
        echo 'Warning: failed to create index in master: '.$e->getMessage()."\n";
    }
    Database::closeAll();

    $per = intdiv($N, $concurrent);
    $rem = $N % $concurrent;
    $workers = [];
    $startIdx = 0;

    for ($w = 0; $w < $concurrent; ++$w) {
        $countW = $per + ($w < $rem ? 1 : 0);
        $startW = $startIdx;
        $startIdx += $countW;

        $cmd = escapeshellcmd(PHP_BINARY).' '.escapeshellarg(__FILE__)
            .' --child '.escapeshellarg($mode === 'memory' ? ':memory:' : $filePath)
            .' '.escapeshellarg((string) $startW)
            .' '.escapeshellarg((string) $countW)
            .' '.escapeshellarg((string) $chunk)
            .' '.escapeshellarg((string) $w);

        $descriptorspec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptorspec, $pipes, __DIR__);
        if (is_resource($proc)) {
            $workers[] = ['proc' => $proc, 'pipes' => $pipes, 'cmd' => $cmd, 'start' => $startW, 'count' => $countW, 'id' => $w];
        } else {
            echo "Failed to start worker {$w}\n";
        }
    }

    // wait for workers and collect results
    $aggregate = ['inserted' => 0, 'insertTime' => 0, 'readTime' => 0, 'updateCount' => 0, 'updateTime' => 0, 'deletedApprox' => 0];
    foreach ($workers as $idx => $wdata) {
        $proc = $wdata['proc'];
        $pipes = $wdata['pipes'];

        // read all output
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($proc);

        echo "--- Worker {$wdata['id']} output ---\n";
        echo $out;
        if ($err) {
            echo "(stderr)\n".$err."\n";
        }

        // parse WORKER_RESULT JSON
        if (preg_match('/WORKER_RESULT\s*(\{.*\})/s', $out, $m)) {
            $res = json_decode($m[1], true);
            if ($res) {
                $aggregate['inserted'] += $res['inserted'] ?? 0;
                $aggregate['insertTime'] += $res['insertTime'] ?? 0;
                $aggregate['readTime'] += $res['readTime'] ?? 0;
                $aggregate['updateCount'] += $res['updateCount'] ?? 0;
                $aggregate['updateTime'] += $res['updateTime'] ?? 0;
                $aggregate['deletedApprox'] += $res['deletedApprox'] ?? 0;
            }
        }
    }

    // After workers done, master can run final queries
    $db = new Database($mode === 'memory' ? ':memory:' : $filePath);
    $coll = $db->selectCollection('stress_test');
    $finalCount = $coll->count();

    echo "Master: all workers completed. Aggregate inserted: {$aggregate['inserted']}. Final count: {$finalCount}\n";

    Database::closeAll();

    exit(0);
}

// Run stress test (single-process)
$startTime = t();
$results = runStressTest($mode === 'memory' ? ':memory:' : $filePath, $N, $chunk, "Stress Test ($mode)");
$totalTime = t() - $startTime;

// Display results
echo "Stress Test Results Summary:\n";
echo "============================\n\n";

echo 'Total test time: '.number_format($totalTime, 2)." seconds\n\n";

echo "Operation Breakdown:\n";
echo '- Bulk Insert: '.number_format($results['insert'], 4).'s ('.number_format($N / $results['insert'], 0)." docs/sec)\n";
echo '- Simple Query: '.number_format($results['simple_query'], 4)."s\n";
echo '- Complex Query: '.number_format($results['complex_query'], 4)."s\n";
echo '- Aggregation: '.number_format($results['aggregation'], 4)."s\n";
echo '- Index Creation: '.($results['index_creation'] >= 0 ? number_format($results['index_creation'], 4).'s' : 'Failed')."\n";
echo '- Update Operations: '.number_format($results['update'], 4)."s\n";
echo '- Delete Operations: '.number_format($results['delete'], 4)."s\n\n";

echo "Performance Metrics:\n";
echo '- Documents per second: '.number_format($N / $totalTime, 0)."\n";
echo '- Average query time: '.number_format(($results['simple_query'] + $results['complex_query']) / 2, 4)."s\n";

echo "\nStress benchmark completed successfully.\n";
