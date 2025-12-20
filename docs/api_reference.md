# API Reference

This file documents the public API surface of PocketDB.

## Client

- `new \PocketDB\Client(string $path, array $options = [])` — client managing multiple databases. If `$path` is `:memory:` the client works in-memory only.
- `selectDB(string $name): Database` — open or create database file at `{path}/{name}.sqlite`.
- `selectCollection(string $db, string $collection): Collection` — convenience to select a collection in a named DB.
- `listDBs(): array` — lists available databases in the path (disk mode).
- `close()` — close all connections managed by the client.

## Database

- `new \PocketDB\Database(string $path = ':memory:', array $options = [])` — opens a PDO SQLite connection and registers helper functions.
- `selectCollection(string $name): Collection` — create or return collection wrapper.
- `createCollection(string $name)` / `dropCollection(string $name)`
- `getCollectionNames(): array` — list tables
- `createJsonIndex(string $collection, string $field, ?string $indexName = null)` — create an index on `json_extract(document,'$.field')`.
- `registerCriteriaFunction($criteria): ?string` — register a PHP callable or array criteria; returns an id used by `document_criteria` SQLite callback.
- `callCriteriaFunction(string $id, $document): bool` — invoke a previously registered criteria closure.
- `staticCallCriteria(string $id, $document): bool` — internal entrypoint used by SQLite extension.
- `close()` / `vacuum()` / `drop()`
- `attach(string $path, string $alias): bool` — attach another SQLite file to this connection using `ATTACH DATABASE 'path' AS alias`.
- `detach(string $alias): bool` — detach an attached database alias using `DETACH DATABASE alias`.
- `attachOnce(string $path, string $alias, callable $callback)` — convenience helper: attaches the database, runs the callback, and detaches in a finally block. The callback receives the current `Database` and the alias. Throws on attach failure or rethrows callback exceptions after ensuring detach.

## Collection

- `insert(array $doc)` — insert a single doc or array of docs. For arrays, the method uses a transaction.
- `insertMany(array $docs)` — alias for batch insert.
- `save(array $doc, bool $create = false)` — upsert style save using `_id`.
- `update($criteria, array $data, bool $merge=true): int` — update matching documents.
- `remove($criteria): int` — remove matching documents.
- `find($criteria = null, $projection = null): Cursor` — returns a `Cursor` object. Simple equality criteria arrays will be converted to native SQL `json_extract` WHERE clauses.
- `findOne($criteria = null, $projection = null): ?array` — convenience wrapper.
- `count($criteria = null): int` — count matching documents (delegates to `Cursor::count`).
- `on(string $event, callable $fn)` / `off(string $event, callable $fn = null)` — hook events: `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeRemove`, `afterRemove`.
- `populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null)` — populates references where `$foreign` may be `collection` or `db.collection`.

## Cursor

- `limit(int $n)` / `skip(int $n)` / `sort(array $spec)`
- implements PHP `Iterator` methods: `rewind()`, `current()`, `key()`, `next()`, `valid()`.
- `toArray()` / `each(callable $fn)` / `count(): int`

## Helpers & Utilities

- `UtilArrayQuery` — internal class to evaluate array-based criteria in PHP (`$gt`, `$lt`, `$in`, `$regex`, etc.).
- Global helper functions: `createMongoDbLikeId()`, `fuzzy_search()` and others.

## Notes

- For large result-sets, prefer using native SQL paths (simple equality criteria) so SQLite can use indexes created with `createJsonIndex()`.
- The library registers an SQLite function `document_criteria(id, document)` that delegates to PHP-registered criteria closures. For very large scans this causes PHP call-per-row overhead.
