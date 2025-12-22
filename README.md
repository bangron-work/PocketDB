# PocketDB

[![Latest Version](https://img.shields.io/packagist/v/bangron-work/pocketdb.svg?style=flat-square)](https://packagist.org/packages/bangron-work/pocketdb)
[![PHP Version](https://img.shields.io/packagist/php-v/bangron-work/pocketdb.svg?style=flat-square)](https://php.net/)
[![License](https://img.shields.io/github/license/bangron-work/pocketdb.svg?style=flat-square)](https://github.com/bangron-work/pocketdb/blob/main/LICENSE)

PocketDB adalah pustaka PHP ringan yang menghadirkan API mirip MongoDB di atas SQLite. Library ini menyimpan dokumen JSON dalam tabel SQLite dan menyediakan fungsi query serta utilitas untuk pengembangan aplikasi kecil hingga menengah.

## 🚀 Fitur Utama

- **API Sederhana**: Mirip MongoDB dengan `Client`, `Database`, `Collection`, dan `Cursor`
- **Dokumen JSON**: Penyimpanan fleksibel dengan dukungan schema-less
- **Kinerja Tinggi**: Menggunakan SQLite sebagai backend yang cepat dan andal
- **Query Lengkap**: Dukungan operator seperti `$gt`, `$lt`, `$in`, `$exists`, dll.
- **Indeks JSON**: Optimasi query dengan indeks pada field JSON
- **Transaksi**: Dukungan penuh untuk operasi atomik
- **Hooks & Events**: Fleksibel dengan siklus hidup dokumen
- **Multi-database**: Dukungan untuk beberapa database dalam satu aplikasi

## 📦 Persyaratan

- PHP 8.0 atau lebih tinggi
- Ekstensi `pdo_sqlite` aktif
- Composer untuk manajemen dependensi

## 🔧 Instalasi

```bash
composer require bangron-work/pocketdb
```

## 🚀 Memulai Cepat

### Koneksi ke Database

```php
require 'vendor/autoload.php';

use PocketDB\Client;

// Buat instance client dengan direktori penyimpanan
$client = new Client(__DIR__.'/data');

// Pilih database (akan dibuat jika belum ada)
$db = $client->selectDB('aplikasi_saya');

// Pilih koleksi (seperti tabel)
$produk = $db->selectCollection('produk');
```

### Operasi Dasar

#### Menyisipkan Dokumen

```php
// Menyisipkan satu dokumen
$id = $produk->insert([
    'nama' => 'Laptop Gaming',
    'harga' => 12000000,
    'stok' => 15,
    'spesifikasi' => [
        'prosesor' => 'Intel i7',
        'ram' => '16GB',
        'storage' => '512GB SSD'
    ],
    'tags' => ['elektronik', 'laptop', 'gaming']
]);

echo "ID dokumen yang disimpan: $id";
```

#### Mencari Dokumen

```php
// Mencari satu dokumen
$laptop = $produk->findOne(['nama' => 'Laptop Gaming']);

// Mencari banyak dokumen dengan filter
$produkMahal = $produk->find([
    'harga' => ['$gt' => 10000000]
])->toArray();

// Pencarian teks (case-insensitive)
$hasilPencarian = $produk->find([
    'nama' => ['$regex' => 'gaming', '$options' => 'i']
]);
```

#### Memperbarui Dokumen

```php
// Update satu dokumen
$produk->update(
    ['_id' => $id],
    ['$set' => ['stok' => 10]]
);

// Update banyak dokumen
$produk->update(
    ['kategori' => 'elektronik'],
    ['$inc' => ['harga' => 500000]], // Naikkan harga 500.000
    ['multiple' => true]
);
```

#### Menghapus Dokumen

```php
// Hapus satu dokumen
$produk->remove(['_id' => $id]);

// Hapus banyak dokumen
$produk->remove(['stok' => 0]);
```

## 📚 Dokumentasi Lengkap

Lihat [dokumentasi lengkap](docs/index.md) untuk informasi lebih lanjut tentang:

- [Panduan Penggunaan](docs/guides/getting-started.md)
- [Referensi API](docs/api/collection.md)
- [Indeks dan Optimasi](docs/guides/indexes.md)
- [Transaksi](docs/guides/transactions.md)
- [Integrasi Framework](docs/integrations/index.md)
- [Troubleshooting](docs/troubleshooting/common-issues.md)

## 🔍 Contoh Lengkap

Kunjungi direktori [examples](examples/) untuk contoh kode yang dapat dijalankan:

- [Aplikasi Blog Sederhana](examples/blog/)
- [REST API dengan Slim Framework](examples/rest-api/)
- [Integrasi dengan Laravel](examples/laravel/)

## 🛠 Pengembangan

### Menjalankan Pengujian

```bash
composer test
```

### Kontribusi

Kontribusi sangat diterima! Silakan buat issue atau pull request.

## 📄 Lisensi

PocketDB dilisensikan di bawah [MIT License](LICENSE).

- Populate (mirip join pada level aplikasi):

```php
$orders = $db->selectCollection('orders');
$ordersWithUser = $orders->find()->populate('user_id', $users)->toArray();
```

- Membuat index JSON untuk mempercepat query pada field tertentu:

```php
$db->createJsonIndex('users', 'age');
$db->createJsonIndex('users', 'profile.level');
```

Catatan: index dibuat dengan `json_extract(document, '$.field')` sehingga query yang memakai field tersebut dapat menggunakan index.

## Operator Query yang Didukung

- Persamaan: `['name' => 'Alice']`
- Pembanding: `['age' => ['$gt' => 20]]`, `$gte`, `$lt`, `$lte`
- Keanggotaan: `['tag' => ['$in' => ['red', 'blue']]]` dan `$nin`
- Eksistensi: `['field' => ['$exists' => true]]`

Jika sebuah criteria tidak bisa diterjemahkan ke SQL JSON, PocketDB akan menggunakan fungsi callback PHP (`document_criteria`) untuk menilai tiap dokumen — ini lebih fleksibel tapi lebih lambat (karena `json_decode` per baris).

## Tips Performa dan Best Practices

- Gunakan batch insert (`insert($batch)`) untuk throughput tinggi.
- Buat index pada field yang sering dicari atau dipakai sebagai kondisi.
- Hindari meletakkan field yang sangat sering diupdate di dalam JSON tunggal jika memungkinkan — pertimbangkan menambah kolom SQL biasa (mis. `last_updated`) di tabel untuk menghindari re-encode JSON setiap update.
- Jika Anda butuh banyak penulis paralel (high write concurrency), evaluasi apakah SQLite adalah pilihan tepat; untuk sangat banyak penulis, RDBMS server (Postgres/MySQL) lebih cocok.

### Optimasi Save (Upsert)

Sejak pembaruan terakhir, method `save()` diimplementasikan untuk menggunakan pencarian native SQL pada field `_id` sehingga operasi upsert tidak memanggil callback PHP per baris.

- Sebelumnya: `save()` menggunakan `document_criteria(...)` yang memicu decoding JSON dan evaluasi PHP untuk setiap baris — ini sangat mahal pada tabel besar.
- Sekarang: `save()` memakai subquery seperti `SELECT id FROM <table> WHERE json_extract(document, '$._id') = <value> LIMIT 1` sehingga SQLite dapat menggunakan index JSON jika dibuat.

Rekomendasi:

- Buat index JSON pada `_id` (atau field yang Anda gunakan untuk upsert) sebelum beban produksi:

```php
$db->createJsonIndex('users', '_id');
```

- Pastikan Anda memahami tipe `_id` yang digunakan (string vs numerik). Library akan mencoba menangani literal numerik tanpa quoting, dan meng-quote nilai string.

Keuntungan:

- Operasi `save()` menjadi jauh lebih cepat (menghindari callback PHP per-baris).
- Jika index dibuat, pencarian untuk upsert dan `find(['_id' => ...])` menjadi O(log n) pada level database.

Catatan keamanan:

- Nama field JSON di-build menjadi path `$.field` untuk `json_extract`. Jika nama field datang dari input yang tidak tepercaya, pertimbangkan untuk memvalidasi atau menggunakan whitelist nama field untuk mencegah injection pada path JSON. Saat ini library melakukan escaping sederhana untuk kutipan, tetapi validasi tambahan direkomendasikan untuk penggunaan publik-facing API.

## Concurrency

- PocketDB mengaktifkan WAL (`PRAGMA journal_mode = WAL`) untuk meningkatkan concurrency baca/tulis.
- Namun, beban update berat oleh banyak writer paralel tetap akan menimbulkan penantian pada level SQLite.

## Debugging & Troubleshooting

- Jika Anda melihat query lambat, cek apakah criteria diterjemahkan ke SQL (lihat `_canTranslateToJsonWhere` di `src/Collection.php`). Jika tidak, pertimbangkan menggunakan field yang dapat diindeks.
- Jika tabel tidak ada error: pastikan `selectCollection()` dipanggil sebelum membuat indeks, atau gunakan `createCollection()` terlebih dahulu.

## API Ringkas

- `Client($path, $options=[])` — path folder database atau `:memory:` untuk in-memory.
- `Database` — `attach()`, `detach()`, `createCollection()`, `createJsonIndex()`, `registerCriteriaFunction()`.
- `Collection` — `insert()`, `insertMany()`, `find()`, `findOne()`, `update()`, `remove()`, `count()`, `save()`, `drop()`, `createIndex()`.
- `Cursor` — `limit()`, `skip()`, `sort()`, `populate()`, `toArray()`, `count()`.

## Contoh Praktis: Index + Query Cepat

```php
$db->createJsonIndex('users', 'email');
$fast = $users->find(['email' => 'alice@example.com'])->toArray();
```

## Benchmark & Tools

- Ada skrip benchmark di `tools/stress_benchmark.php` untuk mengukur throughput insert, query, update, dan index creation. Gunakan dengan hati-hati (dapat membuat file besar pada disk).

## Contributing

- Silakan lihat `CONTRIBUTING.md` untuk panduan kontribusi.

## Lisensi

- Lihat file `LICENSE` untuk detail lisensi.

## Butuh bantuan lebih lanjut?

- Jika Anda mau, saya dapat menambahkan:
  - Dokumentasi contoh lengkap (folder `examples/`) dengan contoh aplikasi kecil.
  - Output benchmark otomatis (`results.json`) untuk perbandingan.
  - Panduan migrasi dari MongoDB-lite ke PocketDB.

Selamat mencoba PocketDB — beri tahu saya jika Anda mau saya siapkan contoh proyek kecil agar bisa langsung dijalankan.
