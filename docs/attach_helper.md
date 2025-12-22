# ATTACH & Cross-Database SQL Guide

When you need database-level joins across files for performance, use SQLite `ATTACH` to mount another database into the current connection.

Example usage

```php
$client = new \PocketDB\Client('/path/to/dbs');
$db = $client->selectDB('ecommerce');

// Attach base.db as alias 'base'
$db->connection->exec("ATTACH DATABASE '/abs/path/to/base.sqlite' AS base");

// Now you can reference base.users in SQL
$sql = "SELECT a.document AS order_doc, b.document AS user_doc
        FROM {$db->name} a
        JOIN base.users b
          ON json_extract(a.document,'$.user_id') = json_extract(b.document,'$._id')";

$rows = $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Detach when finished
$db->connection->exec("DETACH DATABASE base");
```

Helper suggestions

- We recommend wrapping attach/detach in helper functions to avoid alias collisions and ensure `DETACH` is always called (try/finally).

Example helper (application-level):

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

Database helper

The library provides a convenience method `attachOnce()` on `\PocketDB\Database` which wraps the attach/callback/detach pattern safely. Usage:

```php
$db = $client->selectDB('ecommerce');
$result = $db->attachOnce('/abs/path/to/base.sqlite', 'base_alias', function($dbConn, $alias) use ($db) {
    $table = $db->selectCollection('orders')->name;
    $sql = "SELECT a.document as order_doc, b.document as user_doc FROM {$table} a JOIN {$alias}.users b ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')";
    return $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
});
```

Caveats

- ATTACH registers the attached DB on the same connection only — it does not affect other connections.
- Be careful with relative paths and permissions. Use absolute paths when possible.
