# PocketDB — Contoh Penggunaan

Berikut beberapa contoh praktis dan pola penggunaan PocketDB.

1. Populate lintas database (tingkat aplikasi)

```php
$client = new \PocketDB\Client('/path/to/dbs');
$ordersColl = $client->selectDB('ecommerce')->selectCollection('orders');
$orders = $ordersColl->find(['status' => 'paid'])->toArray();

// Populate detail pengguna dari koleksi users di DB lain (contoh pada level aplikasi)
$orders = $ordersColl->populate('user_id', $client->selectDB('base')->selectCollection('users'))->toArray();

foreach ($orders as $o) {
    echo $o['user']['name'] . " ordered " . $o['items'][0]['sku'];
}
```

2. ATTACH dan lakukan JOIN SQL (tingkat DB)

```php
$client = new \PocketDB\Client('/path/to/dbs');
$db = $client->selectDB('ecommerce');

// Lampirkan file DB lain sebagai alias
$db->connection->exec("ATTACH DATABASE '/abs/path/to/base.sqlite' AS base_alias");

$sql = "SELECT json_extract(a.document, '$.user_id') AS uid,
               a.document AS order_doc,
               b.document AS user_doc
        FROM {$db->selectCollection('orders')->name} a
        JOIN base_alias.users b
          ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')
        WHERE json_extract(b.document, '$.role') = 'admin'";

$rows = $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Lepas alias bila selesai
$db->connection->exec("DETACH DATABASE base_alias");
```

3. Contoh routing shard sederhana

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

4. Bulk load dengan `PRAGMA synchronous=OFF` sementara

```php
$db = $client->selectDB('ecommerce');
$db->connection->exec("PRAGMA synchronous=OFF");
// jalankan batch insert besar
$coll->insert($bigBatch);
$db->connection->exec("PRAGMA synchronous=NORMAL");
```

5. Menjalankan benchmark (contoh)

```bash
php tools/benchmark_disk.php 100000 2000

php tools/benchmark_crossdb.php 100000 2000
```

6. Menggunakan `attachOnce()` (aman: attach + detach otomatis)

```php
$client = new \PocketDB\Client('/path/to/dbs');
$db = $client->selectDB('ecommerce');

$orders = $db->attachOnce('/abs/path/to/base.sqlite', 'base_alias', function($dbInstance, $alias) use ($db) {
  $table = $db->selectCollection('orders')->name;
  $sql = "SELECT a.document AS order_doc, b.document AS user_doc
      FROM {$table} a
      JOIN {$alias}.users b
        ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')";
  return $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
});

foreach ($orders as $row) {
  // proses hasil join order + user
}
```
