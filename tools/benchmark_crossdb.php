<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PocketDB\Database;

function t()
{
    return microtime(true);
}

$N = isset($argv[1]) ? (int)$argv[1] : 100000;
$chunk = isset($argv[2]) ? (int)$argv[2] : 2000;

echo "Cross-database benchmark (N={$N}, chunk={$chunk})\n";

$baseDir = __DIR__ . '/../test_databases';
if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

$configs = [
    ['name' => 'memory', 'path' => ':memory:', 'pragmas' => []],
    ['name' => 'disk-wal-normal', 'path' => $baseDir . '/bench_wal.sqlite', 'pragmas' => ['journal_mode' => 'WAL', 'synchronous' => 'NORMAL']],
    ['name' => 'disk-wal-sync-off', 'path' => $baseDir . '/bench_wal_syncoff.sqlite', 'pragmas' => ['journal_mode' => 'WAL', 'synchronous' => 'OFF']],
    ['name' => 'disk-delete-sync-off', 'path' => $baseDir . '/bench_delete_syncoff.sqlite', 'pragmas' => ['journal_mode' => 'DELETE', 'synchronous' => 'OFF']],
];

function runConfig($name, $path, $pragmas, $N, $chunk)
{
    if ($path !== ':memory:' && file_exists($path)) @unlink($path);

    $db = new Database($path);

    // apply pragmas if requested
    foreach ($pragmas as $k => $v) {
        try {
            $db->connection->exec("PRAGMA {$k} = {$v}");
        } catch (\Throwable $e) {
        }
    }

    $coll = $db->selectCollection('bench');

    $start = t();
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

    $start = t();
    $rows1 = $coll->find(['age' => 30])->toArray();
    $findDur = t() - $start;

    $start = t();
    $rows2 = $coll->find(['age' => ['$gt' => 30]])->toArray();
    $complexDur = t() - $start;

    $start = t();
    $rows3 = $coll->find()->sort(['age' => -1])->limit(100)->toArray();
    $sortDur = t() - $start;

    Database::closeAll();

    return ['insert' => $insertDur, 'find' => $findDur, 'complex' => $complexDur, 'sort' => $sortDur, 'counts' => [count($rows1), count($rows2), count($rows3)]];
}

$results = [];
foreach ($configs as $cfg) {
    echo "\n== Running: {$cfg['name']} ({$cfg['path']}) ==\n";
    $results[$cfg['name']] = runConfig($cfg['name'], $cfg['path'], $cfg['pragmas'], $N, $chunk);
}

echo "\nSummary (seconds):\n";
printf("%-20s %-10s %-10s %-10s %-10s\n", 'config', 'insert', 'find', 'complex', 'sort');
foreach ($results as $name => $r) {
    printf("%-20s %-10.4f %-10.4f %-10.4f %-10.4f\n", $name, $r['insert'], $r['find'], $r['complex'], $r['sort']);
}

echo "\nCounts (find, complex, sort-limit):\n";
foreach ($results as $name => $r) {
    echo sprintf("%-20s %s\n", $name, implode(', ', $r['counts']));
}

echo "\nDone.\n";
