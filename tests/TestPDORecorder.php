<?php

namespace Tests;

/**
 * Simple PDO subclass that records executed SQL statements for assertions in tests.
 */
class TestPDOStatement extends \PDOStatement
{
    /** @var array<string> */
    public array $queries = [];

    protected function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    public function execute(?array $params = null): bool
    {
        // Reconstruct the query with parameters for recording
        $query = $this->queryString;
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $paramKey = is_int($key) ? '?' : ":$key";
                $query = preg_replace('/'.preg_quote($paramKey, '/').'/', is_string($value) ? "'$value'" : $value, $query, 1);
            }
        }

        $this->queries[] = $query;

        return parent::execute($params);
    }
}

class TestPDORecorder extends \PDO
{
    /** @var array<string> */
    public array $queries = [];

    public function __construct(string $dsn, ?string $username = null, ?string $passwd = null, array $options = [])
    {
        parent::__construct($dsn, $username ?? null, $passwd ?? null, $options);
        // Set error mode to exception for better test failures
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function query(string $statement, ?int $mode = null, ...$fetch_mode_args)
    {
        $this->queries[] = $statement;
        if ($mode === null) {
            return parent::query($statement);
        }

        return parent::query($statement, $mode, ...$fetch_mode_args);
    }

    public function exec(string $statement): int
    {
        $this->queries[] = $statement;

        return parent::exec($statement);
    }

    public function prepare(string $statement, ?array $options = null): \PDOStatement
    {
        $this->queries[] = $statement;

        // Create a custom statement that can record queries
        $stmt = new TestPDOStatement();
        $stmt->queryString = $statement;

        // Set the parent PDO connection
        $reflection = new \ReflectionClass($stmt);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($stmt, $this);

        return $stmt;
    }

    public function lastQuery(): ?string
    {
        if (empty($this->queries)) {
            return null;
        }

        return end($this->queries);
    }
}
