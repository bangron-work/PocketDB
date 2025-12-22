# Indeks dan Optimasi Kinerja

## Daftar Isi
- [Membuat Indeks](#membuat-indeks)
- [Indeks pada Field Bertingkat](#indeks-pada-field-bertingkat)
- [Jenis-Jenis Indeks](#jenis-jenis-indeks)
- [Best Practice Indeks](#best-practice-indeks)
- [Memonitor Kinerja Query](#memonitor-kinerja-query)
- [Optimasi Query](#optimasi-query)

## Membuat Indeks

Indeks mempercepat pencarian dengan membuat struktur data tambahan yang memungkinkan database menemukan data lebih cepat tanpa melakukan full table scan.

```php
// Buat indeks pada field 'email'
$users->createIndex('email');

// Dengan nama indeks kustom
$users->createIndex('email', 'idx_email');

// Indeks unik
$users->createIndex('username', 'idx_username', ['unique' => true]);
```

## Indeks pada Field Bertingkat

PocketDB mendukung pembuatan indeks pada field bertingkat (nested fields) menggunakan notasi titik:

```php
// Indeks pada field bertingkat
$users->createIndex('alamat.kota');

// Indeks pada array
$products->createIndex('kategori.$');
```

## Jenis-Jenis Indeks

### 1. Indeks Tunggal
```php
$collection->createIndex('nama');
```

### 2. Indeks Unik
```php
$collection->createIndex('email', 'idx_email', ['unique' => true]);
```

### 3. Indeks Teks (Pencarian Teks)
```php
// Membuat indeks untuk pencarian teks
$collection->createIndex('deskripsi', 'idx_deskripsi_fts', ['type' => 'FTS5']);

// Mencari dengan full-text search
$hasil = $collection->find([
    '$text' => [
        '$search' => 'laptop gaming',
        '$language' => 'id',
        '$caseSensitive' => false,
        '$diacriticSensitive' => false
    ]
]);
```

## Best Practice Indeks

1. **Jangan berlebihan membuat indeks**
   - Setiap indeks memakan ruang disk dan memperlambat operasi tulis
   - Buat indeks hanya untuk field yang sering digunakan dalam query

2. **Gunakan indeks untuk query yang sering digunakan**
   ```php
   // Jika sering mencari berdasarkan email
   $users->createIndex('email');
   ```

3. **Hindari indeks pada field dengan kardinalitas rendah**
   ```php
   // Tidak efektif untuk field dengan sedikit nilai unik
   $users->createIndex('jenis_kelamin');
   ```

4. **Gunakan indeks komposit untuk query dengan banyak kondisi**
   ```php
   // Lebih efisien untuk query dengan banyak kondisi
   $products->createIndex(['kategori', 'harga']);
   ```

## Memonitor Kinerja Query

Gunakan EXPLAIN untuk menganalisis query:

```php
// Dapatkan query plan
$plan = $collection->explain([
    'harga' => ['$gt' => 1000000],
    'stok' => ['$gt' => 0]
]);

// Output query plan
print_r($plan);
```

## Optimasi Query

1. **Gunakan proyeksi untuk mengambil field yang diperlukan**
   ```php
   // Hanya ambil field yang diperlukan
   $users->find(
       ['status' => 'active'],
       ['projection' => ['nama' => 1, 'email' => 1]]
   );
   ```

2. **Batasi jumlah hasil**
   ```php
   // Ambil 10 dokumen pertama
   $users->find([])->limit(10);
   ```

3. **Gunakan paginasi**
   ```php
   $page = 1;
   $perPage = 10;
   $skip = ($page - 1) * $perPage;
   
   $users->find([])
         ->skip($skip)
         ->limit($perPage);
   ```

4. **Hindari penggunaan $where**
   ```php
   // Hindari
   $users->find(['$where' => 'this.age > 18']);
   
   // Lebih baik
   $users->find(['age' => ['$gt' => 18]]);
   ```

Dengan mengikuti panduan ini, Anda dapat mengoptimalkan kinerja aplikasi PocketDB Anda secara signifikan. Selalu uji perubahan indeks dan query di lingkungan pengembangan sebelum menerapkannya ke produksi.
