<?php

namespace PocketDB;

/**
 * Database object.
 */
class Database
{
    /**
     * @var string - DSN path form memory database
     */
    public const DSN_PATH_MEMORY = ':memory:';

    /** @var \PDO|mixed */
    public $connection;

    /**
     * @var array<string,\PocketDB\Collection>
     */
    protected array $collections = [];

    public string $path;

    /**
     * Back-reference to the owning Client (if any).
     */
    public ?Client $client = null;

    /**
     * @var array<string,callable>
     */
    protected array $document_criterias = [];

    /**
     * Static registry for criteria id -> weak reference of Database.
     *
     * @var array<string,mixed>
     */
    protected static array $criteria_registry = [];
    /**
     * Weak refs to all Database instances (to allow closing lingering connections).
     *
     * @var array<int,mixed>
     */
    protected static array $instances = [];

    /**
     * Constructor.
     */
    public function __construct(string $path = self::DSN_PATH_MEMORY, array $options = [])
    {
        $dns = "sqlite:{$path}";
        $this->path = $path;
        $this->connection = new \PDO($dns, null, null, $options);

        $database = $this;

        $this->connection->sqliteCreateFunction('document_key', function ($key, $document) {
            // Handle null document case
            if ($document === null) {
                return '';
            }

            $document = \json_decode($document, true);

            if ($document === null || !is_array($document)) {
                return '';
            }

            $val = '';

            if (strpos($key, '.') !== false) {
                $keys = \explode('.', $key);
                $ref = $document;
                foreach ($keys as $k) {
                    if (!is_array($ref) || !array_key_exists($k, $ref)) {
                        $ref = null;
                        break;
                    }
                    $ref = $ref[$k];
                }
                $val = $ref ?? '';
            } else {
                $val = array_key_exists($key, $document) ? $document[$key] : '';
            }

            return \is_array($val) || \is_object($val) ? \json_encode($val) : $val;
        }, 2);

        $this->connection->sqliteCreateFunction('document_criteria', ['\\PocketDB\\Database', 'staticCallCriteria'], 2);

        // Prefer Write-Ahead Logging for better concurrency and reliability
        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA synchronous = NORMAL');
        $this->connection->exec('PRAGMA PAGE_SIZE = 4096');

        // register weak reference to this instance
        if (class_exists('WeakReference')) {
            self::$instances[] = \WeakReference::create($this);
        } else {
            self::$instances[] = $this;
        }
    }

    /**
     * Attach another SQLite database file to this connection with an alias.
     * Returns true on success.
     *
     * Note: alias must be a simple identifier (letters, numbers, underscore).
     */
    public function attach(string $path, string $alias): bool
    {
        // validate alias
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $alias)) {
            throw new \InvalidArgumentException('Invalid alias for attach: '.$alias);
        }

        // ensure absolute path when possible
        $p = $path;

        // quote path for SQL
        $quoted = $this->connection->quote($p);

        try {
            $this->connection->exec('ATTACH DATABASE '.$quoted.' AS '.$alias);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Detach previously attached database alias. Returns true on success.
     */
    public function detach(string $alias): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $alias)) {
            throw new \InvalidArgumentException('Invalid alias for detach: '.$alias);
        }

        try {
            $this->connection->exec('DETACH DATABASE '.$alias);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Attach, run a callback, then detach in a safe finally block.
     * The callback receives the current Database instance and the alias string.
     * Returns whatever the callback returns.
     * Throws if attach fails or if the callback throws (callback exception is rethrown after detach).
     */
    public function attachOnce(string $path, string $alias, callable $callback)
    {
        if (!$this->attach($path, $alias)) {
            throw new \RuntimeException('Failed to attach database: '.$path);
        }

        try {
            return $callback($this, $alias);
        } finally {
            // best-effort detach; ignore detach failures but ensure we attempted it
            try {
                $this->detach($alias);
            } catch (\Throwable $_) {
            }
        }
    }

    /**
     * Close all known Database instances (best-effort).
     */
    public static function closeAll(): void
    {
        foreach (self::$instances as $k => $ref) {
            if (is_object($ref) && $ref instanceof \WeakReference) {
                $db = $ref->get();
                if ($db) {
                    $db->close();
                }
            } elseif (is_object($ref)) {
                $ref->close();
            }
            unset(self::$instances[$k]);
        }
        self::$instances = [];
    }

    /**
     * Close the database connection.
     */
    public function close(): void
    {
        // cleanup registered criteria weak refs
        foreach (array_keys($this->document_criterias) as $id) {
            if (isset(self::$criteria_registry[$id])) {
                unset(self::$criteria_registry[$id]);
            }
        }

        $this->document_criterias = [];

        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Register Criteria function.
     *
     * @return mixed
     */
    public function registerCriteriaFunction($criteria): ?string
    {
        $id = \uniqid('criteria');

        if (\is_callable($criteria)) {
            $this->document_criterias[$id] = $criteria;

            return $id;
        }

        if (is_array($criteria)) {
            // Build a pure PHP closure that uses UtilArrayQuery::check to evaluate
            // the criteria against decoded document arrays. Avoid any string
            // evaluation or eval() usage to eliminate code injection risks.
            $fn = function ($document) use ($criteria) {
                // Expect $document to be an array (staticCallCriteria decodes JSON)
                if (!\is_array($document)) {
                    return false;
                }

                return UtilArrayQuery::match($criteria, $document);
            };

            $this->document_criterias[$id] = $fn;
            // store weak reference in static registry so sqlite callback can find this DB without
            // creating a strong reference cycle
            if (class_exists('WeakReference')) {
                self::$criteria_registry[$id] = \WeakReference::create($this);
            } else {
                // fallback (older PHP) - store strong ref (may keep object alive)
                self::$criteria_registry[$id] = $this;
            }

            return $id;
        }

        return null;
    }

    /**
     * Execute registred criteria function.
     *
     * @param array $document
     */
    public function callCriteriaFunction(string $id, $document): bool
    {
        return isset($this->document_criterias[$id]) ? $this->document_criterias[$id]($document) : false;
    }

    /**
     * Static entrypoint called by SQLite extension.
     */
    public static function staticCallCriteria(string $id, $document): bool
    {
        if (!isset(self::$criteria_registry[$id])) {
            return false;
        }

        $ref = self::$criteria_registry[$id];

        if (is_object($ref) && $ref instanceof \WeakReference) {
            $db = $ref->get();
            if ($db === null) {
                unset(self::$criteria_registry[$id]);

                return false;
            }
        } else {
            $db = $ref;
        }

        if ($document === null) {
            return false;
        }

        $document = \json_decode($document, true);

        return $db->callCriteriaFunction($id, $document);
    }

    /**
     * Vacuum database.
     */
    public function vacuum(): void
    {
        $this->connection->query('VACUUM');
    }

    /**
     * Drop database.
     */
    public function drop(): void
    {
        if ($this->path != static::DSN_PATH_MEMORY) {
            // ensure connection closed before unlinking file on Windows
            $this->close();
            \unlink($this->path);
        }
    }

    /**
     * Create a collection.
     */
    public function createCollection(string $name): void
    {
        $this->connection->exec("CREATE TABLE IF NOT EXISTS `{$name}` ( id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT )");
    }

    /**
     * Drop a collection.
     */
    public function dropCollection(string $name): void
    {
        $this->connection->exec("DROP TABLE IF EXISTS `{$name}`");

        // Remove collection from cache
        unset($this->collections[$name]);
    }

    /**
     * Get all collection names in the database.
     */
    public function getCollectionNames(): array
    {
        $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence';");
        $tables = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $names = [];

        foreach ($tables as $table) {
            $names[] = $table['name'];
        }

        return $names;
    }

    /**
     * Get all collections in the database.
     */
    public function listCollections(): array
    {
        foreach ($this->getCollectionNames() as $name) {
            if (!isset($this->collections[$name])) {
                $this->collections[$name] = new Collection($name, $this);
            }
        }

        return $this->collections;
    }

    /**
     * Select collection.
     *
     * @return object
     */
    public function selectCollection(string $name): Collection
    {
        if (!isset($this->collections[$name])) {
            if (!in_array($name, $this->getCollectionNames())) {
                $this->createCollection($name);
            }

            $this->collections[$name] = new Collection($name, $this);
        }

        return $this->collections[$name];
    }

    public function __get(string $collection): Collection
    {
        return $this->selectCollection($collection);
    }

    /**
     * Create an index for a JSON field using json_extract(document, '$.field').
     */
    public function createJsonIndex(string $collection, string $field, ?string $indexName = null): void
    {
        $name = $indexName ?? sprintf('idx_%s_%s', $collection, preg_replace('/[^a-zA-Z0-9_]/', '_', $field));
        $path = '$.'.str_replace("'", "\\'", $field);
        $sql = 'CREATE INDEX IF NOT EXISTS '.$name.' ON '.$collection." (json_extract(document, '".$path."'))";
        $this->connection->exec($sql);
    }

    /**
     * Drop an index by name.
     */
    public function dropIndex(string $indexName): void
    {
        $this->connection->exec('DROP INDEX IF EXISTS '.$indexName);
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
