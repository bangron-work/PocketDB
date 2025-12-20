<?php

namespace PocketDB;

/**
 * Client object.
 */
class Client
{

    /**
     * @var array<string,\PocketDB\Database>
     */
    protected array $databases = [];

    /**
     * @var string
     */
    public string $path;

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * Constructor
     *
     * @param string $path - Pathname to database file or :memory:
     * @param array  $options
     */
    public function __construct(string $path, array $options = [])
    {
        $this->path    = \rtrim($path, '\\');
        $this->options = $options;
    }

    /**
     * List Databases
     *
     * @return array List of database names
     */
    public function listDBs(): array
    {

        // Return all databases available in memory
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return array_keys($this->databases);
        }

        // Return all databases available on disk
        $databases = [];

        foreach (new \DirectoryIterator($this->path) as $fileInfo) {
            if ($fileInfo->getExtension() === 'sqlite') {
                $databases[] = $fileInfo->getBasename('.sqlite');
            }
        }

        return $databases;
    }

    /**
     * Select Collection
     *
     * @param  string $database
     * @param  string $collection
     * @return Collection
     */
    public function selectCollection(string $database, string $collection): Collection
    {
        return $this->selectDB($database)->selectCollection($collection);
    }

    /**
     * Select database
     *
     * @param  string $name
     * @return Database
     */
    public function selectDB(string $name): Database
    {

        if (!isset($this->databases[$name])) {
            $this->databases[$name] = new Database(
                $this->path === Database::DSN_PATH_MEMORY ? $this->path : sprintf('%s/%s.sqlite', $this->path, $name),
                $this->options
            );
            // attach back-reference to client
            if (is_object($this->databases[$name])) {
                $this->databases[$name]->client = $this;
            }
        }

        return $this->databases[$name];
    }

    public function __get(string $database): Database
    {
        return $this->selectDB($database);
    }

    /**
     * Close all database connections held by this client
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

    public function __destruct()
    {
        $this->close();
    }
}
