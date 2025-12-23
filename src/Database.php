<?php

namespace PocketDB;

/**
 * Database object for managing SQLite database connections and operations.
 */
class Database
{
    // Constants
    public const DSN_PATH_MEMORY = ':memory:';
    private const COLLECTION_NAME_REGEX = '/^[A-Za-z0-9_]+$/';
    private const IDENTIFIER_REGEX = '/^[A-Za-z0-9_]+$/';

    // Instance properties
    public string $path;
    public ?Client $client = null;
    public ?string $encryptionKey = null;
    protected array $options = [];
    protected array $collections = [];

    // Connection and criteria
    public $connection;
    protected array $document_criterias = [];

    // Static registry for managing database instances
    protected static array $criteria_registry = [];
    protected static array $instances = [];

    /**
     * Constructor.
     */
    public function __construct(string $path = self::DSN_PATH_MEMORY, array $options = [])
    {
        $this->path = $path;
        $this->options = $options;
        $this->encryptionKey = $options['encryption_key'] ?? null;

        $this->connection = $this->createConnection();
        $this->setupDatabaseFunctions();
        $this->configureDatabaseSettings();
        $this->registerInstance();
    }

    /**
     * Create PDO connection.
     */
    private function createConnection(): \PDO
    {
        $dsn = "sqlite:{$this->path}";

        return new \PDO($dsn, null, null, $this->options);
    }

    /**
     * Setup custom SQLite functions.
     */
    private function setupDatabaseFunctions(): void
    {
        $this->connection->sqliteCreateFunction('document_key', [$this, 'createDocumentKeyFunction'], 2);
        $this->connection->sqliteCreateFunction('document_criteria', ['\\PocketDB\\Database', 'staticCallCriteria'], 2);
    }

    /**
     * Configure database settings for performance.
     */
    private function configureDatabaseSettings(): void
    {
        // Prefer Write-Ahead Logging for better concurrency and reliability
        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA synchronous = NORMAL');
        $this->connection->exec('PRAGMA PAGE_SIZE = 4096');
    }

    /**
     * Register database instance for cleanup.
     */
    private function registerInstance(): void
    {
        if (class_exists('WeakReference')) {
            self::$instances[] = \WeakReference::create($this);
        } else {
            self::$instances[] = $this;
        }
    }

    /**
     * Document key function for SQLite.
     */
    public function createDocumentKeyFunction(string $key, $document): string
    {
        if ($document === null) {
            return '';
        }

        $document = json_decode($document, true);
        if ($document === null || !is_array($document)) {
            return '';
        }

        $value = $this->extractDocumentValue($document, $key);

        return is_array($value) || is_object($value) ? json_encode($value) : $value;
    }

    /**
     * Extract value from document using key (supports dot notation).
     */
    private function extractDocumentValue(array $document, string $key): string
    {
        if (strpos($key, '.') !== false) {
            return $this->extractNestedValue($document, $key);
        }

        return array_key_exists($key, $document) ? $document[$key] : '';
    }

    /**
     * Extract nested value using dot notation.
     */
    private function extractNestedValue(array $document, string $key): string
    {
        $keys = explode('.', $key);
        $ref = $document;

        foreach ($keys as $k) {
            if (!is_array($ref) || !array_key_exists($k, $ref)) {
                return '';
            }
            $ref = $ref[$k];
        }

        return $ref;
    }

    /**
     * Attach another SQLite database file to this connection with an alias.
     */
    public function attach(string $path, string $alias): bool
    {
        $this->validateAlias($alias);
        $quotedPath = $this->connection->quote($path);

        try {
            $this->connection->exec('ATTACH DATABASE '.$quotedPath.' AS '.$alias);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Validate database alias.
     */
    private function validateAlias(string $alias): void
    {
        if (!preg_match(self::IDENTIFIER_REGEX, $alias)) {
            throw new \InvalidArgumentException('Invalid alias for attach: '.$alias);
        }
    }

    /**
     * Detach previously attached database alias.
     */
    public function detach(string $alias): bool
    {
        $this->validateAlias($alias);

        try {
            $this->connection->exec('DETACH DATABASE '.$alias);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Attach, run a callback, then detach in a safe finally block.
     */
    public function attachOnce(string $path, string $alias, callable $callback)
    {
        if (!$this->attach($path, $alias)) {
            throw new \RuntimeException('Failed to attach database: '.$path);
        }

        try {
            return $callback($this, $alias);
        } finally {
            $this->safeDetach($alias);
        }
    }

    /**
     * Safely detach database ignoring errors.
     */
    private function safeDetach(string $alias): void
    {
        try {
            $this->detach($alias);
        } catch (\Throwable $_) {
            // Ignore detach failures but ensure we attempted it
        }
    }

    /**
     * Close all known Database instances (best-effort).
     */
    public static function closeAll(): void
    {
        foreach (self::$instances as $key => $ref) {
            self::closeInstance($ref, $key);
        }
        self::$instances = [];
    }

    /**
     * Close a single database instance.
     */
    private static function closeInstance($ref, int $key): void
    {
        if (is_object($ref) && $ref instanceof \WeakReference) {
            $db = $ref->get();
            if ($db) {
                $db->close();
            }
        } elseif (is_object($ref)) {
            $ref->close();
        }

        unset(self::$instances[$key]);
    }

    /**
     * Close the database connection.
     */
    public function close(): void
    {
        $this->cleanupCriteriaRegistry();
        $this->document_criterias = [];
        $this->connection = null;
    }

    /**
     * Clean up criteria registry.
     */
    private function cleanupCriteriaRegistry(): void
    {
        foreach (array_keys($this->document_criterias) as $id) {
            if (isset(self::$criteria_registry[$id])) {
                unset(self::$criteria_registry[$id]);
            }
        }
    }

    /**
     * Destructor to ensure connection is closed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Register Criteria function.
     */
    public function registerCriteriaFunction($criteria): ?string
    {
        $id = uniqid('criteria');

        if (is_callable($criteria)) {
            return $this->registerCallableCriteria($id, $criteria);
        }

        if (is_array($criteria)) {
            return $this->registerArrayCriteria($id, $criteria);
        }

        return null;
    }

    /**
     * Register callable criteria function.
     */
    private function registerCallableCriteria(string $id, callable $criteria): string
    {
        $this->document_criterias[$id] = $criteria;
        $this->registerWeakReference($id);

        return $id;
    }

    /**
     * Register array-based criteria function.
     */
    private function registerArrayCriteria(string $id, array $criteria): string
    {
        $fn = function ($document) use ($criteria) {
            if (!is_array($document)) {
                return false;
            }

            return UtilArrayQuery::match($criteria, $document);
        };

        $this->document_criterias[$id] = $fn;
        $this->registerWeakReference($id);

        return $id;
    }

    /**
     * Register weak reference for criteria.
     */
    private function registerWeakReference(string $id): void
    {
        if (class_exists('WeakReference')) {
            self::$criteria_registry[$id] = \WeakReference::create($this);
        } else {
            self::$criteria_registry[$id] = $this;
        }
    }

    /**
     * Execute registered criteria function.
     */
    public function callCriteriaFunction(string $id, $document): bool
    {
        return isset($this->document_criterias[$id])
            ? $this->document_criterias[$id]($document)
            : false;
    }

    /**
     * Static entrypoint called by SQLite extension.
     */
    public static function staticCallCriteria(string $id, $document): bool
    {
        if (!isset(self::$criteria_registry[$id])) {
            return false;
        }

        $db = self::resolveDatabaseReference(self::$criteria_registry[$id]);
        if ($db === null) {
            unset(self::$criteria_registry[$id]);

            return false;
        }

        if ($document === null) {
            return false;
        }

        $document = json_decode($document, true);

        return $db->callCriteriaFunction($id, $document);
    }

    /**
     * Resolve database reference from registry.
     */
    private static function resolveDatabaseReference($ref): ?Database
    {
        if (is_object($ref) && $ref instanceof \WeakReference) {
            return $ref->get();
        }

        return $ref;
    }

    /**
     * Vacuum database to reclaim space.
     */
    public function vacuum(): void
    {
        $this->connection->query('VACUUM');
    }

    /**
     * Drop database file (for non-memory databases).
     */
    public function drop(): void
    {
        if ($this->path !== static::DSN_PATH_MEMORY) {
            $this->close();
            unlink($this->path);
        }
    }

    /**
     * Create a collection.
     */
    public function createCollection(string $name): void
    {
        $this->validateCollectionName($name);
        $this->executeCreateCollection($name);
    }

    /**
     * Validate collection name.
     */
    private function validateCollectionName(string $name): void
    {
        if (!preg_match(self::COLLECTION_NAME_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid collection name: '.$name);
        }
    }

    /**
     * Execute collection creation.
     */
    private function executeCreateCollection(string $name): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$name}` ( id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT )";
        $this->connection->exec($sql);
    }

    /**
     * Drop a collection.
     */
    public function dropCollection(string $name): void
    {
        $this->validateCollectionName($name);
        $this->executeDropCollection($name);
        $this->removeCollectionFromCache($name);
    }

    /**
     * Execute collection drop.
     */
    private function executeDropCollection(string $name): void
    {
        $sql = "DROP TABLE IF EXISTS `{$name}`";
        $this->connection->exec($sql);
    }

    /**
     * Remove collection from cache.
     */
    private function removeCollectionFromCache(string $name): void
    {
        unset($this->collections[$name]);
    }

    /**
     * Get all collection names in the database.
     */
    public function getCollectionNames(): array
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence'";
        $stmt = $this->connection->query($sql);
        $tables = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return array_column($tables, 'name');
    }

    /**
     * Get all collections in the database.
     */
    public function listCollections(): array
    {
        foreach ($this->getCollectionNames() as $name) {
            $this->ensureCollectionLoaded($name);
        }

        return $this->collections;
    }

    /**
     * Ensure collection is loaded into cache.
     */
    private function ensureCollectionLoaded(string $name): void
    {
        if (!isset($this->collections[$name])) {
            if (!in_array($name, $this->getCollectionNames())) {
                $this->createCollection($name);
            }
            $this->collections[$name] = new Collection($name, $this);
        }
    }

    /**
     * Select collection.
     */
    public function selectCollection(string $name): Collection
    {
        $this->ensureCollectionLoaded($name);

        return $this->collections[$name];
    }

    /**
     * Magic getter for collection access.
     */
    public function __get(string $collection): Collection
    {
        return $this->selectCollection($collection);
    }

    /**
     * Create an index for a JSON field using json_extract(document, '$.field').
     */
    public function createJsonIndex(string $collection, string $field, ?string $indexName = null): void
    {
        $this->validateCollectionName($collection);

        $indexName = $indexName ?? $this->generateIndexName($collection, $field);
        $path = '$.'.str_replace("'", "\\'", $field);
        $sql = 'CREATE INDEX IF NOT EXISTS '.$indexName.' ON '.$collection.
               " (json_extract(document, '".$path."'))";

        $this->connection->exec($sql);
    }

    /**
     * Generate index name.
     */
    private function generateIndexName(string $collection, string $field): string
    {
        $sanitizedField = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);

        return sprintf('idx_%s_%s', $collection, $sanitizedField);
    }

    /**
     * Quote an identifier (table/index/column name) in a safe manner.
     */
    public function quoteIdentifier(string $name): string
    {
        if (!preg_match(self::IDENTIFIER_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid identifier: '.$name);
        }

        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * Drop an index by name.
     */
    public function dropIndex(string $indexName): void
    {
        $sql = 'DROP INDEX IF EXISTS '.$indexName;
        $this->connection->exec($sql);
    }
}

class UtilArrayQuery
{
    public static function buildCondition($criteria, $concat = ' && ')
    {
        $fn = [];

        foreach ($criteria as $key => $value) {
            switch ($key) {
                case '$and':
                    $_fn = [];

                    foreach ($value as $v) {
                        $_fn[] = self::buildCondition($v, ' && ');
                    }

                    $fn[] = '('.\implode(' && ', $_fn).')';

                    break;
                case '$or':
                    $_fn = [];

                    foreach ($value as $v) {
                        $_fn[] = self::buildCondition($v, ' && ');
                    }

                    $fn[] = '('.\implode(' || ', $_fn).')';

                    break;

                case '$where':
                    if (\is_callable($value)) {
                        // need implementation
                    }

                    break;

                default:
                    $d = '$document';

                    if (\strpos($key, '.') !== false) {
                        $keys = \explode('.', $key);

                        foreach ($keys as $k) {
                            $d .= '[\''.$k.'\']';
                        }
                    } else {
                        $d .= '[\''.$key.'\']';
                    }

                    if (\is_array($value)) {
                        $fn[] = "\\PocketDB\\UtilArrayQuery::check((isset({$d}) ? {$d} : null), ".\var_export($value, true).')';
                    } else {
                        $_value = \var_export($value, true);

                        $fn[] = "(isset({$d}) && (
                                    is_array({$d}) && is_string({$_value})
                                        ? in_array({$_value}, {$d})
                                        : {$d}=={$_value}
                                    )
                                )";
                    }
            }
        }

        return \count($fn) ? \trim(\implode($concat, $fn)) : 'true';
    }

    public static function check($value, $condition)
    {
        $keys = \array_keys($condition);

        foreach ($keys as &$key) {
            if ($key == '$options') {
                continue;
            }

            if (!self::evaluate($key, $value, $condition[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match a full criteria array against a document array.
     * Supports $and, $or, $where and field comparisons (delegates to check()).
     */
    public static function match($criteria, $document)
    {
        if (!\is_array($criteria)) {
            return false;
        }

        foreach ($criteria as $key => $value) {
            switch ($key) {
                case '$and':
                    foreach ($value as $v) {
                        if (!self::match($v, $document)) {
                            return false;
                        }
                    }
                    break;

                case '$or':
                    $ok = false;
                    foreach ($value as $v) {
                        if (self::match($v, $document)) {
                            $ok = true;
                            break;
                        }
                    }
                    if (!$ok) {
                        return false;
                    }
                    break;

                case '$where':
                    if (\is_callable($value)) {
                        if (!$value($document)) {
                            return false;
                        }
                    }
                    break;

                default:
                    // resolve nested field value
                    $d = $document;
                    if (\strpos($key, '.') !== false) {
                        $keys = \explode('.', $key);
                        foreach ($keys as $k) {
                            if (!\is_array($d) || !\array_key_exists($k, $d)) {
                                $d = null;
                                break;
                            }
                            $d = $d[$k];
                        }
                    } else {
                        $d = \array_key_exists($key, $d) ? $d[$key] : null;
                    }

                    if (\is_array($value)) {
                        if (!self::check($d, $value)) {
                            return false;
                        }
                    } else {
                        if ($d != $value) {
                            return false;
                        }
                    }
            }
        }

        return true;
    }

    private static function evaluate($func, $a, $b)
    {
        $r = false;

        if (\is_null($a) && $func != '$exists') {
            return false;
        }

        switch ($func) {
            case '$eq':
                $r = $a == $b;
                break;

            case '$ne':
                $r = $a != $b;
                break;

            case '$gte':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a >= $b;
                }
                break;

            case '$gt':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a > $b;
                }
                break;

            case '$lte':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a <= $b;
                }
                break;

            case '$lt':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a < $b;
                }
                break;

            case '$in':
                if (\is_array($a)) {
                    $r = \is_array($b) ? \count(\array_intersect($a, $b)) : false;
                } else {
                    $r = \is_array($b) ? \in_array($a, $b) : false;
                }
                break;

            case '$nin':
                if (\is_array($a)) {
                    $r = \is_array($b) ? (\count(\array_intersect($a, $b)) === 0) : false;
                } else {
                    $r = \is_array($b) ? (\in_array($a, $b) === false) : false;
                }
                break;

            case '$has':
                if (\is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $has array not supported');
                }
                if (!\is_array($a)) {
                    $a = @\json_decode($a, true) ?: [];
                }
                $r = \in_array($b, $a);
                break;

            case '$all':
                if (!\is_array($a)) {
                    $a = @\json_decode($a, true) ?: [];
                }
                if (!\is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $all option must be array');
                }
                $r = \count(\array_intersect_key($a, $b)) == \count($b);
                break;

            case '$regex':
            case '$preg':
            case '$match':
            case '$not':
                $r = (bool) @\preg_match(isset($b[0]) && $b[0] == '/' ? $b : '/'.$b.'/iu', $a, $match);
                if ($func === '$not') {
                    $r = !$r;
                }
                break;

            case '$size':
                if (!\is_array($a)) {
                    $a = @\json_decode($a, true) ?: [];
                }
                $r = (int) $b == \count($a);
                break;

            case '$mod':
                if (!\is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $mod option must be array');
                }
                $r = $a % $b[0] == $b[1] ?? 0;
                break;

            case '$func':
            case '$fn':
            case '$f':
                if (!\is_callable($b)) {
                    throw new \InvalidArgumentException('Function should be callable');
                }
                $r = $b($a);
                break;

            case '$exists':
                $r = $b ? !\is_null($a) : \is_null($a);
                break;

            case '$fuzzy':
            case '$text':
                $distance = 3;
                $minScore = 0.7;

                if (\is_array($b) && isset($b['$search'])) {
                    if (isset($b['$minScore']) && \is_numeric($b['$minScore'])) {
                        $minScore = $b['$minScore'];
                    }
                    if (isset($b['$distance']) && \is_numeric($b['$distance'])) {
                        $distance = $b['$distance'];
                    }

                    $b = $b['search'];
                }

                $r = fuzzy_search($b, $a, $distance) >= $minScore;
                break;

            default:
                throw new \ErrorException("Condition not valid ... Use {$func} for custom operations");
                break;
        }

        return $r;
    }
}

// Helper Functions
function levenshtein_utf8($s1, $s2)
{
    $map = [];
    $utf8_to_extended_ascii = function ($str) use ($map) {
        // find all multibyte characters (cf. utf-8 encoding specs)
        $matches = [];

        if (!\preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches)) {
            return $str;
        } // plain ascii string

        // update the encoding map with the characters not already met
        foreach ($matches[0] as $mbc) {
            if (!isset($map[$mbc])) {
                $map[$mbc] = \chr(128 + \count($map));
            }
        }

        // finally remap non-ascii characters
        return \strtr($str, $map);
    };

    return levenshtein($utf8_to_extended_ascii($s1), $utf8_to_extended_ascii($s2));
}

function fuzzy_search($search, $text, $distance = 3)
{
    $needles = \explode(' ', \mb_strtolower($search, 'UTF-8'));
    $tokens = \explode(' ', \mb_strtolower($text, 'UTF-8'));
    $score = 0;

    foreach ($needles as $needle) {
        foreach ($tokens as $token) {
            if (\strpos($token, $needle) !== false) {
                ++$score;
            } else {
                $d = levenshtein_utf8($needle, $token);

                if ($d <= $distance) {
                    $l = \mb_strlen($token, 'UTF-8');
                    $matches = $l - $d;
                    $score += ($matches / $l);
                }
            }
        }
    }

    return $score / \count($needles);
}

function createMongoDbLikeId()
{
    // Generate a UUID v4
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        random_int(0, 0xFFFF),
        random_int(0, 0xFFFF),

        // 16 bits for "time_mid"
        random_int(0, 0xFFFF),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        random_int(0, 0x0FFF) | 0x4000,

        // 16 bits, 8 bits for "clk_hi_res",
        // 8 bits for "clk_hi_res",
        // two most significant bits holds zero and one for variant DCE1.1
        random_int(0, 0x3FFF) | 0x8000,

        // 48 bits for "node"
        random_int(0, 0xFFFF),
        random_int(0, 0xFFFF),
        random_int(0, 0xFFFF)
    );
}
