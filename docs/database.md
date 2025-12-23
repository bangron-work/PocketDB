# Database Class Documentation

The `Database` class represents a single database instance and manages collections within it. It provides methods for database operations, collection management, and advanced features like database attachment and encryption.

## Constructor

```php
public function __construct(string $path = self::DSN_PATH_MEMORY, array $options = [])
```

**Parameters:**

- `$path` (string): Path to database file or `:memory:` for in-memory database
- `$options` (array): Additional options including encryption key

**Constants:**

- `DSN_PATH_MEMORY = ':memory:'`: Constant for in-memory database

**Example:**

```php
// Create in-memory database
$db = new Database(':memory:');

// Create file-based database
$db = new Database('./mydatabase.sqlite');

// Create database with encryption
$db = new Database('./secure.db', [
    'encryption_key' => 'my-secret-key-123'
]);
```

## Properties

### `$connection`

```php
public $connection
```

PDO connection instance to the SQLite database.

### `$options`

```php
protected array $options = []
```

Raw options passed to constructor.

### `$encryptionKey`

```php
public ?string $encryptionKey = null
```

Optional encryption key for database-level encryption.

### `$collections`

```php
protected array $collections = []
```

Array of Collection instances managed by this database.

### `$path`

```php
public string $path
```

The path to the database file.

### `$client`

```php
public ?Client $client = null
```

Back-reference to the owning Client instance.

### `$document_criterias`

```php
protected array $document_criterias = []
```

Registered criteria functions for complex queries.

## Methods

### attach()

```php
public function attach(string $path, string $alias): bool
```

**Parameters:**

- `$path` (string): Path to the SQLite database file to attach
- `$alias` (string): Alias for the attached database

**Returns:**

- `bool`: True on success, false on failure

**Description:**
Attaches another SQLite database file to this connection with an alias. Allows querying across multiple database files.

**Validation:**

- Alias must be a simple identifier (letters, numbers, underscore)

**Example:**

```php
// Main database
$mainDb = new Database('./main.sqlite');

// Attach another database
$mainDb->attach('./archive.sqlite', 'archive');

// Now you can query across databases
$mainDb->connection->exec("SELECT * FROM users u JOIN archive.orders o ON u.id = o.user_id");
```

### detach()

```php
public function detach(string $alias): bool
```

**Parameters:**

- `$alias` (string): Alias of the database to detach

**Returns:**

- `bool`: True on success, false on failure

**Description:**
Detaches a previously attached database alias.

**Example:**

```php
$db = new Database('./main.sqlite');
$db->attach('./archive.sqlite', 'archive');

// Use the attached database
$results = $db->connection->query("SELECT * FROM archive.users");

// Detach when done
$db->detach('archive');
```

### attachOnce()

```php
public function attachOnce(string $path, string $alias, callable $callback)
```

**Parameters:**

- `$path` (string): Path to the SQLite database file to attach
- `$alias` (string): Alias for the attached database
- `$callback` (callable): Function to execute with the attached database

**Returns:**

- `mixed`: Whatever the callback returns

**Description:**
Attaches a database, runs a callback, then detaches it automatically in a safe finally block.

**Example:**

```php
$db = new Database('./main.sqlite');

// Temporary attachment with callback
$result = $db->attachOnce('./archive.sqlite', 'archive', function(Database $db, string $alias) {
    // Query across databases
    $stmt = $db->connection->query("
        SELECT u.name, o.total
        FROM users u
        JOIN archive.orders o ON u.id = o.user_id
        WHERE o.date > '2023-01-01'
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

// Database is automatically detached after callback
```

### closeAll()

```php
public static function closeAll(): void
```

**Description:**
Closes all known Database instances. Useful for cleanup at the end of an application.

**Example:**

```php
// Create multiple database instances
$db1 = new Database(':memory:');
$db2 = new Database('./test.sqlite');

// Use databases...

// Close all database instances
Database::closeAll();
```

### close()

```php
public function close(): void
```

**Description:**
Closes the database connection and cleans up registered criteria functions.

**Example:**

```php
$db = new Database('./test.sqlite');

// Use database...

// Close connection
$db->close();

// Connection is now closed
```

### registerCriteriaFunction()

```php
public function registerCriteriaFunction($criteria): ?string
```

**Parameters:**

- `$criteria`: Criteria function or array

**Returns:**

- `?string`: Unique ID for the criteria function or null if invalid

**Description:**
Registers a criteria function for complex queries. Supports both callable functions and array criteria.

**Example:**

```php
$db = new Database(':memory:');

// Register array criteria
$criteriaId = $db->registerCriteriaFunction([
    'age' => ['$gt' => 18],
    'status' => 'active'
]);

// Use the criteria ID in queries
$users = $db->users->find($criteriaId);
```

### callCriteriaFunction()

```php
public function callCriteriaFunction(string $id, $document): bool
```

**Parameters:**

- `$id` (string): Criteria function ID
- `$document` (array): Document to evaluate

**Returns:**

- `bool`: True if document matches criteria

**Description:**
Executes a registered criteria function against a document.

### staticCallCriteria()

```php
public static function staticCallCriteria(string $id, $document): bool
```

**Parameters:**

- `$id` (string): Criteria function ID
- `$document` (mixed): Document to evaluate

**Returns:**

- `bool`: True if document matches criteria

**Description:**
Static entrypoint called by SQLite extension for criteria evaluation.

### vacuum()

```php
public function vacuum(): void
```

**Description:**
Rebuilds the database file to reclaim unused space.

**Example:**

```php
$db = new Database('./large_database.sqlite');

// Perform cleanup
$db->vacuum();

// Database file is now optimized
```

### drop()

```php
public function drop(): void
```

**Description:**
Drops the database. For memory databases, this is a no-op. For file databases, it deletes the file.

**Example:**

```php
$fileDb = new Database('./temp.sqlite');
$memoryDb = new Database(':memory:');

// Drop file database (deletes the file)
$fileDb->drop();

// Drop memory database (clears data)
$memoryDb->drop();
```

### createCollection()

```php
public function createCollection(string $name): void
```

**Parameters:**

- `$name` (string): Name of the collection

**Description:**
Creates a new collection with the specified name. Collections are stored as SQLite tables.

**Validation:**

- Collection names must match `/^[A-Za-z0-9_]+$/`

**Example:**

```php
$db = new Database(':memory:');

// Create collections
$db->createCollection('users');
$db->createCollection('products');
$db->createCollection('orders');

// Collections are now ready for use
$users = $db->users;
$users->insert(['name' => 'John']);
```

### dropCollection()

```php
public function dropCollection(string $name): void
```

**Parameters:**

- `$name` (string): Name of the collection to drop

**Description:**
Drops a collection from the database.

**Example:**

```php
$db = new Database(':memory:');

$db->createCollection('temp_collection');
$db->temp_collection->insert(['data' => 'test']);

// Drop the collection
$db->dropCollection('temp_collection');

// Collection no longer exists
```

### getCollectionNames()

```php
public function getCollectionNames(): array
```

**Returns:**

- `array`: Array of collection names

**Description:**
Returns all collection names in the database.

**Example:**

```php
$db = new Database(':memory:');

// Create some collections
$db->createCollection('users');
$db->createCollection('products');

// Get collection names
$collections = $db->getCollectionNames();
// Returns: ['users', 'products']
```

### listCollections()

```php
public function listCollections(): array
```

**Returns:**

- `array`: Array of Collection instances

**Description:**
Returns all Collection instances in the database.

**Example:**

```php
$db = new Database(':memory:');

// Get all collections
$collections = $db->listCollections();

foreach ($collections as $name => $collection) {
    echo "Collection: $name\n";
}
```

### selectCollection()

```php
public function selectCollection(string $name): Collection
```

**Parameters:**

- `$name` (string): Name of the collection

**Returns:**

- `Collection`: Collection instance

**Description:**
Selects or creates a collection. If the collection doesn't exist, it will be created.

**Example:**

```php
$db = new Database(':memory:');

// Select or create collection
$users = $db->selectCollection('users');
$users->insert(['name' => 'John']);

// Equivalent to magic property access
$users = $db->users;
$users->insert(['name' => 'Jane']);
```

### \_\_get()

```php
public function __get(string $collection): Collection
```

**Parameters:**

- `$collection` (string): Name of the collection

**Returns:**

- `Collection`: Collection instance

**Description:**
Magic method to access collections using property syntax.

**Example:**

```php
$db = new Database(':memory:');

// Access collection using property syntax
$users = $db->users;
$users->insert(['name' => 'John']);

// Equivalent to:
$users = $db->selectCollection('users');
```

### createJsonIndex()

```php
public function createJsonIndex(string $collection, string $field, ?string $indexName = null): void
```

**Parameters:**

- `$collection` (string): Name of the collection
- `$field` (string): Field to create index on
- `$indexName` (string, optional): Custom index name

**Description:**
Creates a JSON index for a field using SQLite's json_extract function.

**Example:**

```php
$db = new Database(':memory:');
$db->createCollection('users');

// Create index on email field
$db->createJsonIndex('users', 'email');

// Create custom index name
$db->createJsonIndex('users', 'age', 'idx_users_age');

// Indexes improve query performance
$users = $db->users->find(['email' => 'john@example.com']);
```

### quoteIdentifier()

```php
public function quoteIdentifier(string $name): string
```

**Parameters:**

- `$name` (string): Identifier name to quote

**Returns:**

- `string`: Quoted identifier

**Description:**
Safely quotes table/index/column names for SQL queries.

**Example:**

```php
$db = new Database(':memory:');

// Quote identifier
$quoted = $db->quoteIdentifier('users');
// Returns: `users`

// Use in SQL
$sql = "SELECT * FROM $quoted WHERE name = 'John'";
```

### dropIndex()

```php
public function dropIndex(string $indexName): void
```

**Parameters:**

- `$indexName` (string): Name of the index to drop

**Description:**
Drops an index by name.

**Example:**

```php
$db = new Database(':memory:');

// Create index
$db->createJsonIndex('users', 'email', 'idx_users_email');

// Drop index
$db->dropIndex('idx_users_email');
```

## Complete Usage Examples

### Basic Database Operations

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create database
$db = new Database(':memory:');

// Create collections
$db->createCollection('users');
$db->createCollection('products');

// Insert data using selectCollection
$users = $db->selectCollection('users');
$users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Using magic property access
$products = $db->products;
$products->insert([
    'name' => 'Laptop',
    'price' => 999.99,
    'category' => 'electronics'
]);

// Query data
$user = $users->findOne(['email' => 'john@example.com']);
$product = $products->findOne(['name' => 'Laptop']);

echo "User: " . $user['name'] . "\n";
echo "Product: " . $product['name'] . " - $" . $product['price'] . "\n";

// List collections
$collections = $db->getCollectionNames();
echo "Collections: " . implode(', ', $collections) . "\n";

$db->close();
?>
```

### Database Attachment

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create main database
$mainDb = new Database('./main.sqlite');

// Create archive database
$archiveDb = new Database('./archive.sqlite');

// Insert some data in archive
$archiveDb->createCollection('old_users');
$archiveDb->old_users->insert([
    'name' => 'Old User',
    'email' => 'old@example.com',
    'last_login' => '2022-01-01'
]);

// Attach archive to main database
$mainDb->attach('./archive.sqlite', 'archive');

// Query across databases
$stmt = $mainDb->connection->query("
    SELECT u.name, u.email, ou.last_login
    FROM users u
    LEFT JOIN archive.old_users ou ON u.email = ou.email
    WHERE u.status = 'active'
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $row) {
    echo "User: {$row['name']}, Last Login: {$row['last_login']}\n";
}

// Detach when done
$mainDb->detach('archive');

$mainDb->close();
$archiveDb->close();
?>
```

### Database Attachment with Callback

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Main database
$mainDb = new Database('./main.sqlite');

// Archive database
$archiveDb = new Database('./archive.sqlite');
$archiveDb->createCollection('historical_data');
$archiveDb->historical_data->insert([
    'metric' => 'users_total',
    'value' => 1000,
    'date' => '2022-12-31'
]);

// Use attachOnce for safe temporary attachment
$result = $mainDb->attachOnce('./archive.sqlite', 'archive', function(Database $db, string $alias) {
    // Query across databases
    $stmt = $db->connection->query("
        SELECT u.name, u.email, hd.value as historical_users
        FROM users u
        JOIN archive.historical_data hd ON hd.metric = 'users_total'
        WHERE u.created_at > '2023-01-01'
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

foreach ($result as $row) {
    echo "User: {$row['name']}, Historical Context: {$row['historical_users']}\n";
}

// Database is automatically detached

$mainDb->close();
$archiveDb->close();
?>
```

### Encryption Support

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create encrypted database
$encryptedDb = new Database('./secure.db', [
    'encryption_key' => 'my-secret-key-123'
]);

$encryptedDb->createCollection('sensitive_data');

// Insert sensitive data
$encryptedDb->sensitive_data->insert([
    'user_id' => 'usr_001',
    'ssn' => '123-45-6789',
    'credit_card' => '4111111111111111'
]);

// The data is encrypted in the database
$stmt = $encryptedDb->connection->query("SELECT document FROM sensitive_data");
$rawData = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Encrypted data: " . $rawData['document'] . "\n";

// Query and decrypt automatically
$record = $encryptedDb->sensitive_data->findOne(['user_id' => 'usr_001']);
echo "Decrypted SSN: " . $record['ssn'] . "\n";

$encryptedDb->close();
?>
```

### Indexing for Performance

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create database
$db = new Database(':memory:');
$db->createCollection('users');

// Insert test data
$users = $db->users;
for ($i = 1; $i <= 1000; $i++) {
    $users->insert([
        'name' => "User $i",
        'email' => "user$i@example.com",
        'age' => 20 + ($i % 50),
        'status' => $i % 2 === 0 ? 'active' : 'inactive'
    ]);
}

// Create indexes for better performance
$db->createJsonIndex('users', 'email');
$db->createJsonIndex('users', 'age');
$db->createJsonIndex('users', 'status');

// Query with indexed fields (faster)
$start = microtime(true);
$activeUsers = $users->find(['status' => 'active', 'age' => ['$gte' => 30]])->toArray();
$end = microtime(true);

echo "Found " . count($activeUsers) . " active users aged 30+\n";
echo "Query took " . round(($end - $start) * 1000, 2) . " ms\n";

// List indexes
$stmt = $db->connection->query("SELECT name FROM sqlite_master WHERE type='index'");
$indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Indexes: " . implode(', ', $indexes) . "\n";

$db->close();
?>
```

### Criteria Functions

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create database
$db = new Database(':memory:');
$db->createCollection('users');

// Insert test data
$users = $db->users;
$users->insert([
    'name' => 'John Doe',
    'age' => 25,
    'status' => 'active',
    'tags' => ['admin', 'developer']
]);

$users->insert([
    'name' => 'Jane Smith',
    'age' => 30,
    'status' => 'active',
    'tags' => ['user', 'designer']
]);

$users->insert([
    'name' => 'Bob Johnson',
    'age' => 35,
    'status' => 'inactive',
    'tags' => ['user', 'manager']
]);

// Register complex criteria
$criteria = [
    'age' => ['$gte' => 25],
    'status' => 'active',
    'tags' => ['$has' => 'admin']
];

$criteriaId = $db->registerCriteriaFunction($criteria);

// Use criteria in query
$adminUsers = $users->find($criteriaId)->toArray();

echo "Found " . count($adminUsers) . " admin users:\n";
foreach ($adminUsers as $user) {
    echo "- {$user['name']} ({$user['age']})\n";
}

// Test the criteria function directly
$testDoc = ['age' => 28, 'status' => 'active', 'tags' => ['admin']];
$matches = $db->callCriteriaFunction($criteriaId, $testDoc);
echo "Test document matches: " . ($matches ? 'Yes' : 'No') . "\n";

$db->close();
?>
```

### Database Cleanup

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create multiple database instances
$db1 = new Database(':memory:');
$db2 = new Database('./temp1.sqlite');
$db3 = new Database('./temp2.sqlite');

// Use databases
$db1->createCollection('test');
$db1->test->insert(['data' => 'memory']);

$db2->createCollection('test');
$db2->test->insert(['data' => 'file1']);

$db3->createCollection('test');
$db3->test->insert(['data' => 'file2']);

// Close all databases at once
Database::closeAll();

// All connections are now closed
echo "All databases closed successfully\n";
?>
```

## Best Practices

1. **Use appropriate database types**: Use `:memory:` for temporary data, file paths for persistent data
2. **Always close connections**: Use `close()` or `closeAll()` for cleanup
3. **Use indexes**: Create JSON indexes for frequently queried fields
4. **Validate names**: Ensure collection and database names follow the required pattern
5. **Handle encryption**: Use encryption keys for sensitive data
6. **Use attachment wisely**: Database attachment is powerful but use it carefully
7. **Cleanup temporary databases**: Drop temporary databases when no longer needed
