# PocketDB Documentation

PocketDB adalah database NoSQL ringan yang dibangun di atas SQLite, menawarkan API yang mirip dengan MongoDB untuk PHP. PocketDB menyimpan data dalam format JSON dan menyediakan fitur-fitur seperti indexing, encryption, dan query yang powerful.

## Repository Information

**GitHub Repository:** https://github.com/bangron-work/PocketDB

**Installation via Composer:**

```bash
composer require bangron-work/pocketdb
```

## Struktur Proyek

```
PocketDB/
├── src/
│   ├── Client.php          # Kelas utama untuk mengelola database
│   ├── Database.php        # Kelas untuk mengelola database dan koleksi
│   ├── Collection.php      # Kelas untuk operasi pada koleksi
│   └── Cursor.php          # Kelas untuk hasil query dan iterasi
└── docs/
    └── index.md           # Dokumentasi lengkap
```

## Fitur Utama

- **Storage Format**: Data disimpan dalam format JSON di SQLite
- **MongoDB-like API**: API yang familiar bagi pengguna MongoDB
- **Encryption**: Mendukung enkripsi AES-256-CBC untuk data sensitif
- **Indexing**: JSON indexing untuk query yang lebih cepat
- **Searchable Fields**: Fields yang dapat di-index untuk pencarian cepat
- **Hooks System**: Event-driven programming dengan before/after hooks
- **Populate**: Relasi data antar koleksi
- **Memory & Disk**: Mendukung database di memory maupun disk

## Instalasi

### Menggunakan Composer (Direkomendasikan)

```bash
composer require bangron-work/pocketdb
```

### Menggunakan Git Clone

```bash
# Clone repository
git clone https://github.com/bangron-work/PocketDB.git
cd PocketDB

# Include dalam project PHP Anda
require_once 'src/Client.php';
```

## Quick Start

```php
<?php
require_once 'src/Client.php';

use PocketDB\Client;

// Buat client baru
$client = new Client('./data'); // atau ':memory:' untuk database di memory

// Pilih atau buat database
$db = $client->myDatabase;

// Pilih atau buat koleksi
$users = $db->users;

// Insert data
$users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Query data
$user = $users->findOne(['email' => 'john@example.com']);
print_r($user);

// Update data
$users->update(['email' => 'john@example.com'], ['age' => 31]);

// Delete data
$users->remove(['email' => 'john@example.com']);
?>
```

## Komponen Utama

1. **Client**: Titik masuk utama untuk mengelola database
2. **Database**: Container untuk koleksi-koleksi
3. **Collection**: Struktur data yang menyimpan dokumen-dokumen JSON
4. **Cursor**: Hasil query yang dapat di-iterasi

## Dokumentasi Lengkap

- [Client Class](client.md) - API lengkap untuk Client
- [Database Class](database.md) - API lengkap untuk Database
- [Collection Class](collection.md) - API lengkap untuk Collection
- [Cursor Class](cursor.md) - API lengkap untuk Cursor
- [Utilities](utilities.md) - Helper functions dan utilities
- [Advanced Usage](advanced.md) - Fitur-fitur advanced dan best practices

## Kontribusi

Silakan fork dan submit pull request untuk kontribusi. Pastikan untuk menjalankan test sebelum submit.

## Lisensi

[MIT License](LICENSE)
