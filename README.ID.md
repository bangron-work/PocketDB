# PocketDB

**PocketDB** adalah library database NoSQL ringan berbasis PHP yang dibangun di atas mesin **SQLite**. Library ini menggabungkan fleksibilitas penyimpanan dokumen JSON dengan keandalan, kecepatan, dan stabilitas SQLite.

Sangat cocok untuk aplikasi yang membutuhkan API bergaya MongoDB tanpa perlu repot mengelola server database terpisah.

## 🚀 Fitur Utama

- **API NoSQL**: Insert, update, dan query dokumen JSON menggunakan sintaks mirip MongoDB.
- **Serverless**: Berjalan langsung di atas SQLite (mendukung mode file fisik maupun `:memory:`).
- **Event-Driven Hooks**: Sistem Hook `on('beforeInsert', ...)` yang kuat untuk validasi dan logika bisnis.
- **🔒 Enkripsi**: Enkripsi AES-256-CBC bawaan untuk keamanan dokumen.
- **Pencarian Data Terenkripsi**: Kemampuan untuk mengindeks dan mencari field tertentu meskipun dokumen utamanya terenkripsi.
- **Relasi Data**: Helper `populate` untuk menggabungkan data antar koleksi atau database yang berbeda.
- **ID yang Fleksibel**: Mendukung UUID v4, Auto-Increment dengan Prefix (contoh: `ORD-001`), atau ID Manual.
- **Tanpa Konfigurasi**: Cukup _require_ dan jalankan.

## 📦 Instalasi

```bash
composer require bangron-work/pocketdb

```

## ⚡ Memulai Cepat

### Penggunaan Dasar

```php
use PocketDB\Client;

// Inisialisasi (akan membuat 'database_saya.sqlite' jika belum ada)
$client = new Client(__DIR__ . '/data');
$db = $client->database_saya;

// Mengakses koleksi (tabel)
$users = $db->users;

// 1. Simpan dokumen
$userId = $users->insert([
    'username' => 'budi_santoso',
    'email'    => 'budi@example.com',
    'profile'  => [
        'age'  => 28,
        'city' => 'Jakarta'
    ]
]);

// 2. Cari dokumen
$user = $users->findOne(['username' => 'budi_santoso']);

// 3. Update dokumen (menggunakan dot notation untuk nested data)
$users->update(
    ['_id' => $userId],
    ['profile.city' => 'Bandung']
);

// 4. Hapus
$users->remove(['_id' => $userId]);

```

---

## 🔥 Fitur Lanjutan

### 1. Event Hooks (`on`)

PocketDB memungkinkan Anda untuk menginterupsi operasi database. Fitur ini sangat berguna untuk validasi, mutasi data, atau memicu aksi lain (seperti logging atau notifikasi).

Event yang didukung: `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeRemove`, `afterRemove`.

```php
// Contoh: Validasi stok sebelum pesanan dibuat
$db->orders->on('beforeInsert', function (&$order) use ($db) {
    $product = $db->products->findOne(['_id' => $order['product_id']]);

    // Validasi
    if ($product['stock'] < $order['qty']) {
        return false; // Batalkan proses insert
    }

    // Mutasi: Hitung total harga otomatis
    $order['total_price'] = $product['price'] * $order['qty'];
    $order['created_at']  = date('Y-m-d H:i:s');

    return $order; // Kembalikan data yang sudah dimodifikasi
});

// Contoh: Catat keuangan setelah pesanan berhasil disimpan
$db->orders->on('afterInsert', function ($order) use ($db) {
    $db->ledger->insert([
        'description' => 'Penjualan ' . $order['_id'],
        'amount'      => $order['total_price'],
        'type'        => 'CREDIT'
    ]);
});

```

### 2. Enkripsi & Privasi

Anda dapat mengenkripsi seluruh isi dokumen (kecuali `_id`). Anda juga bisa mengatur field tertentu agar tetap bisa dicari (_Searchable_) meskipun terenkripsi.

```php
// 1. Inisialisasi dengan kunci enkripsi
$db = $client->selectDB('secure_db', [
    'encryption_key' => 'kunci-rahasia-32-karakter-anda!!'
]);

// 2. Konfigurasi field yang bisa dicari
// 'email' di-hash (pencarian aman, hanya bisa exact match)
// 'role' disimpan plain text (bisa dicari dengan LIKE/Regex)
$db->users->setSearchableFields([
    'email' => ['hash' => true],
    'role'  => ['hash' => false]
]);

// 3. Insert (Data tersimpan terenkripsi di disk)
$db->users->insert(['email' => 'bos@perusahaan.com', 'role' => 'admin', 'gaji' => 50000000]);

// 4. Anda tetap bisa mencarinya!
$user = $db->users->findOne(['email' => 'bos@perusahaan.com']);
// Output: Dokumen otomatis didekripsi saat diambil

```

### 3. Relasi (Populate)

Menggabungkan data dari koleksi berbeda (mirip SQL JOIN).

```php
// Anggap kita punya 'orders' yang memiliki field 'customer_id'
$orders = $db->orders->find()->toArray();

// Populate data user ke dalam field 'customer_details'
$results = $db->orders->populate(
    $orders,             // Data sumber
    'customer_id',       // Foreign key di 'orders'
    'users',             // Nama koleksi target
    '_id',               // Primary key di 'users'
    'customer_details'   // Nama field output
);

```

### 4. Query & Cursor

Gunakan API berantai (_chainable_) untuk query yang lebih kompleks.

```php
$results = $db->products->find([
        'price' => ['$gte' => 1000000],        // Harga >= 1 Juta
        'tags'  => ['$in' => ['promo', 'new']] // Tag ada di dalam array
    ])
    ->sort(['price' => -1]) // Urutkan Menurun (DESC)
    ->limit(10)
    ->skip(0)
    ->toArray();

```

**Operator yang Didukung:**
`$eq` (sama dengan), `$gt` (lebih besar), `$gte` (lebih besar sama dengan), `$lt` (lebih kecil), `$lte` (lebih kecil sama dengan), `$in` (di dalam array), `$nin` (tidak di dalam array), `$exists` (cek keberadaan field), `$regex` (pencarian pola), `$fuzzy` (pencarian teks mirip).

### 5. Manajemen ID

Kontrol bagaimana `_id` dihasilkan untuk setiap koleksi.

```php
// Mode: Auto (Default) -> UUID v4
$db->logs->setIdModeAuto();

// Mode: Prefix -> 'TRX-000001', 'TRX-000002'
$db->orders->setIdModePrefix('TRX-');

// Mode: Manual -> Anda wajib menyertakan '_id' sendiri
$db->users->setIdModeManual();

```

## 🛠 Arsitektur

- **Client**: Mengelola direktori dan file-file database.
- **Database**: Wrapper untuk koneksi `PDO` (SQLite), menangani transaksi dan kunci enkripsi.
- **Collection**: Menangani logika dokumen, hooks, pembuatan ID, dan penyusunan query.
- **Cursor**: Menangani iterasi data, pengurutan, pagination, dan _lazy loading_.

## 📄 Lisensi

MIT License. Lihat file [LICENSE](https://www.google.com/search?q=LICENSE) untuk informasi lebih lanjut.

---

_Dibuat dengan ❤️ menggunakan PHP dan SQLite._
