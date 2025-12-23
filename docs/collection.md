# Collection Class Documentation

The `Collection` class is the core workhorse for data operations in PocketDB. It provides methods for CRUD operations, data validation, indexing, encryption, and advanced features like hooks and searchable fields.

## Constructor

```php
public function __construct(string $name, Database $database)
```

**Parameters:**

- `$name` (string): Name of the collection
- `$database` (Database): Database instance that owns this collection

## Constants

### ID Generation Modes

```php
public const ID_MODE_AUTO = 'auto';          // Generate UUID v4 automatically
public const ID_MODE_MANUAL = 'manual';      // Use provided _id only
public const ID_MODE_PREFIX = 'prefix';      // Generate with prefix
```

## Properties

### `$database`

```php
public Database $database
```

The database instance that owns this collection.

### `$name`

```php
public string $name
```

The name of this collection.

### `$encryptionKey`

```php
protected ?string $encryptionKey = null
```

Optional per-collection encryption key (overrides database-level encryption).

### `$searchableFields`

```php
protected array $searchableFields = []
```

Configuration for searchable fields. Maps field names to ['hash' => bool].

### `$idMode`

```php
protected $idMode = self::ID_MODE_AUTO
```

Current ID generation mode.

### `$idPrefix`

```php
protected ?string $idPrefix = null
```

Prefix for ID generation in prefix mode.

### `$idCounter`

```php
protected int $idCounter = 0
```

Auto-increment counter for prefix mode.

### `$hooks`

```php
protected array $hooks = []
```

Event hooks storage: event name => list of callables.

## Methods

### setIdModeAuto()

```php
public function setIdModeAuto(): self
```

**Description:**
Sets ID generation mode to AUTO (UUID v4). Automatically generates unique IDs for documents that don't have an `_id` field.

**Example:**

```php
$users = $db->users;
$users->setIdModeAuto();

// Insert without _id - auto-generated UUID will be used
$user = $users->insert(['name' => 'John', 'email' => 'john@example.com']);
echo "Generated ID: " . $user['_id'] . "\n";
// Output: Generated ID: 550e8400-e29b-41d4-a716-446655440000 (example UUID)
```

### setIdModeManual()

```php
public function setIdModeManual(): self
```

**Description:**
Sets ID generation mode to MANUAL. Requires documents to have their own `_id` field.

**Example:**

```php
$users = $db->users;
$users->setIdModeManual();

// Insert with custom _id
$user = $users->insert([
    '_id' => 'user_001',
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Try to insert without _id - will fail
try {
    $users->insert(['name' => 'Jane']); // Missing _id
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### setIdModePrefix()

```php
public function setIdModePrefix(string $prefix): self
```

**Parameters:**

- `$prefix` (string): Prefix for generated IDs (e.g., 'USR', 'PRD', 'ORD')

**Description:**
Sets ID generation mode to PREFIX. Generates IDs with the specified prefix and auto-incrementing number.

**Example:**

```php
$users = $db->users;
$users->setIdModePrefix('USR');

// Insert users
$user1 = $users->insert(['name' => 'John', 'email' => 'john@example.com']);
$user2 = $users->insert(['name' => 'Jane', 'email' => 'jane@example.com']);

echo "User IDs: " . $user1['_id'] . ", " . $user2['_id'] . "\n";
// Output: User IDs: USR-000001, USR-000002
```

### setEncryptionKey()

```php
public function setEncryptionKey(?string $key): self
```

**Parameters:**

- `$key` (string, optional): Encryption key or null to disable

**Description:**
Sets per-collection encryption key. This overrides the database-level encryption key.

**Example:**

```php
$users = $db->users;
$users->setEncryptionKey('collection-secret-key');

// Insert sensitive data
$users->insert([
    'name' => 'John',
    'ssn' => '123-45-6789',
    'password_hash' => 'hashed_password'
]);

// The data is encrypted at collection level
```

### setSearchableFields()

```php
public function setSearchableFields(array $fields, bool $hash = false): self
```

**Parameters:**

- `$fields` (array): Array of field names to make searchable
- `$hash` (bool): Whether to hash the values (for privacy-preserving search)

**Description:**
Configures searchable fields. Each field will be stored in a dedicated `si_{field}` TEXT column. If `$hash` is true, the stored value will be a hex SHA-256 hash.

**Example:**

```php
$users = $db->users;
$users->setSearchableFields(['email', 'username'], false);
$users->setSearchableFields(['ssn'], true); // Hashed for privacy

// Insert user
$users->insert([
    'name' => 'John',
    'email' => 'john@example.com',
    'username' => 'john_doe',
    'ssn' => '123-45-6789'
]);

// Search by email (fast, indexed)
$results = $users->find(['email' => 'john@example.com']);

// Search by username (fast, indexed)
$results = $users->find(['username' => 'john_doe']);

// Search by SSN (hashed, private)
$results = $users->find(['ssn' => '123-45-6789']);
```

### removeSearchableField()

```php
public function removeSearchableField(string $field, bool $dropColumn = false): self
```

**Parameters:**

- `$field` (string): Field name to remove
- `$dropColumn` (bool): Whether to drop the physical column

**Description:**
Removes a searchable field configuration. If `$dropColumn` is true, it rebuilds the table without that column.

**Example:**

```php
$users = $db->users;
$users->setSearchableFields(['email', 'username']);

// Remove searchable field
$users->removeSearchableField('email', true); // Drop the column

// Email is no longer searchable efficiently
```

### drop()

```php
public function drop()
```

**Description:**
Drops the entire collection from the database.

**Example:**

```php
$tempCollection = $db->temp_data;
$tempCollection->insert(['data' => 'test']);

// Drop the collection
$tempCollection->drop();

// Collection no longer exists
```

### insert()

```php
public function insert(array $document = []): mixed
```

**Parameters:**

- `$document` (array): Document to insert

**Returns:**

- `mixed`: Last insert ID for single document, count for multiple documents

**Description:**
Inserts a document or array of documents into the collection. Handles ID generation based on the current mode.

**Example:**

```php
$users = $db->users;

// Single document
$user = $users->insert([
    'name' => 'John',
    'email' => 'john@example.com',
    'age' => 30
]);
echo "Inserted with ID: " . $user['_id'] . "\n";

// Multiple documents
$count = $users->insert([
    ['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 25],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 35]
]);
echo "Inserted $count users\n";
```

### insertMany()

```php
public function insertMany(array $documents): int
```

**Parameters:**

- `$documents` (array): Array of documents to insert

**Returns:**

- `int`: Number of documents inserted

**Description:**
Alias for `insert()` when passing an array of documents.

**Example:**

```php
$users = $db->users;

$users->insertMany([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com'],
    ['name' => 'David', 'email' => 'david@example.com']
]);
```

### save()

```php
public function save(array $document, bool $create = false): mixed
```

**Parameters:**

- `$document` (array): Document to save
- `$create` (bool): Whether to create if not exists

**Returns:**

- `mixed`: Document ID or false on failure

**Description:**
Saves a document. If `_id` exists, updates the document. Otherwise, inserts it (unless `$create` is false).

**Example:**

```php
$users = $db->users;

// Insert new user
$user = $users->save([
    '_id' => 'user_001',
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Update existing user
$users->save([
    '_id' => 'user_001',
    'name' => 'Johnathan',
    'email' => 'john@example.com',
    'age' => 30
]);
```

### update()

```php
public function update($criteria, array $data, bool $merge = true): int
```

**Parameters:**

- `$criteria` (mixed): Criteria to match documents
- `$data` (array): Data to update
- `$merge` (bool): Whether to merge with existing data

**Returns:**

- `int`: Number of documents updated

**Description:**
Updates documents matching the criteria. If `$merge` is true, merges the new data with existing data.

**Example:**

```php
$users = $db->users;

// Update users older than 30
$updated = $users->update(
    ['age' => ['$gt' => 30]],
    ['status' => 'senior'],
    true // merge with existing data
);
echo "Updated $updated users\n";

// Replace data instead of merging
$users->update(
    ['email' => 'john@example.com'],
    ['name' => 'John Smith', 'verified' => true],
    false // replace existing data
);
```

### remove()

```php
public function remove($criteria): int
```

**Parameters:**

- `$criteria` (mixed): Criteria to match documents

**Returns:**

- `int`: Number of documents removed

**Description:**
Removes documents matching the criteria.

**Example:**

```php
$users = $db->users;

// Remove inactive users
$removed = $users->remove(['status' => 'inactive']);
echo "Removed $removed inactive users\n";

// Remove user by email
$users->remove(['email' => 'old@example.com']);
```

### find()

```php
public function find($criteria = null, $projection = null): Cursor
```

**Parameters:**

- `$criteria` (mixed): Criteria to match documents
- `$projection` (mixed): Fields to include/exclude

**Returns:**

- `Cursor`: Cursor for iterating results

**Description:**
Finds documents matching the criteria. Returns a Cursor for efficient iteration.

**Example:**

```php
$users = $db->users;

// Find all users
$allUsers = $users->find();

// Find active users older than 25
$activeUsers = $users->find([
    'status' => 'active',
    'age' => ['$gt' => 25]
]);

// Find with projection (only name and email)
$userNames = $users->find(
    ['status' => 'active'],
    ['name' => 1, 'email' => 1]
);
```

### findOne()

```php
public function findOne($criteria = null, $projection = null): ?array
```

**Parameters:**

- `$criteria` (mixed): Criteria to match documents
- `$projection` (mixed): Fields to include/exclude

**Returns:**

- `?array`: First matching document or null

**Description:**
Finds the first document matching the criteria.

**Example:**

```php
$users = $db->users;

// Find user by email
$user = $users->findOne(['email' => 'john@example.com']);
if ($user) {
    echo "Found user: {$user['name']}\n";
}

// Find with projection
$userEmail = $users->findOne(
    ['email' => 'john@example.com'],
    ['email' => 1]
);
```

### count()

```php
public function count($criteria = null): int
```

**Parameters:**

- `$criteria` (mixed): Criteria to match documents

**Returns:**

- `int`: Number of matching documents

**Description:**
Counts documents matching the criteria.

**Example:**

```php
$users = $db->users;

// Count all users
$totalUsers = $users->count();
echo "Total users: $totalUsers\n";

// Count active users
$activeUsers = $users->count(['status' => 'active']);
echo "Active users: $activeUsers\n";

// Count users older than 30
$seniorUsers = $users->count(['age' => ['$gt' => 30]]);
```

### createIndex()

```php
public function createIndex(string $field, ?string $indexName = null): void
```

**Parameters:**

- `$field` (string): Field to create index on
- `$indexName` (string, optional): Custom index name

**Description:**
Creates a JSON index for a field using the database's createJsonIndex method.

**Example:**

```php
$users = $db->users;

// Create index on email field
$users->createIndex('email');

// Create custom index name
$users->createIndex('age', 'idx_users_age');
```

### renameCollection()

```php
public function renameCollection($newname): bool
```

**Parameters:**

- `$newname` (string): New collection name

**Returns:**

- `bool`: True on success, false if name already exists

**Description:**
Renames the collection to a new name.

**Example:**

```php
$users = $db->users;

// Rename collection
$success = $users->renameCollection('customers');
if ($success) {
    echo "Collection renamed successfully\n";
} else {
    echo "Collection name already exists\n";
}
```

### on()

```php
public function on(string $event, callable $fn): void
```

**Parameters:**

- `$event` (string): Event name ('beforeInsert', 'afterInsert', 'beforeUpdate', 'afterUpdate', 'beforeRemove', 'afterRemove')
- `$fn` (callable): Callback function

**Description:**
Registers an event hook for the collection.

**Example:**

```php
$users = $db->users;

// Before insert hook
$users->on('beforeInsert', function($document) {
    // Auto-set created_at timestamp
    $document['created_at'] = date('Y-m-d H:i:s');
    return $document;
});

// After insert hook
$users->on('afterInsert', function($document, $id) {
    // Log the insertion
    error_log("User created: $id - {$document['name']}");
});

// Before update hook
$users->on('beforeUpdate', function($criteria, $data) {
    // Prevent updating email
    if (isset($data['email'])) {
        unset($data['email']);
    }
    return ['criteria' => $criteria, 'data' => $data];
});
```

### off()

```php
public function off(string $event, ?callable $fn = null): void
```

**Parameters:**

- `$event` (string): Event name
- `$fn` (callable, optional): Specific callback to remove

**Description:**
Removes event hooks. If `$fn` is null, removes all hooks for the event.

**Example:**

```php
$users = $db->users;

// Remove specific hook
$users->off('beforeInsert', $myHookFunction);

// Remove all hooks for an event
$users->off('afterInsert');
```

### populate()

```php
public function populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed
```

**Parameters:**

- `$documents` (array): Documents to populate
- `$localField` (string): Field in local documents to reference foreign collection
- `$foreign` (string): Foreign collection name or 'db.collection'
- `$foreignField` (string): Field in foreign collection to match (default: '\_id')
- `$as` (string, optional): Field name to store populated data

**Returns:**

- `mixed`: Populated documents (array or single document)

**Description:**
Populates references in documents with data from foreign collections.

**Example:**

```php
$users = $db->users;
$posts = $db->posts;

// Insert users
$users->insertMany([
    ['_id' => 'user_1', 'name' => 'John'],
    ['_id' => 'user_2', 'name' => 'Jane']
]);

// Insert posts
$posts->insertMany([
    ['_id' => 'post_1', 'title' => 'Hello', 'author_id' => 'user_1'],
    ['_id' => 'post_2', 'title' => 'World', 'author_id' => 'user_2'}
]);

// Get posts and populate author information
$postsWithAuthors = $posts->populate(
    $posts->find()->toArray(),
    'author_id',
    'users',
    '_id',
    'author'
);

foreach ($postsWithAuthors as $post) {
    echo "Post: {$post['title']} by {$post['author']['name']}\n";
}
```

## Helper Methods

### encodeStored()

```php
protected function encodeStored(array $doc): string
```

**Parameters:**

- `$doc` (array): Document to encode

**Returns:**

- `string`: JSON string ready for storage

**Description:**
Encodes a document for storage. If encryption is enabled, encrypts the document with AES-256-CBC.

### decodeStored()

```php
public function decodeStored(string $stored): ?array
```

**Parameters:**

- `$stored` (string): Stored document string

**Returns:**

- `?array`: Decoded document array or null

**Description:**
Decodes a stored document string. Handles decryption if the document was encrypted.

### \_generateId()

```php
protected function _generateId(): ?string
```

**Returns:**

- `?string`: Generated ID or null for manual mode

**Description:**
Generates ID based on the current mode (AUTO, MANUAL, PREFIX).

## Complete Usage Examples

### Basic CRUD Operations

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('myApp');
$users = $db->users;

// Create users collection
$db->createCollection('users');

// Insert users
$user1 = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'status' => 'active'
]);

$user2 = $users->insert([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'age' => 25,
    'status' => 'active'
]);

echo "Inserted users with IDs: {$user1['_id']}, {$user2['_id']}\n";

// Find all users
$allUsers = $users->find()->toArray();
foreach ($allUsers as $user) {
    echo "User: {$user['name']} ({$user['email']})\n";
}

// Find one user
$john = $users->findOne(['email' => 'john@example.com']);
echo "Found user: {$john['name']}, Age: {$john['age']}\n";

// Update user
$updated = $users->update(
    ['email' => 'john@example.com'],
    ['age' => 31, 'last_updated' => date('Y-m-d H:i:s')]
);
echo "Updated $updated user(s)\n";

// Count users
$total = $users->count();
$active = $users->count(['status' => 'active']);
echo "Total users: $total, Active: $active\n";

// Remove user
$removed = $users->remove(['email' => 'jane@example.com']);
echo "Removed $removed user(s)\n";

$client->close();
?>
```

### ID Generation Modes

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('test');

// AUTO mode (default)
$autoUsers = $db->createCollection('auto_users');
$autoUsers->setIdModeAuto();

$user1 = $autoUsers->insert(['name' => 'John']);
$user2 = $autoUsers->insert(['name' => 'Jane']);
echo "AUTO IDs: {$user1['_id']}, {$user2['_id']}\n";

// MANUAL mode
$manualUsers = $db->createCollection('manual_users');
$manualUsers->setIdModeManual();

$user3 = $manualUsers->insert([
    '_id' => 'custom_001',
    'name' => 'Bob'
]);
echo "Manual ID: {$user3['_id']}\n";

// PREFIX mode
$prefixUsers = $db->createCollection('prefix_users');
$prefixUsers->setIdModePrefix('USR');

$user4 = $prefixUsers->insert(['name' => 'Alice']);
$user5 = prefixUsers->insert(['name' => 'Charlie']);
echo "PREFIX IDs: {$user4['_id']}, {$user5['_id']}\n";

$client->close();
?>
```

### Encryption Support

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('secure');

// Create encrypted collection
$users = $db->users;
$users->setEncryptionKey('my-secret-key-123');

// Insert sensitive data
$users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'ssn' => '123-45-6789',
    'credit_card' => '4111111111111111'
]);

// Query data (automatically decrypted)
$user = $users->findOne(['email' => 'john@example.com']);
echo "User: {$user['name']}\n";
echo "SSN: {$user['ssn']}\n";
echo "Credit Card: {$user['credit_card']}\n";

// Check encrypted data in database
$stmt = $db->connection->query("SELECT document FROM users");
$encrypted = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Encrypted in DB: " . substr($encrypted['document'], 0, 50) . "...\n";

$client->close();
?>
```

### Searchable Fields

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('search');

// Create users with searchable fields
$users = $db->users;
$users->setSearchableFields(['email', 'username'], false);
$users->setSearchableFields(['ssn'], true); // Hashed for privacy

// Insert users
$users->insertMany([
    [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'username' => 'john_doe',
        'ssn' => '123-45-6789'
    ],
    [
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'username' => 'jane_smith',
        'ssn' => '987-65-4321'
    ]
]);

// Search by email (fast, indexed)
$emailResults = $users->find(['email' => 'john@example.com']);
echo "Found by email: " . count($emailResults) . " user(s)\n";

// Search by username (fast, indexed)
$usernameResults = $users->find(['username' => 'jane_smith']);
echo "Found by username: " . count($usernameResults) . " user(s)\n";

// Search by SSN (hashed, private)
$ssnResults = $users->find(['ssn' => '123-45-6789']);
echo "Found by SSN: " . count($ssnResults) . " user(s)\n";

// Check searchable columns
$stmt = $db->connection->query("PRAGMA table_info(users)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Searchable columns: ";
$searchable = [];
foreach ($columns as $col) {
    if (strpos($col['name'], 'si_') === 0) {
        $searchable[] = $col['name'];
    }
}
echo implode(', ', $searchable) . "\n";

$client->close();
?>
```

### Event Hooks

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('hooks');
$users = $db->users;

// Before insert hook - auto-timestamp
$users->on('beforeInsert', function($document) {
    if (!isset($document['created_at'])) {
        $document['created_at'] = date('Y-m-d H:i:s');
    }
    return $document;
});

// After insert hook - logging
$users->on('afterInsert', function($document, $id) {
    error_log("User created: $id - {$document['name']} at {$document['created_at']}");
});

// Before update hook - prevent email change
$users->on('beforeUpdate', function($criteria, $data) {
    if (isset($data['email'])) {
        error_log("Attempt to change email blocked");
        unset($data['email']);
    }
    return ['criteria' => $criteria, 'data' => $data];
});

// Insert user (triggers beforeInsert)
$user = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update user (triggers beforeUpdate)
$users->update(['_id' => $user['_id']], ['name' => 'Johnathan']);

// Check the user
$updatedUser = $users->findOne(['_id' => $user['_id']]);
echo "User: {$updatedUser['name']}, Email: {$updatedUser['email']}\n";

$client->close();
?>
```

### Data Population

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('populate');

// Create collections
$users = $db->users;
$posts = $db->posts;
$comments = $db->comments;

// Insert users
$users->insertMany([
    ['_id' => 'user_1', 'name' => 'John', 'email' => 'john@example.com'],
    ['_id' => 'user_2', 'name' => 'Jane', 'email' => 'jane@example.com'],
    ['_id' => 'user_3', 'name' => 'Bob', 'email' => 'bob@example.com']
]);

// Insert posts
$posts->insertMany([
    [
        '_id' => 'post_1',
        'title' => 'Hello World',
        'content' => 'This is my first post',
        'author_id' => 'user_1'
    ],
    [
        '_id' => 'post_2',
        'title' => 'PHP Tips',
        'content' => 'Some useful PHP tips',
        'author_id' => 'user_2'
    ],
    [
        '_id' => 'post_3',
        'title' => 'Database Design',
        'content' => 'Best practices for database design',
        'author_id' => 'user_1'
    ]
]);

// Insert comments
$comments->insertMany([
    [
        '_id' => 'comment_1',
        'content' => 'Great post!',
        'post_id' => 'post_1',
        'user_id' => 'user_2'
    ],
    [
        '_id' => 'comment_2',
        'content' => 'Very helpful, thanks!',
        'post_id' => 'post_2',
        'user_id' => 'user_3'
    ],
    [
        '_id' => 'comment_3',
        'content' => 'I learned a lot from this',
        'post_id' => 'post_3',
        'user_id' => 'user_2'
    ]
]);

// Get posts and populate author information
$postsWithAuthors = $posts->populate(
    $posts->find()->toArray(),
    'author_id',
    'users',
    '_id',
    'author'
);

echo "Posts with authors:\n";
foreach ($postsWithAuthors as $post) {
    echo "- {$post['title']} by {$post['author']['name']}\n";
}

// Get comments and populate both post and user information
$commentsWithDetails = $comments->populate(
    $comments->find()->toArray(),
    'post_id',
    'posts',
    '_id',
    'post'
);

$commentsWithDetails = $comments->populate(
    $commentsWithDetails,
    'user_id',
    'users',
    '_id',
    'user'
);

echo "\nComments with details:\n";
foreach ($commentsWithDetails as $comment) {
    echo "- {$comment['user']['name']} commented on \"{$comment['post']['title']}\": {$comment['content']}\n";
}

$client->close();
?>
```

### Complex Queries

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('complex');

$products = $db->products;

// Insert sample data
$products->insertMany([
    [
        'name' => 'Laptop',
        'category' => 'electronics',
        'price' => 999.99,
        'in_stock' => true,
        'specs' => ['cpu' => 'i7', 'ram' => '16GB', 'storage' => '512GB']
    ],
    [
        'name' => 'Smartphone',
        'category' => 'electronics',
        'price' => 699.99,
        'in_stock' => true,
        'specs' => ['cpu' => 'A14', 'ram' => '8GB', 'storage' => '128GB']
    ],
    [
        'name' => 'Desk Chair',
        'category' => 'furniture',
        'price' => 199.99,
        'in_stock' => false,
        'specs' => ['material' => 'mesh', 'color' => 'black']
    ],
    [
        'name' 'Tablet',
        'category' => 'electronics',
        'price' => 399.99,
        'in_stock' => true,
        'specs' => ['cpu' => 'A12', 'ram' => '4GB', 'storage' => '64GB']
    ]
]);

// Create indexes for better performance
$products->createIndex('category');
$products->createIndex('price');
$products->createIndex('in_stock');

// Find electronics in stock
$electronics = $products->find([
    'category' => 'electronics',
    'in_stock' => true
]);
echo "Electronics in stock: " . count($electronics) . "\n";

// Find products with price between 300 and 800
$midRange = $products->find([
    'price' => ['$gte' => 300, '$lte' => 800]
]);
echo "Mid-range products: " . count($midRange) . "\n";

// Find products with specific CPU
$intelProducts = $products->find([
    'specs.cpu' => 'i7'
]);
echo "Intel products: " . count($intelProducts) . "\n";

// Find products not in furniture category
$notFurniture = $products->find([
    'category' => ['$ne' => 'furniture']
]);
echo "Non-furniture products: " . count($notFurniture) . "\n";

// Find products with 16GB or more RAM
$highRam = $products->find([
    'specs.ram' => ['$gte' => '16GB']
]);
echo "High RAM products: " . count($highRam) . "\n";

// Count by category
$categories = $products->find()->toArray();
$categoryCounts = [];
foreach ($categories as $product) {
    $categoryCounts[$product['category']] = ($categoryCounts[$product['category']] ?? 0) + 1;
}

echo "Product counts by category:\n";
foreach ($categoryCounts as $category => $count) {
    echo "- $category: $count\n";
}

$client->close();
?>
```

## Best Practices

1. **Choose appropriate ID generation mode**: AUTO for simplicity, MANUAL for control, PREFIX for ordered IDs
2. **Use encryption for sensitive data**: Set encryption keys for collections storing personal information
3. **Implement searchable fields**: For frequently queried fields to improve performance
4. **Use event hooks**: For data validation, logging, and automation
5. **Populate relations efficiently**: Use populate() to avoid multiple queries
6. **Create indexes**: For frequently queried fields to improve performance
7. **Handle errors gracefully**: Check return values and handle exceptions
8. **Use transactions**: For multiple related operations
9. **Clean up unused collections**: Use drop() to remove temporary collections
10. **Use projections**: To limit data transfer when only specific fields are needed
