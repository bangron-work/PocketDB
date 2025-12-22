# Transaksi di PocketDB

## Daftar Isi
- [Pengenalan Transaksi](#pengenalan-transaksi)
- [Menggunakan Transaksi Sederhana](#menggunakan-transaksi-sederhana)
- [Transaksi dengan Rollback](#transaksi-dengan-rollback)
- [Transaksi Bersarang](#transaksi-bersarang)
- [Isolasi dan Locking](#isolasi-dan-locking)
- [Error Handling](#error-handling)
- [Best Practice](#best-practice)

## Pengenalan Transaksi

Transaksi memungkinkan Anda menjalankan beberapa operasi database sebagai satu unit kerja yang atomik. Jika salah satu operasi gagal, semua perubahan dalam transaksi akan dibatalkan (rollback).

## Menggunakan Transaksi Sederhana

```php
// Mulai transaksi
$db->beginTransaction();

try {
    // Operasi 1: Kurangi stok produk
    $products->update(
        ['_id' => 'produk123', 'stok' => ['$gte' => 2]],
        ['$inc' => ['stok' => -2]]
    );

    // Operasi 2: Buat pesanan baru
    $orders->insert([
        'produk_id' => 'produk123',
        'jumlah' => 2,
        'total' => 200000,
        'status' => 'diproses'
    ]);

    // Commit transaksi jika semua berhasil
    $db->commit();
    echo "Transaksi berhasil";
} catch (\Exception $e) {
    // Rollback jika terjadi error
    $db->rollback();
    echo "Transaksi gagal: " . $e->getMessage();
}
```

## Transaksi dengan Rollback

```php
try {
    $db->beginTransaction();

    // Operasi yang mungkin gagal
    $result = $users->update(
        ['email' => 'user@example.com'],
        ['$set' => ['saldo' => 1000000]]
    );

    if ($result->getModifiedCount() === 0) {
        throw new \Exception('Pengguna tidak ditemukan');
    }

    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
    // Log error atau beri tahu pengguna
    error_log("Error dalam transaksi: " . $e->getMessage());
}
```

## Transaksi Bersarang

PocketDB mendukung transaksi bersarang (nested transactions) menggunakan savepoint:

```php
function prosesOrder($userId, $produkId, $jumlah) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Kurangi stok
        $produk = $produkCollection->findAndModify(
            ['_id' => $produkId, 'stok' => ['$gte' => $jumlah]],
            ['$inc' => ['stok' => -$jumlah]],
            ['new' => true]
        );
        
        if (!$produk) {
            throw new \Exception('Stok tidak mencukupi');
        }
        
        // Buat pesanan
        $orderId = $orderCollection->insert([
            'user_id' => $userId,
            'produk_id' => $produkId,
            'jumlah' => $jumlah,
            'total' => $produk['harga'] * $jumlah,
            'status' => 'diproses',
            'created_at' => new \MongoDB\BSON\UTCDateTime()
        ]);
        
        $db->commit();
        return $orderId;
        
    } catch (\Exception $e) {
        $db->rollback();
        throw $e; // Lempar kembali exception untuk penanganan lebih lanjut
    }
}
```

## Isolasi dan Locking

### Tingkat Isolasi

PocketDB menggunakan SQLite sebagai backend, yang mendukung beberapa tingkat isolasi:

1. **READ UNCOMMITTED** - Tidak dianjurkan, bisa terjadi dirty reads
2. **READ COMMITTED** - Default, mencegah dirty reads
3. **SERIALIZABLE** - Level isolasi tertinggi, mencegah phantom reads

```php
// Set tingkat isolasi
$db->getConnection()->exec('PRAGMA read_uncommitted = 0'); // Default
$db->getConnection()->exec('PRAGMA read_uncommitted = 1'); // Untuk READ UNCOMMITTED
```

### Locking

- **Shared Lock**: Digunakan untuk operasi baca
- **Exclusive Lock**: Digunakan untuk operasi tulis

```php
// Lock eksklusif untuk operasi tulis
$db->getConnection()->beginTransaction();
try {
    // Operasi tulis di sini
    $db->getConnection()->commit();
} catch (\Exception $e) {
    $db->getConnection()->rollBack();
    throw $e;
}
```

## Error Handling

### Jenis-Jenis Error

1. **Constraint Violation**
   - Duplikat kunci unik
   - Foreign key violation
   - Check constraint violation

2. **Connection Errors**
   - Koneksi ke database terputus
   - Timeout

3. **Query Errors**
   - Syntax error
   - Tabel tidak ditemukan

### Contoh Penanganan Error

```php
try {
    $db->beginTransaction();
    
    // Operasi database
    $result = $users->insert([
        'email' => 'user@example.com',
        'name' => 'John Doe'
    ]);
    
    $db->commit();
    
} catch (\PocketDB\Exception\DuplicateKeyException $e) {
    $db->rollback();
    echo "Error: Email sudah terdaftar";
    
} catch (\PDOException $e) {
    $db->rollback();
    
    if ($e->getCode() == '23000') { // SQLite constraint violation
        echo "Terjadi pelanggaran kendala data";
    } else {
        echo "Error database: " . $e->getMessage();
    }
    
} catch (\Exception $e) {
    $db->rollback();
    echo "Terjadi kesalahan: " . $e->getMessage();
}
```

## Best Practice

1. **Jaga Transaksi Tetap Singkat**
   - Transaksi yang lama dapat mengunci tabel dan mempengaruhi performa
   - Selesaikan transaksi secepat mungkin

2. **Tangani Error dengan Benar**
   - Selalu gunakan try-catch untuk transaksi
   - Pastikan rollback dipanggil jika terjadi error

3. **Hindari Interaksi Pengguna dalam Transaksi**
   - Jangan menunggu input pengguna di tengah transaksi
   - Kumpulkan semua input sebelum memulai transaksi

4. **Gunakan Transaksi untuk Operasi yang Berhubungan**
   ```php
   // Baik: Operasi terkait dalam satu transaksi
   $db->beginTransaction();
   $account->debit($amount);
   $transaction->record($txData);
   $db->commit();
   
   // Buruk: Operasi tidak terkait dalam satu transaksi
   $db->beginTransaction();
   $user->updateProfile($data); // Tidak terkait dengan pembaruan produk
   $product->updateStock($productId, -1);
   $db->commit();
   ```

5. **Monitor Deadlocks**
   - Implementasi retry logic untuk menangani deadlocks
   - Gunakan timeout yang sesuai

Dengan mengikuti panduan ini, Anda dapat mengimplementasikan transaksi yang aman dan andal di aplikasi PocketDB Anda.
