# Cursor Class Documentation

The `Cursor` class represents the result of a query operation. It implements the `IteratorAggregate` interface, allowing you to iterate over query results efficiently. Cursors support chaining methods like `limit()`, `skip()`, `sort()`, and provide data population capabilities.

## Constructor

```php
public function __construct(Collection $collection, $criteria, $projection = null, ?string $criteriaSql = null)
```

**Parameters:**

- `$collection` (Collection): The collection this cursor belongs to
- `$criteria` (mixed): Query criteria
- `$projection` (mixed, optional): Fields to include/exclude
- `$criteriaSql` (string, optional): Pre-built SQL WHERE clause

## Properties

### `$collection`

```php
public Collection $collection
```

The collection instance this cursor belongs to.

### `$criteria`

```php
public $criteria
```

The query criteria used to generate this cursor.

### `$projection`

```php
protected ?array $projection = null
```

Fields to include/exclude in results.

### `$limit`

```php
protected ?int $limit = null
```

Maximum number of documents to return.

### `$skip`

```php
protected ?int $skip = null
```

Number of documents to skip.

### `$sort`

```php
protected ?array $sort = null
```

Sort criteria for results.

### `$populate`

```php
protected array $populate = []
```

Population rules for data relations.

## Methods

### count()

```php
public function count(): int
```

**Returns:**

- `int`: Number of documents matching the query

**Description:**
Counts the number of documents in the cursor. Respects limit and skip settings.

**Example:**

```php
$users = $db->users;

// Count all users
$total = $users->find()->count();
echo "Total users: $total\n";

// Count active users
$active = $users->find(['status' => 'active'])->count();
echo "Active users: $active\n";

// Count with limit
$recent = $users->find()
                ->skip(10)
                ->limit(5)
                ->count();
echo "Users 11-15: $recent\n";
```

### each()

```php
public function each($callable): self
```

**Parameters:**

- `$callable` (callable): Function to call for each document

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Iterates over all documents in the cursor and calls the provided function for each.

**Example:**

```php
$users = $db->users;

// Process each user
$users->find(['status' => 'active'])->each(function($user) {
    echo "Processing user: {$user['name']}\n";
    // Perform some operation on each user
});
```

### limit()

```php
public function limit(int $limit): self
```

**Parameters:**

- `$limit` (int): Maximum number of documents to return

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Limits the number of documents returned.

**Example:**

```php
$users = $db->users;

// Get first 5 users
$recentUsers = $users->find()
                    ->sort(['created_at' => -1])
                    ->limit(5)
                    ->toArray();
```

### skip()

```php
public function skip(int $skip): self
```

**Parameters:**

- `$skip` (int): Number of documents to skip

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Skips the specified number of documents. Useful for pagination.

**Example:**

```php
$users = $db->users;

// Pagination: page 2, 10 users per page
$page = 2;
$perPage = 10;

$usersPage = $users->find()
                   ->skip(($page - 1) * $perPage)
                   ->limit($perPage)
                   ->toArray();
```

### sort()

```php
public function sort($sort): self
```

**Parameters:**

- `$sort` (array): Sort criteria (field => direction)

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Sorts the results by the specified criteria. Direction is 1 for ascending, -1 for descending.

**Example:**

```php
$users = $db->users;

// Sort by name ascending
$usersByName = $users->find()->sort(['name' => 1])->toArray();

// Sort by age descending
$usersByAge = $users->find()->sort(['age' => -1])->toArray();

// Sort by multiple fields
$usersMultiSort = $users->find()
                       ->sort(['status' => 1, 'age' => -1])
                       ->toArray();
```

### populate()

```php
public function populate(string $path, Collection $collection, array $options = []): self
```

**Parameters:**

- `$path` (string): Field path in documents to populate
- `$collection` (Collection): Collection to populate from
- `$options` (array): Additional options

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Adds a population rule to populate a field in documents with data from another collection.

**Example:**

```php
$posts = $db->posts;
$users = $db->users;

// Get posts and populate author information
$postsWithAuthors = $posts->find()
                         ->populate('author_id', $users, ['as' => 'author'])
                         ->toArray();
```

### populateMany()

```php
public function populateMany(array $defs): self
```

**Parameters:**

- `$defs` (array): Array of population definitions

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Adds multiple population rules at once.

**Example:**

```php
$comments = $db->comments;
$posts = $db->posts;
$users = $db->users;

// Populate both post and user information
$commentsWithDetails = $comments->find()
                               ->populateMany([
                                   'post_id' => [$posts, ['as' => 'post']],
                                   'user_id' => [$users, ['as' => 'user']]
                               ])
                               ->toArray();
```

### with()

```php
public function with(string|array $path, ?Collection $collection = null, array $options = []): self
```

**Parameters:**

- `$path` (string|array): Field path or array of paths
- `$collection` (Collection, optional): Collection to populate from
- `$options` (array): Additional options

**Returns:**

- `self`: Return the cursor for method chaining

**Description:**
Alias for populate() and populateMany(). Accepts either a single path or array of paths.

**Example:**

```php
$orders = $db->orders;
$users = $db->users;
$products = $db->products;

// Single populate
$ordersWithUser = $orders->find()
                        ->with('user_id', $users)
                        ->toArray();

// Multiple populates
$ordersWithDetails = $orders->find()
                            ->with([
                                'user_id' => [$users, ['as' => 'customer']],
                                'product_id' => [$products, ['as' => 'product']]
                            ])
                            ->toArray();
```

### toArray()

```php
public function toArray(): array
```

**Returns:**

- `array`: Array of all documents in the cursor

**Description:**
Converts the cursor to an array of documents. Applies population and projection.

**Example:**

```php
$users = $db->users;

// Get all users as array
$allUsers = $users->find()->toArray();

// Get active users with specific fields
$activeUsers = $users->find(
    ['status' => 'active'],
    ['name' => 1, 'email' => 1]
)->toArray();

// Get users with pagination and sorting
$pageUsers = $users->find()
                  ->sort(['name' => 1])
                  ->skip(10)
                  ->limit(10)
                  ->toArray();
```

## Iterator Methods

### rewind()

```php
public function rewind()
```

**Description:**
Rewinds the cursor to the first document.

### current()

```php
public function current()
```

**Returns:**

- `mixed`: Current document

**Description:**
Returns the current document.

### key()

```php
public function key()
```

**Returns:**

- `int`: Current position key

**Description:**
Returns the current position key.

### next()

```php
public function next()
```

**Description:**
Moves to the next document.

### valid()

```php
public function valid(): bool
```

**Returns:**

- `bool`: True if there are more documents

**Description:**
Checks if there are more documents to iterate over.

### getIterator()

```php
public function getIterator(): \Traversable
```

**Returns:**

- `\Traversable`: Iterator for the cursor

**Description:**
Returns a traversable iterator for the cursor.

**Example:**

```php
$users = $db->users;

// Iterate using foreach
foreach ($users->find(['status' => 'active']) as $user) {
    echo "User: {$user['name']}\n";
}

// Using iterator directly
$iterator = $users->find()->getIterator();
foreach ($iterator as $user) {
    echo "User: {$user['name']}\n";
}
```

## Protected Methods

### applyPopulate()

```php
protected function applyPopulate(array $data, array $rule): array
```

**Parameters:**

- `$data` (array): Documents to populate
- `$rule` (array): Population rule

**Returns:**

- `array`: Populated documents

**Description:**
Applies a single population rule to documents.

### collectIds()

```php
protected function collectIds($node, $path, &$ids, $i = 0)
```

**Parameters:**

- `$node` (mixed): Current node
- `$path` (array): Path segments
- `&$ids` (array): Reference to collect IDs into
- `$i` (int): Current path index

**Description:**
Collects IDs from documents for population.

### inject()

```php
protected function inject(&$node, $path, $map, $as, $i = 0)
```

**Parameters:**

- `&$node` (mixed): Node to inject into
- `$path` (array): Path segments
- `$map` (array): Map of IDs to documents
- `$as` (string): Field name to populate as
- `$i` (int): Current path index

**Description:**
Injects populated data into documents.

### applyProjection()

```php
protected function applyProjection(array $doc): array
```

**Parameters:**

- `$doc` (array): Document to apply projection to

**Returns:**

- `array`: Document with projection applied

**Description:**
Applies field projection to a document.

## Complete Usage Examples

### Basic Cursor Operations

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('test');

// Create users collection
$db->createCollection('users');

// Insert test data
$users = $db->users;
$users->insertMany([
    ['name' => 'John', 'age' => 30, 'status' => 'active'],
    ['name' => 'Jane', 'age' => 25, 'status' => 'active'],
    ['name' => 'Bob', 'age' => 35, 'status' => 'inactive'],
    ['name' => 'Alice', 'age' => 28, 'status' => 'active'],
    ['name' => 'Charlie', 'age' => 32, 'status' => 'inactive']
]);

// Basic cursor usage
$allUsers = $users->find();
echo "All users:\n";
foreach ($allUsers as $user) {
    echo "- {$user['name']} ({$user['age']})\n";
}

// Count users
$total = $users->find()->count();
echo "\nTotal users: $total\n";

// Active users only
$activeUsers = $users->find(['status' => 'active']);
echo "Active users:\n";
foreach ($activeUsers as $user) {
    echo "- {$user['name']}\n";
}

$client->close();
?>
```

### Sorting and Pagination

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('pagination');

// Create users collection with timestamps
$db->createCollection('users');

$users = $db->users;
$users->on('beforeInsert', function($doc) {
    $doc['created_at'] = date('Y-m-d H:i:s');
    return $doc;
});

// Insert test data
$users->insertMany([
    ['name' => 'John', 'age' => 30],
    ['name' => 'Jane', 'age' => 25],
    ['name' => 'Bob', 'age' => 35],
    ['name' => 'Alice', 'age' => 28],
    ['name' => 'Charlie', 'age' => 32],
    ['name' => 'David', 'age' => 27],
    ['name' => 'Eve', 'age' => 29],
    ['name' => 'Frank', 'age' => 31]
]);

// Sort by age ascending
$byAge = $users->find()->sort(['age' => 1])->toArray();
echo "Users by age (ascending):\n";
foreach ($byAge as $user) {
    echo "- {$user['name']} ({$user['age']})\n";
}

// Sort by name descending
$byName = $users->find()->sort(['name' => -1])->toArray();
echo "\nUsers by name (descending):\n";
foreach ($byName as $user) {
    echo "- {$user['name']}\n";
}

// Pagination: first page
$perPage = 3;
$page1 = $users->find()
               ->sort(['age' => 1])
               ->skip(0)
               ->limit($perPage)
               ->toArray();
echo "\nPage 1 (oldest $perPage users):\n";
foreach ($page1 as $user) {
    echo "- {$user['name']} ({$user['age']})\n";
}

// Pagination: second page
$page2 = $users->find()
               ->sort(['age' => 1])
               ->skip($perPage)
               ->limit($perPage)
               ->toArray();
echo "\nPage 2:\n";
foreach ($page2 as $user) {
    echo "- {$user['name']} ({$user['age']})\n";
}

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

// Single population
$postsWithAuthors = $posts->find()
                         ->populate('author_id', $users, ['as' => 'author'])
                         ->toArray();

echo "Posts with authors:\n";
foreach ($postsWithAuthors as $post) {
    echo "- {$post['title']} by {$post['author']['name']}\n";
}

// Multiple population using populateMany
$commentsWithDetails = $comments->find()
                               ->populateMany([
                                   'post_id' => [$posts, ['as' => 'post']],
                                   'user_id' => [$users, ['as' => 'user']]
                               ])
                               ->toArray();

echo "\nComments with details:\n";
foreach ($commentsWithDetails as $comment) {
    echo "- {$comment['user']['name']} commented on \"{$comment['post']['title']}\": {$comment['content']}\n";
}

// Using with() alias for cleaner syntax
$orders = $db->createCollection('orders');
$orders->insertMany([
    [
        '_id' => 'order_1',
        'total' => 99.99,
        'customer_id' => 'user_1',
        'items' => ['item_1', 'item_2']
    ],
    [
        '_id' => 'order_2',
        'total' => 149.99,
        'customer_id' => 'user_2',
        'items' => ['item_3']
    ]
]);

$ordersWithCustomers = $orders->find()
                             ->with('customer_id', $users, ['as' => 'customer'])
                             ->toArray();

echo "\nOrders with customers:\n";
foreach ($ordersWithCustomers as $order) {
    echo "- Order {$order['_id']}: {$order['customer']['name']} spent \${$order['total']}\n";
}

$client->close();
?>
```

### Projections and Field Selection

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('projection');

// Create users collection
$db->createCollection('users');

$users = $db->users;
$users->insertMany([
    [
        '_id' => 'user_1',
        'name' => 'John',
        'email' => 'john@example.com',
        'age' => 30,
        'address' => [
            'street' => '123 Main St',
            'city' => 'New York',
            'country' => 'USA'
        ],
        'preferences' => [
            'theme' => 'dark',
            'notifications' => true
        ]
    ],
    [
        '_id' => 'user_2',
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'age' => 25,
        'address' => [
            'street' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'country' => 'USA'
        ],
        'preferences' => [
            'theme' => 'light',
            'notifications' => false
        ]
    ]
]);

// Include specific fields (projection)
$userProfiles = $users->find(
    null, // no criteria
    ['name' => 1, 'email' => 1, 'age' => 1] // include these fields
)->toArray();

echo "User profiles:\n";
foreach ($userProfiles as $user) {
    echo "- {$user['name']} ({$user['age']}) - {$user['email']}\n";
}

// Exclude specific fields
$userContactInfo = $users->find(
    null, // no criteria
    ['address' => 0, 'preferences' => 0] // exclude these fields
)->toArray();

echo "\nUser contact info (without address/preferences):\n";
foreach ($userContactInfo as $user) {
    echo "- {$user['name']}: {$user['email']}\n";
}

// Include nested fields
$userLocations = $users->find(
    null,
    ['name' => 1, 'address.city' => 1, 'address.country' => 1]
)->toArray();

echo "\nUser locations:\n";
foreach ($userLocations as $user) {
    echo "- {$user['name']} lives in {$user['address']['city']}, {$user['address']['country']}\n";
}

$client->close();
?>
```

### Cursor Method Chaining

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('chaining');

// Create products collection
$db->createCollection('products');

$products = $db->products;
$products->insertMany([
    [
        'name' => 'Laptop',
        'category' => 'electronics',
        'price' => 999.99,
        'in_stock' => true,
        'rating' => 4.5
    ],
    [
        'name' => 'Smartphone',
        'category' => 'electronics',
        'price' => 699.99,
        'in_stock' => true,
        'rating' => 4.2
    ],
    [
        'name' => 'Desk Chair',
        'category' => 'furniture',
        'price' => 199.99,
        'in_stock' => false,
        'rating' => 3.8
    ],
    [
        'name' => 'Tablet',
        'category' => 'electronics',
        'price' => 399.99,
        'in_stock' => true,
        'rating' => 4.1
    ],
    [
        'name' => 'Monitor',
        'category' => 'electronics',
        'price' => 299.99,
        'in_stock' => true,
        'rating' => 4.3
    ]
]);

// Complex query with method chaining
$results = $products->find(['category' => 'electronics'])
                   ->sort(['rating' => -1])
                   ->skip(0)
                   ->limit(3)
                   ->toArray();

echo "Top 3 electronics by rating:\n";
foreach ($results as $product) {
    echo "- {$product['name']}: {$product['rating']}/5 (\${$product['price']})\n";
}

// Pagination with chaining
$page = 1;
$perPage = 2;

$pageResults = $products->find(['in_stock' => true])
                       ->sort(['price' => -1])
                       ->skip(($page - 1) * $perPage)
                       ->limit($perPage)
                       ->toArray();

echo "\nPage $page of in-stock products (most expensive first):\n";
foreach ($pageResults as $product) {
    echo "- {$product['name']}: \${$product['price']}\n";
}

// Using each() for processing
echo "\nProcessing all furniture items:\n";
$products->find(['category' => 'furniture'])
        ->each(function($product) {
            echo "Processing {$product['name']}...\n";
        });

// Convert to array with chaining
$allElectronics = $products->find(['category' => 'electronics'])
                          ->sort(['name' => 1])
                          ->toArray();

echo "\nAll electronics (sorted by name):\n";
foreach ($allElectronics as $product) {
    echo "- {$product['name']}\n";
}

$client->close();
?>
```

### Iterator Usage

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('iterator');

// Create logs collection
$db->createCollection('logs');

$logs = $db->logs;
$logs->on('beforeInsert', function($doc) {
    $doc['timestamp'] = date('Y-m-d H:i:s');
    return $doc;
});

// Insert log entries
$logs->insertMany([
    ['message' => 'User login', 'level' => 'info', 'user_id' => 'user_1'],
    ['message' => 'Database connection', 'level' => 'debug', 'user_id' => 'system'],
    ['message' => 'Payment processed', 'level' => 'info', 'user_id' => 'user_2'],
    ['message' => 'Error in query', 'level' => 'error', 'user_id' => 'system'],
    ['message' => 'User logout', 'level' => 'info', 'user_id' => 'user_1']
]);

// Using foreach loop (IteratorAggregate)
echo "All logs:\n";
foreach ($logs->find() as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

// Using iterator directly
echo "\nUsing iterator directly:\n";
$iterator = $logs->find()->getIterator();
while ($iterator->valid()) {
    $log = $iterator->current();
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
    $iterator->next();
}

// Manual iterator control
echo "\nManual iterator control:\n";
$cursor = $logs->find(['level' => 'info']);
$cursor->rewind();

while ($cursor->valid()) {
    $log = $cursor->current();
    echo "[{$log['timestamp']}] INFO: {$log['message']}\n";
    $cursor->next();
}

// Iterator with chaining
echo "\nIterator with chaining:\n";
$filteredIterator = $logs->find(['level' => ['$in' => ['info', 'error']]])
                        ->sort(['timestamp' => -1])
                        ->getIterator();

foreach ($filteredIterator as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

$client->close();
?>
```

### Advanced Cursor Features

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';

use PocketDB\Client;

// Setup
$client = new Client(':memory:');
$db = $client->selectDB('advanced');

// Create users and posts collections
$users = $db->createCollection('users');
$posts = $db->createCollection('posts');

// Insert users
$users->insertMany([
    ['_id' => 'user_1', 'name' => 'John', 'role' => 'admin', 'department' => 'engineering'],
    ['_id' => 'user_2', 'name' => 'Jane', 'role' => 'user', 'department' => 'marketing'],
    ['_id' => 'user_3', 'name' => 'Bob', 'role' => 'moderator', 'department' => 'support'],
    ['_id' => 'user_4', 'name' => 'Alice', 'role' => 'user', 'department' => 'engineering']
]);

// Insert posts
$posts->insertMany([
    [
        '_id' => 'post_1',
        'title' => 'Introduction to PHP',
        'author_id' => 'user_1',
        'tags' => ['php', 'programming'],
        'published' => true
    ],
    [
        '_id' => 'post_2',
        'title' => 'Marketing Strategies',
        'author_id' => 'user_2',
        'tags' => ['marketing', 'business'],
        'published' => true
    ],
    [
        '_id' => 'post_3',
        'title' => 'Support Guidelines',
        'author_id' => 'user_3',
        'tags' => ['support', 'documentation'],
        'published' => false
    ],
    [
        '_id' => 'post_4',
        'title' => 'Advanced PHP Techniques',
        'author_id' => 'user_1',
        'tags' => ['php', 'advanced'],
        'published' => true
    ]
]);

// Complex query with multiple features
$adminPosts = $posts->find(['published' => true])
                   ->populate('author_id', $users, ['as' => 'author'])
                   ->sort(['title' => 1])
                   ->toArray();

echo "Published posts by admin users:\n";
foreach ($adminPosts as $post) {
    echo "- {$post['title']} by {$post['author']['name']} ({$post['author']['role']})\n";
}

// Department-based filtering with population
$engineeringPosts = $posts->find()
                         ->populate('author_id', $users, [
                             'as' => 'author',
                             'options' => ['query' => ['department' => 'engineering']]
                         ])
                         ->toArray();

echo "\nPosts by engineering department:\n";
foreach ($engineeringPosts as $post) {
    if (isset($post['author']) && $post['author']['department'] === 'engineering') {
        echo "- {$post['title']} by {$post['author']['name']}\n";
    }
}

// Tag-based queries with chaining
$phpPosts = $posts->find(['tags' => 'php'])
                  ->sort(['title' => 1])
                  ->limit(10)
                  ->toArray();

echo "\nPosts tagged with 'php':\n";
foreach ($phpPosts as $post) {
    echo "- {$post['title']}\n";
}

// Using each() for analytics
echo "\nPost analytics:\n";
$postsByDepartment = [];
$postsByRole = [];

$posts->find()->each(function($post) use (&$postsByDepartment, &$postsByRole) {
    // Get author info (would need population in real scenario)
    // For this example, we'll simulate
    $author = $users->findOne(['_id' => $post['author_id']]);

    if ($author) {
        $dept = $author['department'] ?? 'unknown';
        $role = $author['role'] ?? 'unknown';

        $postsByDepartment[$dept] = ($postsByDepartment[$dept] ?? 0) + 1;
        $postsByRole[$role] = ($postsByRole[$role] ?? 0) + 1;
    }
});

echo "Posts by department:\n";
foreach ($postsByDepartment as $dept => $count) {
    echo "- $dept: $count posts\n";
}

echo "Posts by role:\n";
foreach ($postsByRole as $role => $count) {
    echo "- $role: $count posts\n";
}

$client->close();
?>
```

## Best Practices

1. **Use method chaining**: Chain cursor methods for clean, readable queries
2. **Limit results**: Use `limit()` to prevent large result sets
3. **Implement pagination**: Use `skip()` and `limit()` together for pagination
4. **Sort efficiently**: Create indexes for frequently sorted fields
5. **Use projections**: Reduce data transfer with field selection
6. **Population wisely**: Only populate fields you actually need
7. **Close cursors**: Let the garbage collector handle cursor cleanup
8. **Check for empty results**: Use `count()` or check if cursor is valid
9. **Use iterators**: For memory efficiency with large result sets
10. **Chain operations**: Combine multiple operations in a single cursor
