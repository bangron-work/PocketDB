# Benchmarks & Performance Tuning

This document summarises benchmarking tools and tuning tips.

Included tools

- `tools/profile.php` — lightweight profiler for in-memory quick tests.
- `tools/benchmark_disk.php` — disk vs memory benchmark with batch size parameter.
- `tools/benchmark_crossdb.php` — compare PRAGMA configurations across disk setups.

Key tuning tips

- Bulk ingest: use large batch sizes (10k+ when possible) and wrap inserts in transactions.
- For fastest ingest to disk temporarily set `PRAGMA synchronous=OFF` and restore to `NORMAL` afterwards.
- Create JSON indexes for frequent equality/sort fields using `Database::createJsonIndex()`.
- Prefer simple equality queries that can be converted to `json_extract(...) = value` — these can use indexes.

Profiling

- For CPU-level profiling use Xdebug/pcov and generate cachegrind or callgrind output, then analyze with `kcachegrind` or similar.
