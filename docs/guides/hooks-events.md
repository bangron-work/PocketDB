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

```php
// Mendaftarkan hook sebelum insert
$collection->addHook('beforeInsert', function($document) {
    // Validasi atau modifikasi dokumen
    if (empty($document['name'])) {
        throw new \Exception('Nama tidak boleh kosong');
    }
    
    // Menambahkan timestamp
    $document['created_at'] = new \MongoDB\BSON\UTCDateTime();
    
    return $document; // Kembalikan dokumen yang sudah dimodifikasi
});

// Mendaftarkan hook setelah insert
$collection->addHook('afterInsert', function($document) {
    // Log aktivitas
    error_log("Dokumen baru ditambahkan: " . $document['_id']);
    
    // Tidak perlu mengembalikan nilai
});
```

### Menghapus Hook

```php
// Menghapus hook
$hookId = $collection->addHook('beforeInsert', $callback);
$collection->removeHook('beforeInsert', $hookId);
```

## Event System

Selain hooks, PocketDB juga menyediakan event system yang lebih fleksibel untuk menangani berbagai kejadian dalam aplikasi.

### Event yang Tersedia

- `collection.insert` - Dipicu setelah insert berhasil
- `collection.update` - Dipicu setelah update berhasil
- `collection.remove` - Dipicu setelah remove berhasil
- `database.connect` - Dipicu saat koneksi database dibuat
- `database.error` - Dipicu saat terjadi error

### Mendengarkan Event

```php
// Mendengarkan event insert
$dispatcher = $collection->getEventDispatcher();

$dispatcher->addListener('collection.insert', function($event) {
    $document = $event->getDocument();
    $collection = $event->getCollection();
    
    echo "Dokumen baru ditambahkan ke koleksi: " . $collection->getName();
    // Lakukan sesuatu dengan $document
});
```

## Contoh Penggunaan

### Validasi Data

```php
// Validasi sebelum menyimpan
$users->addHook('beforeInsert', function($user) {
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Format email tidak valid');
    }
    
    // Enkripsi password
    if (isset($user['password'])) {
        $user['password'] = password_hash($user['password'], PASSWORD_DEFAULT);
    }
    
    return $user;
});
```

### Logging Otomatis

```php
// Log semua operasi
$auditLog = $db->selectCollection('audit_logs');

$logOperation = function($event) use ($auditLog) {
    $auditLog->insert([
        'action' => $event->getName(),
        'collection' => $event->getCollection()->getName(),
        'document_id' => $event->getDocument()['_id'] ?? null,
        'timestamp' => new \MongoDB\BSON\UTCDateTime(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
};

// Daftarkan untuk semua event
$dispatcher = $db->getEventDispatcher();
$dispatcher->addListener('collection.insert', $logOperation);
$dispatcher->addListener('collection.update', $logOperation);
$dispatcher->addListener('collection.remove', $logOperation);
```

### Soft Delete

```php
// Implementasi soft delete
$posts->addHook('beforeRemove', function($criteria, $options) use ($posts) {
    // Alih-alih menghapus, update field deleted_at
    $posts->update(
        $criteria,
        ['$set' => [
            'deleted_at' => new \MongoDB\BSON\UTCDateTime(),
            'deleted_by' => $currentUserId ?? null
        ]],
        $options
    );
    
    // Kembalikan false untuk membatalkan operasi remove asli
    return false;
});

// Filter dokumen yang tidak terhapus secara default
$posts->addHook('beforeFind', function($criteria) {
    if (!isset($criteria['deleted_at'])) {
        $criteria['deleted_at'] = ['$exists' => false];
    }
    return $criteria;
});
```

## Best Practice

1. **Gunakan Hooks untuk**
   - Validasi data
   - Transformasi data (seperti hashing password)
   - Menambahkan field otomatis (timestamps, pengguna yang membuat/mengubah)
   - Logging perubahan

2. **Hindari**
   - Logika bisnis yang kompleks dalam hooks
   - Operasi I/O yang berat
   - Memanggil operasi database lain yang bisa menyebabkan rekursi tak terbatas

3. **Error Handling**
   - Selalu tangkap exception dalam hooks
   - Berikan pesan error yang deskriptif
   - Gunakan custom exception untuk error bisnis

4. **Dokumentasi**
   - Dokumentasikan hooks yang digunakan di kode
   - Jelaskan tujuan dan efek samping dari setiap hook

Dengan menggunakan hooks dan events dengan benar, Anda dapat membuat kode yang lebih bersih, terstruktur, dan mudah dipelihara.
