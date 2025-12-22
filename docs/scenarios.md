# PocketDB — Skenario & Arsitektur

Dokumen ini menjelaskan skenario penggunaan PocketDB: single DB, multi-DB, cross-database patterns, sharding, dan tuning.

1. Single database per aplikasi

- Semua koleksi disimpan dalam satu file SQLite (mis. `app.sqlite`). Cocok untuk dataset kecil-menengah; memudahkan backup dan operasi lokal.

2. Multi-database (terisolasi per domain)

- Simpan database per-domain seperti `base.sqlite` (user), `ecommerce.sqlite` (orders). Keuntungan: isolasi data, ukuran file lebih kecil, backup per-domain.
- Akses via `Client('/path')->selectDB('ecommerce')->selectCollection('orders')`.

## Cross-database referential — dua pendekatan

a) Populate di tingkat aplikasi (direkomendasikan untuk kemudahan)

- Gunakan `Cursor::populate()`/`populateMany()` untuk mengisi referensi dari koleksi lain. Contoh:

```php
$client = new \PocketDB\Client('/path/to/dbs');
$orders = $client->selectDB('ecommerce')->selectCollection('orders')->find()->populate('user_id', $client->selectDB('base')->selectCollection('users'))->toArray();
```

- Kelebihan: sederhana dan aman; tidak memerlukan SQL tingkat lanjut. Kekurangan: lebih banyak I/O jika koleksi terpisah.

b) SQL-level via `ATTACH` (untuk performa)

- Lampirkan file lain ke koneksi SQLite dan jalankan JOIN di engine SQLite:

```php
$db = $client->selectDB('ecommerce');
$db->connection->exec("ATTACH DATABASE '/path/to/base.sqlite' AS base_alias");
$sql = "SELECT a.document AS order_doc, b.document AS user_doc
        FROM {$db->selectCollection('orders')->name} a
        JOIN base_alias.users b
          ON json_extract(a.document,'$.user_id') = json_extract(b.document,'$._id')";
$rows = $db->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
```

- Kelebihan: performa lebih baik untuk join besar. Kekurangan: perlu manajemen attach/detach dan path.

3. Sharding (partisi horizontal)

- PocketDB tidak menyediakan sharding otomatis; Anda bisa menerapkan routing di `Client` berdasarkan key.
- Strategi sederhana: `shard = crc32(key) % shardCount` dan simpan tiap shard sebagai file terpisah.
- Untuk query lintas-shard lakukan scatter-gather (jalankan query ke semua shard, gabungkan hasil di aplikasi).

Pertimbangan: transaksi lintas-shard tidak atomik; rebalancing butuh proses migrasi; buat index JSON per-shard untuk performa.

4. Bulk load & tuning

- Gunakan batch insert untuk throughput tinggi.
- Untuk kecepatan disk-backed, sementara set `PRAGMA synchronous=OFF` dan gunakan `WAL` untuk concurrency.
- Setelah selesai, kembalikan pengaturan dan jalankan `VACUUM` jika perlu.

5. Backup & pemeliharaan

- Backup file `.sqlite` per-database; pastikan koneksi ditutup saat menggandakan file untuk konsistensi.

6. Observabilitas

- Monitor ukuran file, I/O, dan query lambat; jalankan benchmark berkala.
