# Best Practices & Operations

Operational and design best practices when using PocketDB in production.

Data layout

- Prefer domain-separated DBs for isolation (e.g., `base.sqlite` for authentication, `ecommerce.sqlite` for orders).
- Use per-tenant or per-region DBs if your access patterns are naturally partitioned.

Indexes

- Use `Database::createJsonIndex()` for fields used in equality or sorting.

Backups

- Back up `.sqlite` files. To ensure file consistency close the connection (`Database::close()`) or quiesce the application.

Bulk operations

- Use large batches and WAL. Consider temporarily lowering `synchronous` when importing large amounts of data.

Monitoring

- Monitor file sizes, I/O, latency per operation, and slow queries. Track number of documents per-collection per-shard.

Security

- Restrict filesystem access to DB files. Use application-level authorization for sensitive fields.

Testing

- Use the included PHPUnit tests as a baseline. Add integration tests for cross-db operations and sharding behaviors.
