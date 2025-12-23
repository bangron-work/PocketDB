# PocketDB

**PocketDB** is a lightweight, serverless NoSQL database library for PHP, built on top of the robust **SQLite** engine. It combines the flexibility of JSON document storage with the reliability, speed, and ACID properties of SQLite.

Ideal for applications that need a MongoDB-like API without the overhead of maintaining a separate database server.

## 🚀 Features

- **NoSQL API**: Insert, update, and query JSON documents using a MongoDB-like syntax.
- **Serverless**: Runs directly on SQLite (supports both file-based and `:memory:` storage).
- **Event-Driven Hooks**: Powerful `on('beforeInsert', ...)` system for validation, triggers, and logic.
- **🔒 Encryption**: Built-in AES-256-CBC encryption for documents.
- **Searchable Encryption**: Ability to index and search specific fields even within encrypted documents.
- **Relationships**: `populate` helper to join documents across collections or different databases.
- **Flexible IDs**: Supports UUID v4, Auto-Increment Prefixes (e.g., `ORD-001`), or Manual IDs.
- **Zero Configuration**: Just require and run.

## 📦 Installation

### Via Composer (Recommended)

```bash
composer require bangron-work/pocketdb
```

### From GitHub

```bash
# Clone the repository
git clone https://github.com/bangron-work/PocketDB.git
cd PocketDB

# Include in your project
require_once 'src/Client.php';
```

**GitHub Repository:** https://github.com/bangron-work/PocketDB

**Documentation:** [Full Documentation](docs/index.md)

## ⚡ Quick Start

### Basic Usage

```php
use PocketDB\Client;

// Initialize (creates 'my_database.sqlite' if it doesn't exist)
$client = new Client(__DIR__ . '/data');
$db = $client->my_database;

// Access a collection (table)
$users = $db->users;

// 1. Insert a document
$userId = $users->insert([
    'username' => 'johndoe',
    'email'    => 'john@example.com',
    'profile'  => [
        'age'  => 28,
        'city' => 'Jakarta'
    ]
]);

// 2. Find a document
$user = $users->findOne(['username' => 'johndoe']);

// 3. Update a document (using dot notation)
$users->update(
    ['_id' => $userId],
    ['profile.city' => 'Bandung']
);

// 4. Delete
$users->remove(['_id' => $userId]);

```

---

## 🔥 Advanced Features

### 1. Event Hooks (`on`)

PocketDB allows you to intercept operations. This is perfect for validation, data mutation, or triggering side effects (logging, email, etc.).

Supported events: `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeRemove`, `afterRemove`.

```php
// Example: Validate stock before order insertion
$db->orders->on('beforeInsert', function (&$order) use ($db) {
    $product = $db->products->findOne(['_id' => $order['product_id']]);

    // Validation
    if ($product['stock'] < $order['qty']) {
        return false; // Cancel insertion
    }

    // Mutation: Calculate total automatically
    $order['total_price'] = $product['price'] * $order['qty'];
    $order['created_at']  = date('Y-m-d H:i:s');

    return $order; // Return the modified document
});

// Example: Update ledger after successful order
$db->orders->on('afterInsert', function ($order) use ($db) {
    $db->ledger->insert([
        'description' => 'Sales Order ' . $order['_id'],
        'amount'      => $order['total_price'],
        'type'        => 'CREDIT'
    ]);
});

```

### 2. Encryption & Privacy

You can encrypt entire documents (except `_id`). You can also configure specific fields to be "Searchable" (hashed or plain) so you can still query them despite encryption.

```php
// 1. Initialize with an encryption key
$db = $client->selectDB('secure_db', [
    'encryption_key' => 'your-secret-32-char-key-here'
]);

// 2. Configure searchable fields
// 'email' will be hashed (secure search, exact match only)
// 'role' will be plain text (allows LIKE/Regex search)
$db->users->setSearchableFields([
    'email' => ['hash' => true],
    'role'  => ['hash' => false]
]);

// 3. Insert (Data is stored encrypted on disk)
$db->users->insert(['email' => 'ceo@company.com', 'role' => 'admin', 'salary' => 50000]);

// 4. You can still find it!
$user = $db->users->findOne(['email' => 'ceo@company.com']);
// Output: Decrypted document

```

### 3. Relationships (Populate)

Join data from different collections (similar to SQL JOIN or Mongoose Populate).

```php
// Assume we have 'orders' containing 'customer_id'
$orders = $db->orders->find()->toArray();

// Populate user data into 'customer_details' field
$results = $db->orders->populate(
    $orders,           // Source data
    'customer_id',     // Foreign key in 'orders'
    'users',           // Target collection name
    '_id',             // Primary key in 'users'
    'customer_details' // Output field name
);

```

### 4. Querying & Cursors

Use a fluent chainable API for advanced queries.

```php
$results = $db->products->find([
        'price' => ['$gte' => 1000000],   // Price >= 1M
        'tags'  => ['$in' => ['promo', 'new']] // Tag is in array
    ])
    ->sort(['price' => -1]) // Sort Descending
    ->limit(10)
    ->skip(0)
    ->toArray();

```

**Supported Operators:**
`$eq`, `$gt`, `$gte`, `$lt`, `$lte`, `$in`, `$nin`, `$exists`, `$regex`, `$fuzzy`.

### 5. ID Management

Control how `_id` is generated.

```php
// Mode: Auto (Default) -> UUID v4
$db->logs->setIdModeAuto();

// Mode: Prefix -> 'TRX-000001', 'TRX-000002'
$db->orders->setIdModePrefix('TRX-');

// Mode: Manual -> You must provide '_id'
$db->users->setIdModeManual();

```

## 🛠 Architecture

- **Client**: Manages the directory of database files.
- **Database**: Wrapper around `PDO` (SQLite), handles transactions and encryption keys.
- **Collection**: Handles logic, hooks, ID generation, and query building.
- **Cursor**: Handles iteration, sorting, pagination, and lazy loading.

## 📄 License

MIT License. See [LICENSE](https://www.google.com/search?q=LICENSE) for more information.

---

_Built with ❤️ using PHP and SQLite._
