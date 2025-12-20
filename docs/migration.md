# Resharding & Migration

This document explains strategies and a minimal tool design to move data between shards or rename databases safely.

Goals

- Move a subset of documents (range or by key) from shard A to shard B with minimal downtime.
- Keep reads and writes correct during migration.

Strategy (dual-write + backfill)

1. Update application to write to both source and target shards (dual-write) for the affected key space.
2. Backfill: scan source shard for all documents matching the migrated key range and copy them to target shard.
3. Switch reads: update routing map so reads go to target shard for migrated keys.
4. Stop dual-write after some validation and eventually remove old documents from source.

Minimal tool sketch (pseudo)

```php
// migrate_range.php --source source_db --target target_db --filter "json_extract(document,'$.tenant') = 'acme'"

$source = $client->selectDB('source_db');
$target = $client->selectDB('target_db');

$rows = $source->selectCollection('coll')->find($criteria)->toArray();
foreach ($rows as $r) {
    $target->selectCollection('coll')->insert(json_decode($r['document'], true));
}
```

Notes

- Ensure `PRAGMA synchronous` and WAL settings are appropriate during bulk copy.
- Use checksums and counts to verify copy integrity.
