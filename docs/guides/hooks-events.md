# Hooks dan Events

## Daftar Isi

- [Pengenalan Hooks](#pengenalan-hooks)
- [Daftar Hooks yang Tersedia](#daftar-hooks-yang-tersedia)
- [Menggunakan Hooks](#menggunakan-hooks)
- [Event System](#event-system)
- [Contoh Penggunaan](#contoh-penggunaan)
- [Best Practice](#best-practice)

## Pengenalan Hooks

Hooks memungkinkan Anda untuk mengeksekusi kode pada titik-titik tertentu dalam siklus hidup dokumen. Ini berguna untuk validasi, transformasi data, atau logging.

## Daftar Hooks yang Tersedia

### Hooks untuk Operasi Insert

- `beforeInsert`: Sebelum dokumen disisipkan
- `afterInsert`: Setelah dokumen berhasil disisipkan

### Hooks untuk Operasi Update

- `beforeUpdate`: Sebelum pembaruan dilakukan
- `afterUpdate`: Setelah pembaruan berhasil

### Hooks untuk Operasi Remove

- `beforeRemove`: Sebelum penghapusan dilakukan
- `afterRemove`: Setelah penghapusan berhasil

## Menggunakan Hooks

### Mendaftarkan Hook

PocketDB menyediakan API sederhana untuk mendaftarkan dan menghapus hook pada koleksi.

Gunakan method `on(string $event, callable $fn)` untuk mendaftarkan hook, dan
`off(string $event, ?callable $fn = null)` untuk menghapus (tanpa parameter kedua akan
menghapus semua listener untuk event tersebut).

Contoh:

```php
// Mendaftarkan hook sebelum insert
$collection->on('beforeInsert', function(array $document) {
    // Validasi atau modifikasi dokumen
    if (empty($document['name'])) {
        throw new \Exception('Nama tidak boleh kosong');
    }

    // Menambahkan timestamp (simpan sebagai integer UNIX time atau string ISO)
    $document['created_at'] = time();

    // Kembalikan dokumen yang sudah dimodifikasi; mengembalikan false akan
    // membatalkan operasi insert untuk dokumen tersebut.
    return $document;
});

// Mendaftarkan hook setelah insert
$collection->on('afterInsert', function(array $document, $id = null) {
    // Log aktivitas (contoh sederhana)
    error_log("Dokumen baru ditambahkan: " . ($id ?? $document['_id'] ?? '(unknown)'));
});
```

### Menghapus Hook

Untuk menghapus listener tertentu, simpan referensi callable dan berikan ke `off()`; jika
Anda memanggil `off()` tanpa argumen kedua, semua listener pada event tersebut akan dihapus.

```php
// Menyimpan referensi callback
$cb = function(array $doc) { /* ... */ };
$collection->on('beforeInsert', $cb);

// Menghapus callback spesifik
$collection->off('beforeInsert', $cb);

// Hapus semua listener sebelumInsert
$collection->off('beforeInsert');
```

## Event System

PocketDB saat ini menyediakan sistem hook pada level `Collection` melalui `on()`/`off()`
seperti didemonstrasikan di atas. Tidak ada event dispatcher publik yang berbeda pada versi
ini; jika Anda memerlukan sistem event yang lebih canggih, Anda dapat mengintegrasikan
library event dispatcher eksternal dan memanggilnya dari dalam hook `afterInsert`/`afterUpdate`.

## Contoh Penggunaan

### Validasi Data

```php
// Validasi sebelum menyimpan
$users->on('beforeInsert', function(array $user) {
    if (!filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Format email tidak valid');
    }

    // Hash password jika ada
    if (isset($user['password'])) {
        $user['password'] = password_hash($user['password'], PASSWORD_DEFAULT);
    }

    return $user;
});
```

### Logging Otomatis

Anda dapat menggunakan hook `afterInsert`, `afterUpdate`, dan `afterRemove` untuk membuat
log audit pada operasi perubahan data. Contoh sederhana:

```php
// Log semua operasi
$auditLog = $db->selectCollection('audit_logs');

$logInsert = function(array $doc, $id = null) use ($auditLog) {
    $auditLog->insert([
        'action' => 'insert',
        'collection' => $doc['_collection'] ?? null,
        'document_id' => $id ?? $doc['_id'] ?? null,
        'timestamp' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
};

$collection->on('afterInsert', $logInsert);
$collection->on('afterUpdate', function($old, $new) use ($auditLog) {
    $auditLog->insert(['action' => 'update', 'document_id' => $new['_id'] ?? null, 'timestamp' => time()]);
});
$collection->on('afterRemove', function($doc) use ($auditLog) {
    $auditLog->insert(['action' => 'remove', 'document_id' => $doc['_id'] ?? null, 'timestamp' => time()]);
});
```

### Soft Delete

Implementasi soft delete menggunakan `beforeRemove` untuk mencegah penghapusan
dan mengganti tindakan dengan update (mis. menambahkan `deleted_at`). Hook `beforeRemove`
dipanggil untuk setiap dokumen yang cocok; jika hook mengembalikan `false`, operasi
hapus pada dokumen tersebut dibatalkan.

```php
// Implementasi soft delete: mark deleted_at dan batalkan penghapusan
$posts->on('beforeRemove', function(array $doc) use ($posts, $currentUserId) {
    $posts->update(['_id' => $doc['_id']], ['$set' => [
        'deleted_at' => time(),
        'deleted_by' => $currentUserId ?? null
    ]]);

    // Kembalikan false untuk mencegah penghapusan fisik
    return false;
});

// Untuk memastikan dokumen yang dihapus tidak muncul, tambahkan filter saat mencari
$posts->on('beforeFind', function($criteria = null) {
    if (!is_array($criteria)) {
        $criteria = [];
    }
    if (!array_key_exists('deleted_at', $criteria)) {
        $criteria['deleted_at'] = ['$exists' => false];
    }
    return $criteria;
});
```

## Best Practice

1. **Gunakan Hooks untuk**

   - Validasi data
   - Transformasi data (mis. hashing password)
   - Menambahkan field otomatis (timestamps, audit info)
   - Logging perubahan / audit trail (via `after*` hooks)

2. **Hindari**

   - Logika bisnis yang sangat kompleks di dalam hooks
   - Operasi I/O berat yang bisa memperlambat operasi database
   - Memanggil operasi yang dapat men-trigger hook lain tanpa kontrol (menghindari rekursi)

3. **Error Handling**

   - Jika hook melempar exception, operasi akan terhenti; pastikan error ditangani dengan pesan yang jelas.
   - Untuk validasi, lebih baik melempar `InvalidArgumentException` atau custom exception yang jelas.

4. **Dokumentasi**

   - Dokumentasikan hook yang didaftarkan di kode, jelaskan tujuannya dan efek sampingnya.

5. **Performa**
   - Hooks dieksekusi sinkron selama operasi CRUD. Untuk tugas yang berat (mis. pengiriman email, reporting), pertimbangkan menambahkan pekerjaan ke antrian/background worker dari dalam hook `after*`.

Dengan menggunakan hooks dan events dengan benar, Anda dapat membuat kode yang lebih bersih, terstruktur, dan mudah dipelihara.
