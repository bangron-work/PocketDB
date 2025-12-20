# PocketDB — Skenario & Arsitektur

Dokument ini menjelaskan berbagai skenario penggunaan PocketDB: multi-database, cross-database queries, dan sharding.

1. Single database per application

---

- Semua koleksi berada di satu file SQLite (mis. `app.sqlite`). Cocok untuk dataset kecil sampai menengah, memudahkan backup dan query lokal.
- Gunakan `Client('/path')` lalu `selectDB('app')`.

2. Multi-database (terisolasi per domain)

---

- Simpan database domain terpisah: `base.sqlite` (user, role), `ecommerce.sqlite` (orders, products).
- Keuntungan: isolasi, backup per-domain, ukuran file lebih kecil.
- Cara akses: `Client('/path')->selectDB('ecommerce')->selectCollection('orders')`.

## Cross-database referential (dua pendekatan)

a) Application-level populate (direkomendasikan untuk kemudahan)

- Gunakan `Collection::populate()` dengan string `db.collection` sebagai target foreign. Contoh:

```php
$client = new \PocketDB\Client('/path/to/dbs');
$orders = $client->selectDB('ecommerce')->selectCollection('orders')->find()->toArray();
$orders = $client->selectDB('ecommerce')->selectCollection('orders')->populate($orders, 'user_id', 'base.users', '_id', 'user');
```

- Kelebihan: aman, tidak memerlukan SQL lanjutan. Kekurangan: biaya jaringan/IO karena query antar-file.

b) SQL-level via `ATTACH` (untuk performa join tertentu)

- ATTACH menempelkan file lain ke koneksi SQLite saat runtime:

```php
$db = $client->selectDB('ecommerce');
$db->connection->exec("ATTACH DATABASE '/path/to/base.sqlite' AS base_alias");
$sql = "SELECT a.document AS order_doc, b.document AS user_doc
        FROM {$db->name} a
        JOIN base_alias.users b
          ON json_extract(a.document,'$.user_id') = json_extract(b.document,'$._id')";
$rows = $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
```

- Kelebihan: join dijalankan di engine SQLite (lebih cepat untuk dataset besar). Kekurangan: perlu manajemen alias dan pewaktu ATTACH/detach.

3. Sharding (horizontal partitioning)

---

- PocketDB tidak menyediakan sharding otomatis. Pilihan: lakukan routing di level `Client`.
- Strategi sederhana: `shard = hash(key) % shardCount`, path file `ecommerce_shard_0001.sqlite`.
- Untuk query global, lakukan scatter-gather: jalankan query di semua shard lalu gabungkan hasil di aplikasi.

Pertimbangan:

- Transaksi lintas-shard tidak atomik.
- Rebalancing membutuhkan alat migrasi (stream copy antar shard).
- Buat index JSON pada tiap shard untuk performa lokal.

4. Bulk load & tuning

---

- Gunakan insert batch besar (multi-row `insert()` yang sudah ada) untuk throughput terbaik.
- Untuk disk-backed bulk load: set `PRAGMA synchronous=OFF` sementara, gunakan `WAL` jika membutuhkan concurrency.
- Setelah bulk load, kembalikan `synchronous=NORMAL` dan jalankan `VACUUM` jika perlu.

5. Backup & maintenance

---

- Backup file per-database (`.sqlite`) — untuk konsistensi quiesce aplikasi or close connections.
- Untuk shard: backup tiap file shard.

6. Observability

---

- Monitor per-database file sizes, I/O, and slow queries. Simpel: kirim hasil benchmark rutin.
