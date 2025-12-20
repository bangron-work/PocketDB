# PocketDB — Usage Examples

Berikut contoh kode untuk beberapa skenario praktis.

1. Populate across databases (application-level)

```php
$client = new \PocketDB\Client('/path/to/dbs');
$ordersColl = $client->selectDB('ecommerce')->selectCollection('orders');
$orders = $ordersColl->find(['status' => 'paid'])->toArray();

// populate user details from base.users
$orders = $ordersColl->populate($orders, 'user_id', 'base.users', '_id', 'user');

foreach ($orders as $o) {
    echo $o['user']['name'] . " ordered " . json_decode($o['document'], true)['items'][0]['sku'];
}
```

2. ATTACH and perform a SQL join (DB-level)

```php
$client = new \PocketDB\Client('/path/to/dbs');
$db = $client->selectDB('ecommerce');

// Attach base DB into this connection
$db->connection->exec("ATTACH DATABASE '/abs/path/to/base.sqlite' AS base_alias");

$sql = "SELECT json_extract(a.document, '$.user_id') as uid,
               a.document as order_doc,
               b.document as user_doc
        FROM {$db->name} a
        JOIN base_alias.users b
          ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')
        WHERE json_extract(b.document, '$.role') = 'admin'";

$rows = $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Detach when done
$db->connection->exec("DETACH DATABASE base_alias");
```

3. Simple client shard routing example

```php
function selectShardForKey($client, $baseName, $key, $shardCount = 4) {
    $hash = crc32((string)$key);
    $shard = $hash % $shardCount;
    $name = sprintf('%s_shard_%04d', $baseName, $shard);
    return $client->selectDB($name);
}

$client = new \PocketDB\Client('/path/to/dbs');
$db = selectShardForKey($client, 'ecommerce', $userId, 8);
$coll = $db->selectCollection('orders');
$coll->insert(['user_id' => $userId, 'items' => [...] ]);
```

4. Bulk load with temporary synchronous OFF

```php
$db = $client->selectDB('ecommerce');
$db->connection->exec("PRAGMA synchronous=OFF");
// run large batched insert
$coll->insert($bigBatch);
$db->connection->exec("PRAGMA synchronous=NORMAL");
```

5. Running benchmarks (examples)

```powershell
php tools\benchmark_disk.php 100000 2000

php tools\benchmark_crossdb.php 100000 2000
```

6. Using `attachOnce()` helper (safe attach/detach)

```php
$client = new \PocketDB\Client('/path/to/dbs');
$db = $client->selectDB('ecommerce');

// attachOnce will attach, run the callback and always detach
$orders = $db->attachOnce('/abs/path/to/base.sqlite', 'base_alias', function($dbInstance, $alias) use ($db) {
  $table = $db->selectCollection('orders')->name;
  $sql = "SELECT a.document AS order_doc, b.document AS user_doc
      FROM {$table} a
      JOIN {$alias}.users b
        ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')";
  return $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
});

foreach ($orders as $row) {
  // process joined order + user
}
```
