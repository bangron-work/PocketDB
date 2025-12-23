# Installation and Setup Guide

This guide will walk you through installing and setting up PocketDB in your PHP project.

## Repository Information

**GitHub Repository:** https://github.com/bangron-work/PocketDB

**Installation via Composer:**

```bash
composer require bangron-work/pocketdb
```

## Requirements

- PHP 7.4 or higher
- PDO SQLite extension enabled
- OpenSSL extension (for encryption features)

## System Requirements

### PHP Extensions

Make sure you have the following PHP extensions installed and enabled:

```bash
# Required extensions
pdo_sqlite
openssl

# Optional but recommended
json
mbstring
```

Check if extensions are enabled:

```bash
php -m | grep -E '(pdo_sqlite|openssl|json|mbstring)'
```

If any extension is missing, install it:

**On Ubuntu/Debian:**

```bash
sudo apt-get install php-pdo-sqlite php-openssl php-json php-mbstring
```

**On CentOS/RHEL:**

```bash
sudo yum install php-pdo-sqlite php-openssl php-json php-mbstring
```

**Using Docker:**

```dockerfile
FROM php:8.1-cli
RUN docker-php-ext-install pdo_sqlite openssl json mbstring
```

### PHP Version Requirements

PocketDB requires PHP 7.4 or higher. Check your PHP version:

```bash
php -v
```

## Installation Methods

### 1. Direct Download

1. Download the latest release from the GitHub repository
2. Extract the files to your project directory
3. Include the necessary files in your PHP script

```bash
# Download and extract
wget https://github.com/your-repo/pocketdb/archive/main.zip
unzip main.zip
mv pocketdb-main/src /path/to/your/project/pocketdb
```

### 2. Git Clone

```bash
git clone https://github.com/your-repo/pocketdb.git
cd pocketdb
# Copy src directory to your project
cp -r src /path/to/your/project/pocketdb
```

### 3. Composer (Recommended)

If you use Composer, add PocketDB to your project:

```bash
composer require your-vendor/pocketdb
```

Or add to your `composer.json`:

```json
{
  "require": {
    "your-vendor/pocketdb": "^1.0"
  }
}
```

Then run:

```bash
composer install
```

## Basic Setup

### 1. Include PocketDB in Your Project

```php
<?php
// Include the main Client class
require_once 'path/to/pocketdb/src/Client.php';

// Use the namespace
use PocketDB\Client;
?>
```

### 2. Create Your First Client

```php
<?php
require_once 'path/to/pocketdb/src/Client.php';

use PocketDB\Client;

// Create a client for in-memory database
$client = new Client(':memory:');

// Create a client for file-based database
$client = new Client('./data/mydatabase.sqlite');

// Create client with options
$client = new Client('./data/mydatabase.sqlite', [
    'encryption_key' => 'your-secret-key-here'
]);
?>
```

### 3. Basic Usage Example

```php
<?php
require_once 'path/to/pocketdb/src/Client.php';

use PocketDB\Client;

// Create client
$client = new Client(':memory:');

// Select or create a database
$db = $client->myApp;

// Select or create a collection
$users = $db->users;

// Insert data
$user = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Query data
$foundUser = $users->findOne(['email' => 'john@example.com']);
echo "Found user: " . $foundUser['name'] . "\n";

// Clean up
$client->close();
?>
```

## Configuration Options

### Client Options

```php
$client = new Client('./data', [
    // Database connection options (passed to PDO)
    'PDO::ATTR_ERRMODE' => PDO::ERRMODE_EXCEPTION,
    'PDO::ATTR_DEFAULT_FETCH_MODE' => PDO::FETCH_ASSOC,

    // Custom options
    'encryption_key' => 'your-secret-key', // Optional encryption key
]);
```

### Database Options

```php
$db = new Database('./data/mydb.sqlite', [
    'encryption_key' => 'database-secret-key',
    'PDO::ATTR_TIMEOUT' => 30,
]);
```

## Directory Structure

After installation, your project should have a structure like this:

```
your-project/
├── src/
│   └── pocketdb/
│       ├── Client.php
│       ├── Database.php
│       ├── Collection.php
│       └── Cursor.php
├── data/                 # For database files
├── docs/                 # Documentation
└── your-app.php          # Your application code
```

## Environment Setup

### Development Environment

For development, you might want to use environment variables:

```php
<?php
// config.php
return [
    'database_path' => getenv('POCKETDB_PATH') ?: ':memory:',
    'encryption_key' => getenv('POCKETDB_ENCRYPTION_KEY'),
    'debug' => getenv('POCKETDB_DEBUG') === 'true'
];
?>
```

```php
<?php
// app.php
require_once 'config.php';
require_once 'src/pocketdb/Client.php';

use PocketDB\Client;

$config = include 'config.php';

$client = new Client($config['database_path'], [
    'encryption_key' => $config['encryption_key']
]);

// Your application code...
?>
```

### Production Environment

For production:

1. **Set proper file permissions**:

```bash
chmod 755 data/
chmod 644 data/*.sqlite
```

2. **Use absolute paths**:

```php
$client = new Client('/var/www/data/mydatabase.sqlite');
```

3. **Enable error logging**:

```php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/pocketdb_errors.log');
```

## Testing Your Installation

### Basic Test Script

Create a test file to verify everything is working:

```php
<?php
// test.php
require_once 'src/pocketdb/Client.php';

use PocketDB\Client;

try {
    // Create client
    $client = new Client(':memory:');

    // Test database creation
    $db = $client->testDB;

    // Test collection creation
    $collection = $db->testCollection;

    // Test insert and find
    $result = $collection->insert(['test' => 'value']);
    $found = $collection->findOne(['test' => 'value']);

    if ($found && $found['test'] === 'value') {
        echo "✓ PocketDB is working correctly!\n";
    } else {
        echo "✗ PocketDB test failed\n";
    }

    $client->close();

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
```

Run the test:

```bash
php test.php
```

### Advanced Test Script

```php
<?php
// advanced_test.php
require_once 'src/pocketdb/Client.php';

use PocketDB\Client;

try {
    echo "Testing PocketDB installation...\n";

    // Test 1: Memory database
    echo "1. Testing memory database... ";
    $client1 = new Client(':memory:');
    $db1 = $client1->testMemory;
    $db1->createCollection('users');
    $db1->users->insert(['name' => 'Test User']);
    $count = $db1->users->count();
    if ($count === 1) {
        echo "✓ Passed\n";
    } else {
        echo "✗ Failed\n";
    }
    $client1->close();

    // Test 2: File database
    echo "2. Testing file database... ";
    $client2 = new Client('./test_database.sqlite');
    $db2 = $client2->testFile;
    $db2->createCollection('products');
    $db2->products->insertMany([
        ['name' => 'Product 1', 'price' => 10.99},
        ['name' => 'Product 2', 'price' => 19.99}
    ]);
    $count = $db2->products->count();
    if ($count === 2) {
        echo "✓ Passed\n";
    } else {
        echo "✗ Failed\n";
    }
    $client2->close();

    // Test 3: Encryption
    echo "3. Testing encryption... ";
    $client3 = new Client(':memory:', ['encryption_key' => 'test-key']);
    $db3 = $client3->testEncrypted;
    $db3->createCollection('secrets');
    $db3->secrets->insert(['password' => 'secret123']);
    $found = $db3->secrets->findOne(['password' => 'secret123']);
    if ($found && $found['password'] === 'secret123') {
        echo "✓ Passed\n";
    } else {
        echo "✗ Failed\n";
    }
    $client3->close();

    // Test 4: Complex queries
    echo "4. Testing complex queries... ";
    $client4 = new Client(':memory:');
    $db4 = $client4->testQuery;
    $db4->createCollection('complex');
    $db4->complex->insertMany([
        ['name' => 'John', 'age' => 25, 'active' => true],
        ['name' => 'Jane', 'age' => 30, 'active' => true],
        ['name' => 'Bob', 'age' => 35, 'active' => false]
    ]);

    // Test find with criteria
    $active = $db4->complex->find(['active' => true])->count();
    $older = $db4->complex->find(['age' => ['$gt' => 28]])->count();

    if ($active === 2 && $older === 2) {
        echo "✓ Passed\n";
    } else {
        echo "✗ Failed\n";
    }
    $client4->close();

    echo "\n✓ All tests completed!\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
```

## Troubleshooting

### Common Issues

#### 1. "Class 'PocketDB\\Client' not found"

**Cause**: Incorrect file path or missing include
**Solution**:

```php
// Check the file path
require_once 'absolute/path/to/pocketdb/src/Client.php';

// Or use autoloading
spl_autoload_register(function ($class) {
    if (strpos($class, 'PocketDB\\') === 0) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});
```

#### 2. "PDO SQLite extension not loaded"

**Cause**: Missing PHP extension
**Solution**:

```bash
# Check if extension is loaded
php -m | grep pdo_sqlite

# Install the extension
# On Ubuntu/Debian:
sudo apt-get install php-pdo-sqlite

# On CentOS/RHEL:
sudo yum install php-pdo-sqlite

# Restart PHP/Apache
sudo systemctl restart apache2
```

#### 3. "Directory does not exist" for file databases

**Cause**: Database directory doesn't exist or is not writable
**Solution**:

```php
// Create directory if it doesn't exist
$dbPath = './data';
if (!file_exists($dbPath)) {
    mkdir($dbPath, 0755, true);
}

// Use absolute path
$client = new Client('/var/www/data/mydatabase.sqlite');
```

#### 4. "Encryption key not working"

**Cause**: OpenSSL extension missing or key format issues
**Solution**:

```bash
# Check OpenSSL extension
php -m | grep openssl

# Test encryption
$encrypted = openssl_encrypt('test', 'AES-256-CBC', 'key');
if ($encrypted === false) {
    echo "OpenSSL encryption failed\n";
}
```

#### 5. "Query not returning results"

**Cause**: Query syntax or data issues
**Solution**:

```php
// Debug the query
$criteria = ['email' => 'user@example.com'];
echo "Query criteria: " . json_encode($criteria) . "\n";

// Check if data exists
$all = $collection->find()->toArray();
echo "Total documents: " . count($all) . "\n";

// Try simpler query
$simpleResult = $collection->findOne();
var_dump($simpleResult);
```

### Performance Optimization

#### 1. Enable SQLite Write-Ahead Logging (WAL)

PocketDB enables WAL by default, but you can configure it:

```php
$db = new Database(':memory:');
$db->connection->exec('PRAGMA journal_mode = WAL');
$db->connection->exec('PRAGMA synchronous = NORMAL');
```

#### 2. Create Indexes for Frequent Queries

```php
// Create JSON indexes for frequently queried fields
$collection->createIndex('email');
$collection->createIndex('status');
$collection->createIndex('created_at');
```

#### 3. Use Searchable Fields for Text Search

```php
$users = $db->users;
$users->setSearchableFields(['username', 'email'], false);
$users->setSearchableFields(['ssn'], true); // Hashed for privacy
```

#### 4. Connection Pooling

For multiple requests, reuse the same client instance:

```php
// In your application bootstrap
global $pocketdbClient;
if (!isset($pocketdbClient)) {
    $pocketdbClient = new Client('./data/mydatabase.sqlite');
}

// Use the global client throughout your application
```

## IDE Integration

### PhpStorm

1. Add PocketDB to your project's include path:

   - File → Settings → PHP → Include Path
   - Add the directory containing PocketDB files

2. Configure PHP Interpreter:
   - File → Settings → PHP → CLI Interpreter
   - Ensure SQLite and OpenSSL extensions are enabled

### VS Code

1. Install PHP Intelephense extension
2. Add to your `.vscode/settings.json`:

```json
{
  "php.validate.executablePath": "/usr/bin/php",
  "php.executablePath": "/usr/bin/php"
}
```

3. Add PocketDB to your workspace:

```json
{
  "php.suggest.basic": true,
  "php.validate.enable": true
}
```

## Docker Setup

### Dockerfile

```dockerfile
FROM php:8.1-cli

# Install required extensions
RUN docker-php-ext-install pdo_sqlite openssl json mbstring

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Run your application
CMD ["php", "your-app.php"]
```

### docker-compose.yml

```yaml
version: "3.8"

services:
  app:
    build: .
    volumes:
      - ./data:/app/data
    environment:
      - POCKETDB_PATH=/app/data/database.sqlite
      - POCKETDB_ENCRYPTION_KEY=your-secret-key
```

### Run with Docker

```bash
# Build image
docker build -t pocketdb-app .

# Run container
docker run -v $(pwd)/data:/app/data pocketdb-app

# Or with docker-compose
docker-compose up
```

## Deployment Checklist

- [ ] Verify PHP version (7.4+)
- [ ] Check required extensions (pdo_sqlite, openssl)
- [ ] Set proper file permissions
- [ ] Configure database paths
- [ ] Set up encryption keys
- [ ] Create backup strategy
- [ ] Set up logging
- [ ] Test in production environment
- [ ] Monitor performance
- [ ] Document configuration

## Getting Help

If you encounter issues:

1. Check the [troubleshooting section](#troubleshooting)
2. Review the [full documentation](../index.md)
3. Search existing issues on GitHub
4. Create a new issue with:
   - PHP version
   - Error message and stack trace
   - Minimal reproduction code
   - Environment details

## Next Steps

After completing the installation:

1. Read the [Client documentation](client.md) to learn about basic operations
2. Explore the [Collection documentation](collection.md) for data manipulation
3. Check the [Database documentation](database.md) for advanced features
4. Review the [Utilities documentation](utilities.md) for helper functions
5. Try the [examples](../examples/) directory for more complex use cases
