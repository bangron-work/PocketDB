# Client Class Documentation

The `Client` class is the main entry point for PocketDB. It manages database connections and provides methods to work with multiple databases.

## Constructor

```php
public function __construct(string $path, array $options = [])
```

**Parameters:**

- `$path` (string): Path to database file or `:memory:` for in-memory database
- `$options` (array): Additional options for database connection

**Example:**

```php
// Create client for file-based database
$client = new Client('./data');

// Create client for in-memory database
$client = new Client(':memory:');

// Create client with options
$client = new Client('./data', [
    'encryption_key' => 'your-secret-key'
]);
```

## Properties

### `$databases`

```php
protected array $databases = []
```

Array of Database instances managed by this client.

### `$path`

```php
public string $path
```

The path to the database file or `:memory:`.

### `$options`

```php
protected array $options = []
```

Additional options passed to the constructor.

## Methods

### listDBs()

```php
public function listDBs(): array
```

**Description:**
Returns an array of database names available in the client.

**Returns:**

- `array`: List of database names

**Behavior:**

- For memory databases (`:memory:`): Returns keys from the internal `$databases` array
- For file databases: Scans the directory for `.sqlite` files and returns their basenames

**Example:**

```php
$client = new Client('./data');

// Create some databases
$client->usersDB;
$client->productsDB;

// List all databases
$databases = $client->listDBs();
// Returns: ['usersDB', 'productsDB']

// For file-based database
$fileClient = new Client('./data');
$dbs = $fileClient->listDBs();
// Returns: ['users', 'products', 'orders'] (from .sqlite files)
```

### selectCollection()

```php
public function selectCollection(string $database, string $collection): Collection
```

**Parameters:**

- `$database` (string): Name of the database
- `$collection` (string): Name of the collection

**Returns:**

- `Collection`: Collection instance

**Description:**
Shortcut method to select a collection from a specific database.

**Example:**

```php
$client = new Client(':memory:');

// Get a collection directly
$users = $client->selectCollection('myApp', 'users');
$users->insert(['name' => 'John', 'email' => 'john@example.com']);

// Find documents
$user = $users->findOne(['email' => 'john@example.com']);
```

### selectDB()

```php
public function selectDB(string $name): Database
```

**Parameters:**

- `$name` (string): Name of the database

**Returns:**

- `Database`: Database instance

**Description:**
Selects or creates a database instance. Validates database names to prevent path traversal attacks.

**Validation:**

- Database names must match `/^[a-z0-9_-]+$/i`
- Memory databases (`:memory:`) bypass validation

**Example:**

```php
$client = new Client(':memory:');

// Select or create database
$db = $client->selectDB('myApp');
// Creates new Database instance for 'myApp'

// Use the database
$users = $db->users;
$users->insert(['name' => 'John']);

// Database names are validated
try {
    $db = $client->selectDB('invalid@name');
} catch (InvalidArgumentException $e) {
    echo 'Invalid database name: ' . $e->getMessage();
}
```

### \_\_get()

```php
public function __get(string $database): Database
```

**Parameters:**

- `$database` (string): Name of the database

**Returns:**

- `Database`: Database instance

**Description:**
Magic method to access databases using property syntax.

**Example:**

```php
$client = new Client(':memory:');

// Access database using property syntax
$users = $client->myApp->users;
$users->insert(['name' => 'John']);

// Equivalent to:
$db = $client->selectDB('myApp');
$users = $db->users;
```

### close()

```php
public function close(): void
```

**Description:**
Closes all database connections held by this client. Called automatically in destructor.

**Example:**

```php
$client = new Client('./data');

// Use databases
$client->myApp->users->insert(['name' => 'John']);

// Close all connections
$client->close();

// All database connections are now closed
```

### \_\_destruct()

```php
public function __destruct()
```

**Description:**
Destructor that automatically calls `close()` to clean up database connections.

**Example:**

```php
function processUserData() {
    $client = new Client(':memory:');
    $client->myApp->users->insert(['name' => 'John']);
    // No need to explicitly close - done automatically
}

processUserData(); // Database connections closed automatically
```

## Helper Methods

### getValueByDot()

```php
private function getValueByDot(array $data, string $path)
```

**Parameters:**

- `$data` (array): The data array to search in
- `$path` (string): Dot notation path (e.g., 'user.profile.name')

**Returns:**

- `mixed`: The value found at the path or null if not found

**Description:**
Helper method to fetch nested values using dot notation from arrays.

**Example:**

```php
$data = [
    'user' => [
        'profile' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ],
        'settings' => ['theme' => 'dark']
    ]
];

$value = $client->getValueByDot($data, 'user.profile.name');
// Returns: 'John Doe'

$email = $client->getValueByDot($data, 'user.profile.email');
// Returns: 'john@example.com'

$nonExistent = $client->getValueByDot($data, 'user.profile.phone');
// Returns: null
```

## Complete Usage Examples

### Basic Database Operations

```php
<?php
require_once 'src/Client.php';

use PocketDB\Client;

// Create client
$client = new Client('./mydata');

// List existing databases
$databases = $client->listDBs();
echo "Available databases: " . implode(', ', $databases) . "\n";

// Select or create database
$db = $client->selectDB('myApp');

// Use magic property access
$users = $client->myApp->users;
$products = $client->myApp->products;

// Insert data
$users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

$users->insert([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

// Query data
$john = $users->findOne(['email' => 'john@example.com']);
echo "Found user: " . $john['name'] . "\n";

// Update data
$users->update(['email' => 'john@example.com'], [
    'name' => 'Johnathan Doe'
]);

// Find all users
$allUsers = $users->find()->toArray();
foreach ($allUsers as $user) {
    echo "User: " . $user['name'] . " (" . $user['email'] . ")\n";
}

// Clean up
$client->close();
?>
```

### Multiple Database Management

```php
<?php
require_once 'src/Client.php';

use PocketDB\Client;

// Create client
$client = new Client('./multidb');

// Create and use multiple databases
$usersDb = $client->selectDB('users');
$productsDb = $client->selectDB('products');
$ordersDb = $client->selectDB('orders');

// Insert data in different databases
$usersDb->users->insert([
    'id' => 'usr_001',
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

$productsDb->products->insert([
    'id' => 'prod_001',
    'name' => 'Laptop',
    'price' => 999.99
]);

$ordersDb->orders->insert([
    'id' => 'ord_001',
    'user_id' => 'usr_001',
    'product_id' => 'prod_001',
    'quantity' => 1,
    'total' => 999.99
]);

// List all databases
$databases = $client->listDBs();
echo "Databases: " . implode(', ', $databases) . "\n";

// Access collections directly
$user = $client->users->users->findOne(['id' => 'usr_001']);
$product = $client->products->products->findOne(['id' => 'prod_001']);

echo "User: " . $user['name'] . "\n";
echo "Product: " . $product['name'] . "\n";

$client->close();
?>
```

### Error Handling

```php
<?php
require_once 'src/Client.php';

use PocketDB\Client;

$client = new Client('./error_handling');

try {
    // Valid database name
    $db = $client->selectDB('valid_name');
    echo "Database created successfully\n";
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    // Invalid database name with special characters
    $db = $client->selectDB('invalid@name');
} catch (InvalidArgumentException $e) {
    echo "Expected error: " . $e->getMessage() . "\n";
}

// Using magic property access
try {
    $db = $client->invalid@name; // This will also throw an error
} catch (Error $e) {
    echo "Magic property error: " . $e->getMessage() . "\n";
}

$client->close();
?>
```

### Memory vs File Database

```php
<?php
require_once 'src/Client.php';

use PocketDB\Client;

// Memory database - data is lost when script ends
$memoryClient = new Client(':memory:');
$memoryDb = $memoryClient->selectDB('temp');
$memoryDb->test->insert(['data' => 'in_memory']);
echo "Memory database data: " . json_encode($memoryDb->test->find()->toArray()) . "\n";

// File database - data persists
$fileClient = new Client('./persistent');
$fileDb = $fileClient->selectDB('persistent');
$fileDb->test->insert(['data' => 'persistent']);
echo "File database data: " . json_encode($fileDb->test->find()->toArray()) . "\n";

// List databases
echo "Memory databases: " . implode(', ', $memoryClient->listDBs()) . "\n";
echo "File databases: " . implode(', ', $fileClient->listDBs()) . "\n";

// Clean up
$memoryClient->close();
$fileClient->close();
?>
```

## Best Practices

1. **Always close connections**: Use `close()` or let the destructor handle it
2. **Validate database names**: Use alphanumeric characters, underscores, and hyphens
3. **Use appropriate paths**: For file databases, ensure the directory exists and is writable
4. **Handle exceptions**: Catch `InvalidArgumentException` for invalid database names
5. **Memory vs File**: Use memory databases for temporary data, file databases for persistent data
