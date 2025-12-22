PocketDB examples

This folder contains runnable example scripts demonstrating a small cross-database ecommerce scenario.

How to run

1. Install dependencies (from repository root):

```bash
composer install
```

2. Run the example script:

```bash
php examples/run_ecommerce_example.php
```

The script will create three SQLite files under `examples/data/`: `base.sqlite`, `log.sqlite`, `ecommerce.sqlite` and demonstrate attach/join, index creation, insert, upsert, and a sample query.
