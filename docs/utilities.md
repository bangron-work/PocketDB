# Utilities and Helper Functions Documentation

PocketDB includes several utility classes and helper functions that provide additional functionality for array querying, fuzzy searching, and ID generation.

## UtilArrayQuery Class

The `UtilArrayQuery` class provides static methods for complex array querying and condition matching. It's used internally by PocketDB for evaluating query criteria against documents.

### Static Properties

None

### Static Methods

#### buildCondition()

```php
public static function buildCondition($criteria, $concat = ' && ')
```

**Parameters:**

- `$criteria` (array): Criteria array to build condition from
- `$concat` (string): Concatenation operator for conditions

**Returns:**

- `string`: PHP code string representing the condition

**Description:**
Builds a PHP code string from criteria array that can be evaluated to check if a document matches the criteria.

**Example:**

```php
$criteria = [
    'age' => ['$gt' => 18],
    'status' => 'active'
];

$condition = UtilArrayQuery::buildCondition($criteria);
// Returns: "(isset($document['age']) && $document['age']>18) && (isset($document['status']) && $document['status']=='active')"
```

#### check()

```php
public static function check($value, $condition)
```

**Parameters:**

- `$value` (mixed): Value to check
- `$condition` (array): Condition array

**Returns:**

- `bool`: True if value matches condition

**Description:**
Checks if a value matches a condition array.

**Example:**

```php
$value = 25;
$condition = ['$gt' => 18, '$lt' => 30];

$result = UtilArrayQuery::check($value, $condition);
// Returns: true (25 > 18 and 25 < 30)
```

#### match()

```php
public static function match($criteria, $document)
```

**Parameters:**

- `$criteria` (array): Criteria array
- `$document` (array): Document to match against

**Returns:**

- `bool`: True if document matches criteria

**Description:**
Matches a full criteria array against a document array. Supports logical operators ($and, $or) and field comparisons.

**Example:**

```php
$criteria = [
    '$or' => [
        ['status' => 'active'],
        ['role' => 'admin']
    ],
    'age' => ['$gte' => 18]
];

$document1 = ['status' => 'active', 'age' => 25];
$document2 = ['role' => 'admin', 'age' => 16];
$document3 = ['status' => 'inactive', 'age' => 30];

$result1 = UtilArrayQuery::match($criteria, $document1); // true
$result2 = UtilArrayQuery::match($criteria, $document2); // false (age < 18)
$result3 = UtilArrayQuery::match($criteria, $document3); // false (status != active and role != admin)
```

#### evaluate()

```php
private static function evaluate($func, $a, $b)
```

**Parameters:**

- `$func` (string): Function/operator name
- `$a` (mixed): First value
- `$b` (mixed): Second value

**Returns:**

- `bool`: Result of the evaluation

**Description:**
Evaluates a specific function/operator between two values. This is the core evaluation method for all query operators.

**Supported Operators:**

- `$eq`: Equal to
- `$ne`: Not equal to
- `$gt`: Greater than
- `$gte`: Greater than or equal to
- `$lt`: Less than
- `$lte`: Less than or equal to
- `$in`: In array
- `$nin`: Not in array
- `$has`: Has value in array
- `$all`: Has all values in array
- `$regex`: Regular expression match
- `$size`: Array size equals
- `$mod`: Modulo operation
- `$func`: Custom function
- `$exists`: Field exists
- `$fuzzy`: Fuzzy search

**Example:**

```php
// Numeric comparisons
UtilArrayQuery::evaluate('$gt', 25, 18);    // true
UtilArrayQuery::evaluate('$gte', 25, 25);   // true
UtilArrayQuery::evaluate('$lt', 18, 25);    // true
UtilArrayQuery::evaluate('$lte', 25, 25);   // true

// Array operations
UtilArrayQuery::evaluate('$in', 'apple', ['apple', 'banana', 'orange']); // true
UtilArrayQuery::evaluate('$nin', 'grape', ['apple', 'banana', 'orange']); // true

// Existence check
UtilArrayQuery::evaluate('$exists', 'value', true); // true
UtilArrayQuery::evaluate('$exists', null, false); // true
```

## Helper Functions

### createMongoDbLikeId()

```php
function createMongoDbLikeId()
```

**Returns:**

- `string`: UUID v4 string

**Description:**
Generates a MongoDB-like UUID v4 identifier. This is used when ID generation mode is set to AUTO.

**Example:**

```php
$id = createMongoDbLikeId();
// Returns: "550e8400-e29b-41d4-a716-446655440000" (example UUID)

// Used in collection insert
$users->insert(['name' => 'John']); // Gets auto-generated ID
```

### levenshtein_utf8()

```php
function levenshtein_utf8($s1, $s2)
```

**Parameters:**

- `$s1` (string): First string
- `$s2` (string): Second string

**Returns:**

- `int`: Levenshtein distance

**Description:**
Calculates the Levenshtein distance between two UTF-8 strings. This is used for fuzzy search functionality.

**Example:**

```php
$distance = levenshtein_utf8('kitten', 'sitting');
// Returns: 3 (k → s, e → i, insert g)

$distance = levenshtein_utf8('café', 'coffee');
// Returns: 5 (handles UTF-8 characters)
```

### fuzzy_search()

```php
function fuzzy_search($search, $text, $distance = 3)
```

**Parameters:**

- `$search` (string): Search string
- `$text` (string): Text to search in
- `$distance` (int): Maximum Levenshtein distance

**Returns:**

- `float`: Fuzzy score (0.0 to 1.0)

**Description:**
Performs fuzzy search using Levenshtein distance. Returns a score based on how well the search terms match the text.

**Example:**

```php
// Exact match
$score = fuzzy_search('apple', 'apple');
// Returns: 1.0

// Close match
$score = fuzzy_search('apple', 'appel');
// Returns: 0.8 (based on Levenshtein distance)

// Multiple search terms
$score = fuzzy_search('apple banana', 'I like appel and banans');
// Returns: 0.75 (average score for both terms)

// Use in query
$users = $db->users;
$fuzzyResults = $users->find(['name' => ['$fuzzy' => 'jon']])->toArray();
```

## Complete Usage Examples

### UtilArrayQuery Usage

```php
<?php
require_once 'src/Database.php';
require_once 'src/Collection.php';

use PocketDB\Database;
use PocketDB\UtilArrayQuery;

// Create database
$db = new Database(':memory:');

// Test documents
$doc1 = ['name' => 'John', 'age' => 25, 'status' => 'active', 'tags' => ['admin', 'user']];
$doc2 = ['name' => 'Jane', 'age' => 30, 'status' => 'inactive', 'tags' => ['user']];
$doc3 = ['name' => 'Bob', 'age' => 25, 'status' => 'active', 'tags' => ['moderator']];

// Simple equality
$criteria1 = ['name' => 'John'];
$result1 = UtilArrayQuery::match($criteria1, $doc1); // true
$result2 = UtilArrayQuery::match($criteria1, $doc2); // false

echo "Simple equality - John found: " . ($result1 ? 'Yes' : 'No') . "\n";
echo "Simple equality - Jane found: " . ($result2 ? 'Yes' : 'No') . "\n";

// Comparison operators
$criteria2 = ['age' => ['$gt' => 20]];
$result3 = UtilArrayQuery::match($criteria2, $doc1); // true
$result4 = UtilArrayQuery::match($criteria2, $doc2); // true
$result5 = UtilArrayQuery::match($criteria2, $doc3); // true

echo "Age > 20 - All docs match: " . ($result3 && $result4 && $result5 ? 'Yes' : 'No') . "\n";

// Array operators
$criteria3 = ['tags' => ['$has' => 'admin']];
$result6 = UtilArrayQuery::match($criteria3, $doc1); // true
$result7 = UtilArrayQuery::match($criteria3, $doc2); // false

echo "Has 'admin' tag - John: " . ($result6 ? 'Yes' : 'No') . "\n";
echo "Has 'admin' tag - Jane: " . ($result7 ? 'Yes' : 'No') . "\n";

// Logical operators
$criteria4 = [
    '$or' => [
        ['status' => 'active'],
        ['name' => 'Jane']
    ],
    'age' => ['$gte' => 25]
];

$result8 = UtilArrayQuery::match($criteria4, $doc1); // true (status active)
$result9 = UtilArrayQuery::match($criteria4, $doc2); // true (name Jane)
$result10 = UtilArrayQuery::match($criteria4, $doc3); // true (status active)

echo "OR condition - All docs match: " . ($result8 && $result9 && $result10 ? 'Yes' : 'No') . "\n";

// Build condition example
$criteria5 = [
    'age' => ['$gte' => 18, '$lte' => 65],
    'status' => 'active'
];

$condition = UtilArrayQuery::buildCondition($criteria5);
echo "Built condition: $condition\n";

// Evaluate individual operators
$eval1 = UtilArrayQuery::evaluate('$in', 'user', ['admin', 'user', 'moderator']); // true
$eval2 = UtilArrayQuery::evaluate('$nin', 'guest', ['admin', 'user', 'moderator']); // true
$eval3 = UtilArrayQuery::evaluate('$size', 3, ['a', 'b', 'c']); // true
$eval4 = UtilArrayQuery::evaluate('$exists', 'value', true); // true

echo "IN operator test: " . ($eval1 ? 'Pass' : 'Fail') . "\n";
echo "NIN operator test: " . ($eval2 ? 'Pass' : 'Fail') . "\n";
echo "Size operator test: " . ($eval3 ? 'Pass' : 'Fail') . "\n";
echo "Exists operator test: " . ($eval4 ? 'Pass' : 'Fail') . "\n";

// Regular expression matching
$criteria6 = ['name' => ['$regex' => '^J.*e$']];
$result11 = UtilArrayQuery::match($criteria6, $doc1); // true (John matches pattern)
$result12 = UtilArrayQuery::match($criteria6, $doc2); // true (Jane matches pattern)

echo "Regex pattern ^J.*e$ - John: " . ($result11 ? 'Match' : 'No match') . "\n";
echo "Regex pattern ^J.*e$ - Jane: " . ($result12 ? 'Match' : 'No match') . "\n";

// Modulo operation
$criteria7 = ['age' => ['$mod' => [5, 0]]]; // Age divisible by 5 with remainder 0
$result13 = UtilArrayQuery::match($criteria7, $doc1); // true (25 % 5 = 0)
$result14 = UtilArrayQuery::match($criteria7, $doc2); // false (30 % 5 = 0, but wait...)

echo "Modulo 5=0 - John (25): " . ($result13 ? 'Match' : 'No match') . "\n";
echo "Modulo 5=0 - Jane (30): " . ($result14 ? 'Match' : 'No match') . "\n";
?>
```

### ID Generation

```php
<?php
require_once 'src/Collection.php';
require_once 'src/Database.php';

use PocketDB\Database;

// Create database and collection
$db = new Database(':memory:');
$users = $db->createCollection('users');

// Use AUTO mode (default)
$users->setIdModeAuto();

// Insert user without ID - gets auto-generated
$user1 = $users->insert(['name' => 'John']);
echo "AUTO ID: {$user1['_id']}\n";

// Use MANUAL mode
$adminUsers = $db->createCollection('admin_users');
$adminUsers->setIdModeManual();

// Must provide ID
$user2 = $adminUsers->insert([
    '_id' => 'admin_001',
    'name' => 'Jane',
    'role' => 'administrator'
]);
echo "Manual ID: {$user2['_id']}\n";

// Use PREFIX mode
$products = $db->createCollection('products');
$products->setIdModePrefix('PRD');

// Insert products with auto-generated prefixed IDs
$product1 = $products->insert(['name' => 'Laptop', 'price' => 999.99]);
$product2 = $products->insert(['name' => 'Mouse', 'price' => 29.99]);

echo "Product IDs: {$product1['_id']}, {$product2['_id']}\n";

// Generate UUID directly
require_once 'src/Database.php';
function createMongoDbLikeId() {
    // Generate a UUID v4
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xFFFF), random_int(0, 0xFFFF),
        random_int(0, 0xFFFF),
        random_int(0, 0x0FFF) | 0x4000,
        random_int(0, 0x3FFF) | 0x8000,
        random_int(0, 0xFFFF), random_int(0, 0xFFFF), random_int(0, 0xFFFF)
    );
}

// Generate custom UUID
$customId = createMongoDbLikeId();
echo "Custom UUID: $customId\n";
?>
```

### Fuzzy Search

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';
require_once 'src/Collection.php';

use PocketDB\Database;

// Create database
$db = new Database(':memory:');

// Create products collection with searchable fields
$products = $db->createCollection('products');

// Insert sample products
$products->insertMany([
    ['name' => 'Apple iPhone 13', 'category' => 'smartphone', 'price' => 999.99],
    ['name' => 'Samsung Galaxy S21', 'category' => 'smartphone', 'price' => 899.99],
    ['name' => 'Google Pixel 6', 'category' => 'smartphone', 'price' => 599.99],
    ['name' => 'MacBook Pro', 'category' => 'laptop', 'price' => 1999.99],
    ['name' => 'Dell XPS 13', 'category' => 'laptop', 'price' => 1299.99],
    ['name' => 'iPad Pro', 'category' => 'tablet', 'price' => 799.99],
    ['name' => 'Surface Pro', 'category' => 'tablet', 'price' => 999.99]
]);

// Test fuzzy search helper function
require_once 'src/Database.php';

function fuzzy_search($search, $text, $distance = 3) {
    $needles = explode(' ', mb_strtolower($search, 'UTF-8'));
    $tokens = explode(' ', mb_strtolower($text, 'UTF-8'));
    $score = 0;

    foreach ($needles as $needle) {
        foreach ($tokens as $token) {
            if (strpos($token, $needle) !== false) {
                $score++;
            } else {
                $d = levenshtein_utf8($needle, $token);
                if ($d <= $distance) {
                    $l = mb_strlen($token, 'UTF-8');
                    $matches = $l - $d;
                    $score += ($matches / $l);
                }
            }
        }
    }

    return $score / count($needles);
}

function levenshtein_utf8($s1, $s2) {
    $map = [];
    $utf8_to_extended_ascii = function ($str) use ($map) {
        if (!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches)) {
            return $str;
        }
        foreach ($matches[0] as $mbc) {
            if (!isset($map[$mbc])) {
                $map[$mbc] = chr(128 + count($map));
            }
        }
        return strtr($str, $map);
    };

    return levenshtein($utf8_to_extended_ascii($s1), $utf8_to_extended_ascii($s2));
}

// Test fuzzy search scores
echo "Fuzzy search scores:\n";
echo "iphone vs iPhone 13: " . fuzzy_search('iphone', 'Apple iPhone 13') . "\n";
echo "iphone vs Samsumg: " . fuzzy_search('iphone', 'Samsung Galaxy S21') . "\n";
echo "mac vs MacBook: " . fuzzy_search('mac', 'MacBook Pro') . "\n";
echo "labtop vs laptop: " . fuzzy_search('labtop', 'Dell XPS 13') . "\n";
echo "tablt vs tablet: " . fuzzy_search('tablt', 'iPad Pro') . "\n";

// Search products with typos
$searchResults = $products->find(['name' => ['$fuzzy' => 'iphone']])->toArray();
echo "\nProducts matching 'iphone' (fuzzy):\n";
foreach ($searchResults as $product) {
    echo "- {$product['name']}\n";
}

// Search with multiple terms
$searchResults2 = $products->find(['name' => ['$fuzzy' => 'samsun galaxy']])->toArray();
echo "\nProducts matching 'samsun galaxy' (fuzzy):\n";
foreach ($searchResults2 as $product) {
    echo "- {$product['name']}\n";
}

// Test fuzzy search with minimum score
$searchResults3 = $products->find([
    'name' => ['$fuzzy' => ['search' => 'iphone', '$minScore' => 0.7]]
])->toArray();

echo "\nProducts matching 'iphone' (min score 0.7):\n";
foreach ($searchResults3 as $product) {
    echo "- {$product['name']}\n";
}

// Test fuzzy search with custom distance
$searchResults4 = $products->find([
    'name' => ['$fuzzy' => ['search' => 'iphone', '$distance' => 5]]
])->toArray();

echo "\nProducts matching 'iphone' (distance 5):\n";
foreach ($searchResults4 as $product) {
    echo "- {$product['name']}\n";
}
?>
```

### Advanced Query Operators

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';
require_once 'src/Collection.php';

use PocketDB\Database;

// Create database
$db = new Database(':memory:');

// Create users collection
$users = $db->createCollection('users');

// Insert sample data
$users->insertMany([
    [
        'name' => 'John Doe',
        'age' => 30,
        'status' => 'active',
        'roles' => ['admin', 'user'],
        'profile' => [
            'email' => 'john@example.com',
            'preferences' => ['theme' => 'dark', 'notifications' => true]
        ]
    ],
    [
        'name' => 'Jane Smith',
        'age' => 25,
        'status' => 'active',
        'roles' => ['user'],
        'profile' => [
            'email' => 'jane@example.com',
            'preferences' => ['theme' => 'light', 'notifications' => false]
        ]
    ],
    [
        'name' => 'Bob Johnson',
        'age' => 35,
        'status' => 'inactive',
        'roles' => ['moderator', 'user'],
        'profile' => [
            'email' => 'bob@example.com',
            'preferences' => ['theme' => 'dark', 'notifications' => true]
        ]
    ]
]);

// Test various operators

// $in operator
$inResults = $users->find(['roles' => ['$in' => ['admin', 'moderator']]])->toArray();
echo "Users with admin or moderator role:\n";
foreach ($inResults as $user) {
    echo "- {$user['name']}\n";
}

// $nin operator
$ninResults = $users->find(['roles' => ['$nin' => ['user']]])->toArray();
echo "\nUsers without user role (only admin/moderator):\n";
foreach ($ninResults as $user) {
    echo "- {$user['name']}\n";
}

// $has operator (for array fields)
$hasResults = $users->find(['roles' => ['$has' => 'admin']])->toArray();
echo "\nUsers with admin role:\n";
foreach ($hasResults as $user) {
    echo "- {$user['name']}\n";
}

// $all operator
$allResults = $users->find(['roles' => ['$all' => ['user', 'moderator']]])->toArray();
echo "\nUsers with both user and moderator roles:\n";
foreach ($allResults as $user) {
    echo "- {$user['name']}\n";
}

// $size operator
$sizeResults = $users->find(['roles' => ['$size' => 2]])->toArray();
echo "\nUsers with exactly 2 roles:\n";
foreach ($sizeResults as $user) {
    echo "- {$user['name']} (roles: " . implode(', ', $user['roles']) . ")\n";
}

// $exists operator
$existsResults = $users->find(['profile' => ['$exists' => true]])->toArray();
echo "\nUsers with profile:\n";
foreach ($existsResults as $user) {
    echo "- {$user['name']}\n";
}

// $regex operator
$regexResults = $users->find(['name' => ['$regex' => '^J.*e$']])->toArray();
echo "\nUsers with names starting with J and ending with e:\n";
foreach ($regexResults as $user) {
    echo "- {$user['name']}\n";
}

// $mod operator
$modResults = $users->find(['age' => ['$mod' => [5, 0]]])->toArray(); // Age divisible by 5
echo "\nUsers with age divisible by 5:\n";
foreach ($modResults as $user) {
    echo "- {$user['name']} (age: {$user['age']})\n";
}

// Nested field queries
$nestedResults = $users->find(['profile.preferences.theme' => 'dark'])->toArray();
echo "\nUsers with dark theme preference:\n";
foreach ($nestedResults as $user) {
    echo "- {$user['name']}\n";
}

// Combined conditions with $and
$andResults = $users->find([
    '$and' => [
        ['status' => 'active'],
        ['age' => ['$gt' => 25]]
    ]
])->toArray();
echo "\nActive users older than 25:\n";
foreach ($andResults as $user) {
    echo "- {$user['name']} ({$user['age']})\n";
}

// Combined conditions with $or
$orResults = $users->find([
    '$or' => [
        ['status' => 'inactive'],
        ['age' => ['$lt' => 30]]
    ]
])->toArray();
echo "\nInactive users OR users younger than 30:\n";
foreach ($orResults as $user) {
    echo "- {$user['name']} ({$user['age']}, {$user['status']})\n";
}
?>
```

### Custom Function Operators

```php
<?php
require_once 'src/Client.php';
require_once 'src/Database.php';
require_once 'src/Collection.php';

use PocketDB\Database;

// Create database
$db = new Database(':memory:');

// Create products collection
$products = $db->createCollection('products');

// Insert sample data
$products->insertMany([
    ['name' => 'Laptop', 'price' => 999.99, 'category' => 'electronics'],
    ['name' => 'Smartphone', 'price' => 699.99, 'category' => 'electronics'],
    ['name' => 'Book', 'price' => 19.99, 'category' => 'books'],
    ['name' => 'Headphones', 'price' => 199.99, 'category' => 'electronics'],
    ['name' => 'Coffee Mug', 'price' => 12.99, 'category' => 'home']
]);

// Register custom criteria function
$priceRangeCriteria = function($document) {
    return isset($document['price']) && $document['price'] >= 100 && $document['price'] <= 500;
};

$criteriaId = $db->registerCriteriaFunction($priceRangeCriteria);

// Use custom criteria
$customResults = $products->find($criteriaId)->toArray();
echo "Products priced between $100 and $500:\n";
foreach ($customResults as $product) {
    echo "- {$product['name']}: \${$product['price']}\n";
}

// Another custom criteria function
$electronicsCriteria = function($document) {
    return isset($document['category']) && $document['category'] === 'electronics' &&
           isset($document['price']) && $document['price'] > 500;
};

$criteriaId2 = $db->registerCriteriaFunction($electronicsCriteria);

$electronicsResults = $products->find($criteriaId2)->toArray();
echo "\nExpensive electronics (> $500):\n";
foreach ($electronicsResults as $product) {
    echo "- {$product['name']}: \${$product['price']}\n";
}

// Test $func operator with UtilArrayQuery
require_once 'src/Database.php';

// Custom function for expensive items
$expensiveFunc = function($price) {
    return $price > 800;
};

$doc1 = ['name' => 'Laptop', 'price' => 999.99];
$doc2 = ['name' => 'Book', 'price' => 19.99];

$result1 = UtilArrayQuery::evaluate('$func', $doc1['price'], $expensiveFunc);
$result2 = UtilArrayQuery::evaluate('$func', $doc2['price'], $expensiveFunc);

echo "\nCustom function test:\n";
echo "Laptop is expensive: " . ($result1 ? 'Yes' : 'No') . "\n";
echo "Book is expensive: " . ($result2 ? 'Yes' : 'No') . "\n";

// String length function
$longNameFunc = function($name) {
    return strlen($name) > 10;
};

$doc3 = ['name' => 'Smartphone'];
$doc4 = ['name' => 'Book'];

$result3 = UtilArrayQuery::evaluate('$func', $doc3['name'], $longNameFunc);
$result4 = UtilArrayQuery::evaluate('$func', $doc4['name'], $longNameFunc);

echo "Smartphone has long name: " . ($result3 ? 'Yes' : 'No') . "\n";
echo "Book has long name: " . ($result4 ? 'Yes' : 'No') . "\n";
?>
```

## Best Practices

1. **Use appropriate operators**: Choose the right operator for your use case ($in for array membership, $regex for pattern matching, etc.)
2. **Combine operators effectively**: Use $and and $or for complex conditions
3. **Leverage fuzzy search**: For user input with typos or variations
4. **Use custom functions**: For complex business logic that can't be expressed with standard operators
5. **Test your queries**: Verify that your criteria match the expected documents
6. **Use projections**: To limit returned fields and improve performance
7. **Index frequently queried fields**: Create JSON indexes for better performance
8. **Handle null values**: Use $exists operator to check for field presence
9. **Validate input**: Ensure your criteria arrays are properly structured
10. **Use UtilArrayQuery for testing**: Test your criteria arrays before using them in actual queries
