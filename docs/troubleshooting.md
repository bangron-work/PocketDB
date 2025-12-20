# Troubleshooting

Common issues and how to resolve them.

1. Windows: cannot unlink `.sqlite` (file in use)

- Ensure `Database::close()` and `Client::close()` have been called to drop PDO connections.
- Run `gc_collect_cycles()` before deleting files if necessary.
- Ensure other processes (editors, tools, antivirus) are not holding the file open.

2. Memory exhausted on tests

- Review tests that create large in-memory datasets. Use batched operations or smaller fixtures.
- Use `Cursor::toArray()` sparingly on large result sets; iterate with `each()` or `foreach` to stream.

3. Slow queries when using PHP criteria

- PHP-registered criteria are executed by SQLite per-row via the `document_criteria` callback and are expensive for large scans. Where possible, use simple equality criteria so queries are translated to `json_extract(...)` SQL and can use indexes.

4. ATTACH errors / path issues

- Use absolute paths when calling `ATTACH DATABASE '/abs/path/file.sqlite' AS alias` and ensure the running process has filesystem permissions.

5. Concurrency / WAL

- Use WAL (`PRAGMA journal_mode=WAL`) for concurrency. Be aware of the need to checkpoint and the existence of `-wal` and `-shm` files.
