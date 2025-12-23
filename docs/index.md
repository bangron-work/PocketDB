# PocketDB Documentation

PocketDB adalah database NoSQL ringan yang dibangun di atas SQLite, menawarkan API yang mirip dengan MongoDB untuk PHP. PocketDB menyimpan data dalam format JSON dan menyediakan fitur-fitur seperti indexing, encryption, dan query yang powerful.

## 🎯 Repository Information

**GitHub Repository:** https://github.com/bangron-work/PocketDB

**Installation via Composer:**

```bash
composer require bangron-work/pocketdb
```

## 🏗️ Struktur Proyek

```
PocketDB/
├── src/
│   ├── Client.php          # Kelas utama untuk mengelola database
│   ├── Database.php        # Kelas untuk mengelola database dan koleksi
│   ├── Collection.php      # Kelas untuk operasi pada koleksi
│   ├── Cursor.php          # Kelas untuk hasil query dan iterasi
│   └── UtilArrayQuery.php  # Utility untuk query yang kompleks
├── docs/                   # Dokumentasi lengkap
│   ├── index.md           # Dokumentasi utama
│   ├── client.md          # Dokumentasi Client class
│   ├── database.md        # Dokumentasi Database class
│   ├── collection.md      # Dokumentasi Collection class
│   ├── cursor.md          # Dokumentasi Cursor class
│   ├── utilities.md       # Dokumentasi Utility functions
│   └── installation.md    # Panduan instalasi
├── tests/                  # Test cases
├── tools/                  # Benchmark dan tools
└── examples/              # Contoh implementasi
```

## ✨ Fitur Utama

### Core Features

- **Storage Format**: Data disimpan dalam format JSON di SQLite
- **MongoDB-like API**: API yang familiar bagi pengguna MongoDB
- **Serverless**: Tidak memerlukan server database terpisah
- **ACID Compliance**: Mendukung transaksi dan data integrity

### Advanced Features

- **🔒 Encryption**: Mendukung enkripsi AES-256-CBC untuk data sensitif
- **🔍 Indexing**: JSON indexing untuk query yang lebih cepat
- **🔎 Searchable Fields**: Fields yang dapat di-index untuk pencarian cepat
- **⚡ Hooks System**: Event-driven programming dengan before/after hooks
- **🔗 Populate**: Relasi data antar koleksi
- **💾 Memory & Disk**: Mendukung database di memory maupun disk

### Query Capabilities

- **Rich Query Operators**: `$gt`, `$gte`, `$lt`, `$lte`, `$in`, `$nin`, `$exists`, `$regex`, `$fuzzy`
- **Nested Field Access**: Dukungan dot notation untuk nested fields
- **Logical Operators**: `$and`, `$or` untuk query kompleks
- **Custom Functions**: Dukungan untuk criteria functions yang custom

## 📦 Instalasi

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

### Persyaratan Sistem

- **PHP**: 7.4 atau lebih tinggi
- **Extensions**: PDO SQLite, OpenSSL
- **OS**: Linux, macOS, Windows

## 🚀 Quick Start

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
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'profile' => [
        'city' => 'Jakarta',
        'interests' => ['programming', 'reading']
    ]
]);

// Query data
$user = $users->findOne(['email' => 'john@example.com']);
echo "User: " . $user['name'] . "\n";

// Update data
$users->update(['email' => 'john@example.com'], ['age' => 31]);

// Delete data
$users->remove(['email' => 'john@example.com']);

// Clean up
$client->close();
?>
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

## 🏛️ Komponen Utama

### 1. Client

- **Fungsi**: Titik masuk utama untuk mengelola database
- **Responsibilities**:
  - Manajemen multiple database
  - Path validation
  - Connection pooling
  - Cleanup resources

### 2. Database

- **Fungsi**: Container untuk koleksi-koleksi
- **Responsibilities**:
  - SQLite connection management
  - Collection creation/dropping
  - Encryption key management
  - Database attachment/detachment

### 3. Collection

- **Fungsi**: Struktur data yang menyimpan dokumen-dokumen JSON
- **Responsibilities**:
  - CRUD operations
  - ID generation (AUTO, MANUAL, PREFIX)
  - Event hooks (before/after insert/update/remove)
  - Encryption and searchable fields
  - Index management

### 4. Cursor

- **Fungsi**: Hasil query yang dapat di-iterasi
- **Responsibilities**:
  - Query result iteration
  - Pagination (limit/skip)
  - Sorting
  - Data population
  - Field projection

### 5. UtilArrayQuery

- **Fungsi**: Utility untuk query yang kompleks
- **Responsibilities**:
  - Complex condition evaluation
  - Operator implementation ($gt, $lt, $in, $regex, etc.)
  - Fuzzy search algorithms

## 📚 Dokumentasi Lengkap

### Core Documentation

- [Client Class](client.md) - API lengkap untuk Client
- [Database Class](database.md) - API lengkap untuk Database
- [Collection Class](collection.md) - API lengkap untuk Collection
- [Cursor Class](cursor.md) - API lengkap untuk Cursor
- [Utilities](utilities.md) - Helper functions dan utilities

### Guides & Tutorials

- [Installation Guide](installation.md) - Panduan instalasi dan setup
- [Advanced Usage](advanced.md) - Fitur-fitur advanced dan best practices
- [Performance Optimization](performance.md) - Tips untuk performa optimal
- [Error Handling](error-handling.md) - Panduan handling error

## 🔧 Fitur Lanjutan

### 1. Encryption & Security

```php
// Database-level encryption
$db = new Database('./secure.db', [
    'encryption_key' => 'your-32-character-secret-key'
]);

// Collection-level encryption
$users = $db->users;
$users->setEncryptionKey('collection-specific-key');

// Searchable fields for encrypted data
$users->setSearchableFields(['email'], true); // Hashed search
$users->setSearchableFields(['username'], false); // Plain text search
```

### 2. Event Hooks System

```php
// Before insert hook for validation
$users->on('beforeInsert', function($document) {
    if (!filter_var($document['email'], FILTER_VALIDATE_EMAIL)) {
        return false; // Cancel insertion
    }
    $document['created_at'] = date('Y-m-d H:i:s');
    return $document;
});

// After insert hook for logging
$users->on('afterInsert', function($document, $id) {
    error_log("User created: $id - {$document['name']}");
});
```

### 3. Data Population

```php
// Join data from different collections
$posts = $db->posts;
$users = $db->users;

$postsWithAuthors = $posts->populate(
    $posts->find()->toArray(),
    'author_id',
    'users',
    '_id',
    'author'
);
```

### 4. Complex Queries

```php
// Advanced query with multiple operators
$results = $db->users->find([
    'age' => ['$gt' => 18, '$lt' => 65],
    'status' => 'active',
    'tags' => ['$in' => ['admin', 'moderator']],
    '$or' => [
        ['name' => ['$regex' => '^J.*e$']],
        ['email' => ['$fuzzy' => 'john']]
    ]
]);
```

## 🎯 Use Cases

### 1. User Management System

```php
// User registration with validation
$users->on('beforeInsert', function($user) {
    if (!isset($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $user['created_at'] = date('Y-m-d H:i:s');
    return $user;
});

// User profile updates
$users->on('beforeUpdate', function($criteria, $data) {
    // Prevent email changes
    if (isset($data['email'])) {
        unset($data['email']);
    }
    return ['criteria' => $criteria, 'data' => $data];
});
```

### 2. E-commerce Platform

```php
// Order management with inventory check
$orders->on('beforeInsert', function($order) use ($products) {
    $product = $products->findOne(['_id' => $order['product_id']]);
    if ($product['stock'] < $order['quantity']) {
        return false; // Insufficient stock
    }

    // Update inventory
    $products->update(
        ['_id' => $order['product_id']],
        ['stock' => $product['stock'] - $order['quantity']]
    );

    return $order;
});
```

### 3. Content Management System

```php
// Content publishing workflow
$content->on('beforeInsert', function($doc) {
    $doc['status'] = 'draft';
    $doc['created_at'] = date('Y-m-d H:i:s');
    return $doc;
});

// Auto-publish when conditions are met
$content->on('beforeUpdate', function($criteria, $data) {
    if (isset($data['status']) && $data['status'] === 'published') {
        $data['published_at'] = date('Y-m-d H:i:s');
    }
    return ['criteria' => $criteria, 'data' => $data];
});
```

## ⚡ Performance Optimization

### 1. Indexing Strategy

```php
// Create indexes for frequently queried fields
$users->createIndex('email');
$users->createIndex('status');
$users->createIndex('created_at');

// Use searchable fields for text search
$users->setSearchableFields(['username', 'email'], false);
$users->setSearchableFields(['ssn'], true); // Hashed for privacy
```

### 2. Query Optimization

```php
// Use projections to limit returned data
$userProfiles = $users->find(
    ['status' => 'active'],
    ['name' => 1, 'email' => 1, 'profile' => 1]
);

// Use pagination for large datasets
$page = 1;
$perPage = 20;
$usersPage = $users->find()
                  ->sort(['created_at' => -1])
                  ->skip(($page - 1) * $perPage)
                  ->limit($perPage);
```

### 3. Memory Management

```php
// Use cursors for large result sets
$largeResults = $users->find(['active' => true]);
foreach ($largeResults as $user) {
    // Process one at a time - memory efficient
    processUser($user);
}

// Close connections when done
$client->close();
```

## 🛡️ Error Handling

### 1. Database Connection Errors

```php
try {
    $client = new Client('./data/mydatabase.sqlite');
    $db = $client->myApp;
    // Use database...
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    // Log error and retry or show user-friendly message
}
```

### 2. Query Errors

```php
try {
    $users = $db->users;
    $result = $users->insert($document);
    if (!$result) {
        echo "Insert failed";
    }
} catch (Exception $e) {
    echo "Query error: " . $e->getMessage();
    // Handle specific error cases
}
```

### 3. Validation Errors

```php
// Validate before insert
function validateUser($user) {
    $errors = [];

    if (empty($user['name'])) {
        $errors[] = 'Name is required';
    }

    if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    return $errors;
}

// Usage
$errors = validateUser($userData);
if (!empty($errors)) {
    echo "Validation failed: " . implode(', ', $errors);
}
```

## 🚀 Production Deployment

### 1. Environment Configuration

```php
// config.php
return [
    'database_path' => getenv('POCKETDB_PATH') ?: '/var/www/data/pocketdb.sqlite',
    'encryption_key' => getenv('POCKETDB_ENCRYPTION_KEY'),
    'debug' => getenv('POCKETDB_DEBUG') === 'true',
    'backup_enabled' => true,
    'backup_path' => '/var/backups/pocketdb'
];
```

### 2. Backup Strategy

```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/pocketdb"
DB_PATH="/var/www/data/pocketdb.sqlite"

# Create backup
sqlite3 $DB_PATH ".backup $BACKUP_DIR/pocketdb_$DATE.sqlite"

# Keep only last 7 days
find $BACKUP_DIR -name "pocketdb_*.sqlite" -mtime +7 -delete
```

### 3. Monitoring and Logging

```php
// Error logging
error_log("PocketDB Error: " . $e->getMessage(), 3, "/var/log/pocketdb_errors.log");

// Performance monitoring
$start = microtime(true);
$result = $users->find(['active' => true])->toArray();
$end = microtime(true);
$executionTime = ($end - $start) * 1000;

error_log("Query executed in " . round($executionTime, 2) . " ms");
```

## 🤝 Kontribusi

Silakan fork dan submit pull request untuk kontribusi. Pastikan untuk:

1. **Run Tests**: Jalankan `vendor/bin/phpunit --testdox` sebelum submit
2. **Follow Coding Standards**: Ikuti PSR-12 dan clean code principles
3. **Add Tests**: Tambahkan test untuk fitur baru
4. **Update Documentation**: Perbarui dokumentasi jika ada perubahan API
5. **Document Changes**: Berikan penjelasan tentang perubahan yang dibuat

### Development Setup

```bash
# Clone repository
git clone https://github.com/bangron-work/PocketDB.git
cd PocketDB

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit --testdox

# Run benchmarks
php tools/benchmark.php
```

## 📄 Lisensi

[MIT License](LICENSE)

## 🔗 Resources

- [GitHub Repository](https://github.com/bangron-work/PocketDB)
- [Issues & Bug Reports](https://github.com/bangron-work/PocketDB/issues)
- [Discussions](https://github.com/bangron-work/PocketDB/discussions)
- [Composer Package](https://packagist.org/packages/bangron-work/pocketdb)

---

_Dibuat dengan ❤️ menggunakan PHP dan SQLite_
