````markdown
# Panduan ATTACH & SQL Lintas-Database

Jika Anda memerlukan join pada tingkat database antar-file (untuk performa), gunakan SQLite `ATTACH` untuk memasang database lain ke koneksi saat ini.

Contoh penggunaan

```php
$client = new \PocketDB\Client('/path/to/dbs');
$db = $client->selectDB('ecommerce');

// Lampirkan file DB lain sebagai alias 'base'
$db->connection->exec("ATTACH DATABASE '/abs/path/to/base.sqlite' AS base");

// Sekarang Anda dapat merujuk ke base.users di SQL
$sql = "SELECT a.document AS order_doc, b.document AS user_doc
        FROM {$db->selectCollection('orders')->name} a
        JOIN base.users b
          ON json_extract(a.document,'$.user_id') = json_extract(b.document,'$._id')";

$rows = $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Lepas alias setelah selesai
$db->connection->exec("DETACH DATABASE base");
```

Saran helper

- Disarankan membungkus pola attach/detach dalam fungsi helper agar menghindari bentrok alias dan memastikan `DETACH` selalu dipanggil (menggunakan `try/finally`).

Contoh helper (tingkat aplikasi):

```php
function attachOnce(\PocketDB\Database $db, string $path, string $alias, callable $fn) {
    $db->connection->exec("ATTACH DATABASE " . $db->connection->quote($path) . " AS {$alias}");
    try {
        return $fn($db->connection, $alias);
    } finally {
        $db->connection->exec("DETACH DATABASE {$alias}");
    }
}
```

Helper di library

Library sudah menyediakan metode `attachOnce()` pada `\PocketDB\Database` yang membungkus pola attach/callback/detach secara aman. Contoh penggunaan:

```php
$db = $client->selectDB('ecommerce');
$result = $db->attachOnce('/abs/path/to/base.sqlite', 'base_alias', function($dbConn, $alias) use ($db) {
    $table = $db->selectCollection('orders')->name;
    $sql = "SELECT a.document as order_doc, b.document as user_doc FROM {$table} a JOIN {$alias}.users b ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')";
    return $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
});
```

Catatan

- `ATTACH` hanya mendaftarkan database terlampir pada koneksi tersebut — tidak mempengaruhi koneksi lain.
- Perhatikan path relatif dan izin akses; gunakan path absolut bila memungkinkan.
````
