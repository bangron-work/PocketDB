# Instalasi

Persyaratan

- PHP 8.0 atau lebih baru
- Ekstensi PHP `pdo_sqlite`
- Composer (untuk pengembangan / menjalankan tes)

Langkah instalasi

1. Clone repositori:

```bash
git clone <repo> pocketdb
cd pocketdb
```

2. Pasang dependensi:

```bash
composer install
```

3. Pastikan ekstensi `pdo_sqlite` aktif di `php.ini` CLI Anda.

Menjalankan tes

```bash
vendor/bin/phpunit -c phpunit.xml
```

Catatan (Windows)

- Jika Anda mengalami error penguncian file saat pengujian mencoba menghapus file `.sqlite`, pastikan koneksi sudah ditutup (`Database::close()` dan `Client::close()`), dan tutup aplikasi/IDE yang mungkin membuka file database.
