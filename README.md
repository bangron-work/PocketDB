# PocketDB

[![CI](https://github.com/bangron-work/PocketDB/actions/workflows/phpunit.yml/badge.svg)](https://github.com/bangron-work/PocketDB/actions)

CI badge links to `bangron-work/PocketDB`.

PocketDB adalah library PHP yang menyediakan antarmuka mirip MongoDB di atas SQLite. Ringan dan cocok untuk aplikasi kecil yang membutuhkan penyimpanan dokumen JSON tanpa menginstal server database tambahan.

**Fitur utama**

- API mirip MongoDB: `Client`, `Database`, `Collection`, `Cursor`
- Penyimpanan dokumen JSON di tabel SQLite
- Query berbasis array (operator seperti `$gt`, `$lt`, `$in`, `$exists`, dsb.)
- Dukungan mode ID: `auto` (UUID v4), `manual`, dan `prefix`
- Unit tests menggunakan PHPUnit

**Persyaratan**

- PHP >= 8.0
- ekstensi `pdo_sqlite`
- (direkomendasikan) ekstensi `zip` untuk Composer

Instalasi

1. Clone repo atau tambahkan ke proyek via Composer lokal
2. Install dependensi:

```powershell
composer install
```

Jika Composer gagal mendownload paket (Windows):

- Aktifkan `extension=zip` di `php.ini` CLI
- Pastikan `git` tersedia di PATH
- Alternatif: install `7-Zip` dan tambahkan ke PATH

Menjalankan tes

```powershell
vendor\bin\phpunit -c phpunit.xml
```

Jika Anda mengalami error penghapusan file `.sqlite` pada Windows, pastikan koneksi PDO ditutup sebelum file dihapus — library sudah menutup koneksi pada `Database::close()` dan `Client::close()`, tapi environment (IDE/process lain) bisa mengunci file.

Contoh penggunaan singkat

```php
$client = new PocketDB\Client('path/to/databases');
$db = $client->selectDB('mydb');
$col = $db->selectCollection('users');

// insert
$id = $col->insert(['name' => 'Alice', 'age' => 30]);

// find
$user = $col->findOne(['name' => 'Alice']);

// update
$col->update(['_id' => $id], ['email' => 'alice@example.com']);

// remove
$col->remove(['age' => ['$lt' => 18]]);

// close
$client->close();
```

API ringkas

- `PocketDB\Client($path, $options=[])` — kelola beberapa database (folder atau `:memory:`)
- `PocketDB\Database($path, $options=[])` — koneksi SQLite, create/drop/select collection
- `PocketDB\Collection` — `insert`, `insertMany`, `find`, `findOne`, `update`, `remove`, `count`, `save`, `drop`, `renameCollection`
- `PocketDB\Cursor` — iterator, `limit()`, `skip()`, `sort()`, `toArray()`

CI (GitHub Actions)

Saya sarankan menambahkan workflow yang menjalankan `composer install` dan `phpunit` pada `push`/`pull_request`. Mau saya buatkan file `.github/workflows/phpunit.yml` untuk Anda?

Kontribusi

Buka isu atau pull request jika Anda menemukan bug atau ingin fitur baru.

Lisensi

Sesuaikan lisensi sesuai preferensi Anda (mis. MIT).

---

Additional Documentation

PocketDB supports multiple deployment and integration scenarios. See `docs/` for detailed guides:

- `docs/scenarios.md` — cross-database usage, ATTACH helper, durability tradeoffs.
- `docs/usage_examples.md` — code examples: cross-DB populate, ATTACH+SQL join, sharding routing sample.
- `tools/benchmark_disk.php`, `tools/benchmark_crossdb.php` — benchmarking tools used during development.

Quick pointers

- Cross-database (application-level): use `Collection::populate()` with `db.collection` (e.g. `base.users`) to lookup across isolated DB files.
- Cross-database (SQL-level): use `ATTACH DATABASE 'path' AS alias` via `$database->connection->exec()` and write SQL joining `alias.table`.
- Sharding: not automatic — implement routing in `Client` (map key -> shard file) and use scatter-gather for global queries.
- Bulk ingest optimization: batch inserts and consider `PRAGMA synchronous=OFF` temporarily for fastest disk throughput (restore afterward).

If you want, I can add a small `Database::attach()` helper and a `Client::selectShardForKey()` example to the library.

Full documentation is available in the `docs/` folder. See `docs/index.md` for a complete table of contents and detailed guides covering installation, API reference, cross-database patterns, sharding, resharding/migration, benchmarks, and troubleshooting.
