# Sharding Guide

This document explains how to shard PocketDB horizontally (application-level sharding). PocketDB does not provide automatic sharding — the routing layer is implemented by your application or a thin client helper.

Design choices

- Shard key: choose a stable key (customer_id, tenant_id, user_id) used to map to a shard.
- Shard naming: use deterministic filenames, e.g. `ecommerce_shard_0001.sqlite`.
- Mapping function: simple modulo (`crc32(key) % shardCount`) or consistent hashing for elastic scaling.

Routing example (simple)

```php
function selectShardForKey(\PocketDB\Client $client, string $baseName, string $key, int $shardCount = 4) {
    $hash = crc32((string)$key);
    $shard = $hash % $shardCount;
    $name = sprintf('%s_shard_%04d', $baseName, $shard);
    return $client->selectDB($name);
}
```

Scatter-gather

- For global queries (no shard key), the application should query each shard and merge results. Use parallelism where appropriate.

Transactions & consistency

- Transactions are limited to a single shard/connection. Cross-shard transactions are not atomic by default.
- If you need strong cross-shard consistency, implement a two-phase commit at the application layer (complex).

Resharding and migration

- Rebalancing requires copying document subsets from source shards to destination shards and updating routing maps.
- Use a temporary dual-write mode to write to old+new shards during migration, then backfill reads and switch.

Indexing

- Create JSON indexes on each shard for frequently queried fields using `Database::createJsonIndex()`.

Operational tips

- Monitor per-shard file sizes and I/O. Automate creating new shards when a size threshold is reached.
- Backups are per-file; you can snapshot each shard independently.
