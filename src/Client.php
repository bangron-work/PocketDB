<?php

namespace PocketDB;

/**
 * Client object for managing PocketDB databases.
 */
class Client
{
    /**
     * @var array<string,\PocketDB\Database>
     */
    protected array $databases = [];

    /**
     * @var string Database path
     */
    public string $path;

    /**
     * @var array Client options
     */
    protected array $options = [];

    /**
     * Path validation regex for database names.
     */
    private const DATABASE_NAME_REGEX = '/^[a-z0-9_-]+$/i';

    /**
     * Constructor.
     *
     * @param string $path    Pathname to database file or :memory:
     * @param array  $options Client options
     */
    public function __construct(string $path, array $options = [])
    {
        $this->path = $this->normalizePath($path);
        $this->options = $options;
    }

    /**
     * Normalize path by trimming slashes.
     */
    private function normalizePath(string $path): string
    {
        return \rtrim($path, '/\\');
    }

    /**
     * List Databases.
     *
     * @return array List of database names
     */
    public function listDBs(): array
    {
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->getMemoryDatabaseNames();
        }

        return $this->getDiskDatabaseNames();
    }

    /**
     * Get database names from memory.
     */
    private function getMemoryDatabaseNames(): array
    {
        return array_keys($this->databases);
    }

    /**
     * Get database names from disk.
     */
    private function getDiskDatabaseNames(): array
    {
        $databases = [];

        try {
            foreach (new \DirectoryIterator($this->path) as $fileInfo) {
                if ($this->isSqliteFile($fileInfo)) {
                    $databases[] = $fileInfo->getBasename('.sqlite');
                }
            }
        } catch (\Exception $e) {
            // Handle directory access errors gracefully
            return [];
        }

        return $databases;
    }

    /**
     * Check if file is SQLite database.
     */
    private function isSqliteFile(\SplFileInfo $fileInfo): bool
    {
        return $fileInfo->getExtension() === 'sqlite';
    }

    /**
     * Select Collection.
     *
     * @param string $database   Database name
     * @param string $collection Collection name
     */
    public function selectCollection(string $database, string $collection): Collection
    {
        return $this->selectDB($database)->selectCollection($collection);
    }

    /**
     * Select database.
     *
     * @param string $name Database name
     */
    public function selectDB(string $name): Database
    {
        $this->validateDatabaseName($name);

        if (!isset($this->databases[$name])) {
            $this->databases[$name] = $this->createDatabaseInstance($name);
        }

        return $this->databases[$name];
    }

    /**
     * Validate database name.
     */
    private function validateDatabaseName(string $name): void
    {
        if ($name !== Database::DSN_PATH_MEMORY && !preg_match(self::DATABASE_NAME_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid database name');
        }
    }

    /**
     * Create database instance.
     */
    private function createDatabaseInstance(string $name): Database
    {
        $dbPath = $this->buildDatabasePath($name);
        $database = new Database($dbPath, $this->options);

        // Attach back-reference to client
        $database->client = $this;

        return $database;
    }

    /**
     * Build database file path.
     */
    private function buildDatabasePath(string $name): string
    {
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->path;
        }

        return sprintf('%s/%s.sqlite', $this->path, $name);
    }

    /**
     * Helper to fetch nested values using dot notation from arrays.
     */
    private function getValueByDot(array $data, string $path): ?string
    {
        foreach (explode('.', $path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * Magic getter for database access.
     */
    public function __get(string $database): Database
    {
        return $this->selectDB($database);
    }

    /**
     * Close all database connections held by this client.
     */
    public function close(): void
    {
        foreach ($this->databases as $db) {
            if (is_object($db) && method_exists($db, 'close')) {
                $db->close();
            }
        }

        $this->databases = [];
    }

    /**
     * Destructor to ensure all connections are closed.
     */
    public function __destruct()
    {
        $this->close();
    }
}
