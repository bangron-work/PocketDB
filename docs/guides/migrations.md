# Migrasi Data

## Daftar Isi
- [Pendahuluan](#pendahuluan)
- [Membuat Migrasi](#membuat-migrasi)
- [Menjalankan Migrasi](#menjalankan-migrasi)
- [Mengembalikan Migrasi](#mengembalikan-migrasi)
- [Contoh Kasus](#contoh-kasus)
- [Best Practice](#best-practice)

## Pendahuluan

Migrasi membantu Anda mengelola perubahan skema database secara terkendali. PocketDB menyediakan sistem migrasi sederhana untuk mengotomatisasi proses ini.

## Membuat Migrasi

Buat kelas migrasi yang mengimplementasikan `MigrationInterface`:

```php
// migrations/20230101000000_initial_schema.php

use PocketDB\MigrationInterface;
use PocketDB\Database;

class Migration_20230101000000_initial_schema implements MigrationInterface
{
    public function up(Database $db)
    {
        // Buat koleksi users
        $users = $db->selectCollection('users');
        $users->createIndex('email', 'idx_email', ['unique' => true]);
        
        // Buat koleksi posts
        $posts = $db->selectCollection('posts');
        $posts->createIndex('user_id');
        $posts->createIndex('status');
        
        return true;
    }
    
    public function down(Database $db)
    {
        // Hapus koleksi jika rollback
        $db->dropCollection('users');
        $db->dropCollection('posts');
        return true;
    }
    
    public function getVersion(): string
    {
        return '20230101000000';
    }
    
    public function getDescription(): string
    {
        return 'Membuat skema awal database';
    }
}
```

## Menjalankan Migrasi

### Menggunakan Command Line

Buat file `migrate.php` di root project:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use PocketDB\Migrator;
use PocketDB\Client;

// Konfigurasi
$config = [
    'path' => __DIR__ . '/data',
    'database' => 'myapp',
    'migrations_path' => __DIR__ . '/migrations'
];

// Inisialisasi client dan migrator
$client = new Client($config['path']);
$db = $client->selectDB($config['database']);
$migrator = new Migrator($db, $config['migrations_path']);

// Eksekusi migrasi
$command = $argv[1] ?? 'up';

try {
    switch ($command) {
        case 'up':
            $migrated = $migrator->up();
            echo sprintf("Berhasil menjalankan %d migrasi\n", count($migrated));
            break;
            
        case 'down':
            $count = $migrator->down(1); // Rollback 1 migrasi terakhir
            echo "Berhasil mengembalikan $count migrasi\n";
            break;
            
        case 'status':
            $status = $migrator->getStatus();
            echo "Status Migrasi:\n";
            foreach ($status as $migration) {
                echo sprintf(
                    "[%s] %s - %s\n",
                    $migration['applied'] ? '✓' : ' ', 
                    $migration['version'],
                    $migration['description']
                );
            }
            break;
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

### Perintah yang Tersedia

```bash
# Jalankan semua migrasi yang belum dijalankan
php migrate.php up

# Kembalikan migrasi terakhir
php migrate.php down

# Lihat status migrasi
php migrate.php status
```

## Mengembalikan Migrasi

Untuk mengembalikan migrasi, gunakan method `down()` pada kelas migrasi:

```php
// migrations/20230102000000_add_user_roles.php

class Migration_20230102000000_add_user_roles implements MigrationInterface
{
    public function up(Database $db)
    {
        $users = $db->selectCollection('users');
        
        // Tambahkan field role ke semua user yang sudah ada
        $users->update(
            ['role' => ['$exists' => false]],
            ['$set' => ['role' => 'user']],
            ['multiple' => true]
        );
        
        return true;
    }
    
    public function down(Database $db)
    {
        // Hapus field role
        $users = $db->selectCollection('users');
        $users->update(
            [],
            ['$unset' => ['role' => '']],
            ['multiple' => true]
        );
        
        return true;
    }
    
    // ... getVersion() dan getDescription()
}
```

## Contoh Kasus

### Menambahkan Index Baru

```php
class Migration_20230103000000_add_post_slug_index implements MigrationInterface
{
    public function up(Database $db)
    {
        $posts = $db->selectCollection('posts');
        $posts->createIndex('slug', 'idx_slug', ['unique' => true]);
        return true;
    }
    
    public function down(Database $db)
    {
        $posts = $db->selectCollection('posts');
        $posts->dropIndex('idx_slug');
        return true;
    }
    
    // ...
}
```

### Mengubah Struktur Data

```php
class Migration_20230104000000_convert_tags_to_array implements MigrationInterface
{
    public function up(Database $db)
    {
        $posts = $db->selectCollection('posts');
        
        // Konversi string tags ke array
        $cursor = $posts->find([
            'tags' => ['$type' => 'string']
        ]);
        
        foreach ($cursor as $post) {
            $posts->update(
                ['_id' => $post['_id']],
                ['$set' => [
                    'tags' => array_map('trim', explode(',', $post['tags']))
                ]]
            );
        }
        
        return true;
    }
    
    public function down(Database $db)
    {
        // Konversi kembali ke string
        $posts = $db->selectCollection('posts');
        $cursor = $posts->find([
            'tags' => ['$type' => 'array']
        ]);
        
        foreach ($cursor as $post) {
            $posts->update(
                ['_id' => $post['_id']],
                ['$set' => [
                    'tags' => implode(', ', $post['tags'])
                ]]
            );
        }
        
        return true;
    }
    
    // ...
}
```

## Best Practice

1. **Gunakan Versi yang Konsisten**
   - Format: `YYYYMMDDHHMMSS_deskripsi_singkat.php`
   - Contoh: `20230101120000_create_users_table.php`

2. **Buat Migrasi yang Idempoten**
   - Pastikan migrasi dapat dijalankan berulang kali tanpa error
   - Gunakan `$exists` untuk mengecek apakah field/indeks sudah ada

3. **Hindari Data Test dalam Migrasi**
   - Gunakan seeder terpisah untuk data dummy
   - Migrasi hanya untuk perubahan struktur

4. **Backup Sebelum Migrasi**
   - Selalu backup database sebelum menjalankan migrasi
   - Terutama untuk migrasi yang mengubah data

5. **Dokumentasi Perubahan**
   - Tulis deskripsi yang jelas di method `getDescription()`
   - Tambahkan komentar untuk logika yang kompleks

6. **Test Migrasi**
   - Selalu test migrasi di lingkungan pengembangan terlebih dahulu
   - Test juga rollback-nya

Dengan mengikuti panduan ini, Anda dapat mengelola perubahan skema database dengan aman dan terkendali.
