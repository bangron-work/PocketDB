<?php

namespace PocketDB;

/**
 * Cursor object.
 */
class Cursor implements \Iterator
{

    /**
     * @var int
     */
    protected int $position = 0;

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var \PDOStatement|null
     */
    protected ?\PDOStatement $stmt = null;

    /**
     * @var array|null current fetched row decoded
     */
    protected ?array $currentRow = null;

    /**
     * @var string|null - native SQL WHERE clause (json_extract based)
     */
    protected ?string $criteriaSql = null;

    /**
     * @var int last known valid key index
     */
    protected int $lastKey = -1;

    /**
     * @var \PocketDB\Collection
     */
    public Collection $collection;

    /**
     * @var mixed
     */
    public $criteria;

    /**
     * @var array|null
     */
    protected ?array $projection = null;

    /**
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * @var int|null
     */
    protected ?int $skip = null;

    /**
     * @var array|null
     */
    protected ?array $sort = null;

    /**
     * Constructor
     *
     * @param object $collection
     * @param mixed $criteria
     */
    public function __construct(Collection $collection, $criteria, $projection = null, ?string $criteriaSql = null)
    {
        $this->collection  = $collection;
        $this->criteria    = $criteria;
        $this->projection  = $projection;
        $this->criteriaSql = $criteriaSql;
        $this->position    = 0;
        $this->data        = [];
        $this->lastKey     = -1;
    }

    /**
     * Documents count
     *
     * @return integer
     */
    public function count(): int
    {
        // Gunakan SQL untuk menghitung, jangan iterasi manual while()
        $sqlSelect = 'SELECT COUNT(*) as c FROM ' . $this->collection->name;
        $where = '';

        if (!empty($this->criteriaSql)) {
            $where = ' WHERE ' . $this->criteriaSql;
        } elseif ($this->criteria) {
            $where = ' WHERE document_criteria("' . $this->criteria . '", document)';
        }

        if ($this->limit || $this->skip) {
            // Jika ada limit, kita harus menghitung hasil dari subquery
            $limitSql = $this->limit ? " LIMIT " . $this->limit : " LIMIT -1";
            $offsetSql = $this->skip ? " OFFSET " . $this->skip : "";
            $sql = "SELECT COUNT(*) as c FROM (SELECT 1 FROM {$this->collection->name} {$where} {$limitSql} {$offsetSql})";
        } else {
            $sql = $sqlSelect . $where;
        }

        try {
            $stmt = $this->collection->database->connection->query($sql);
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
            return $row ? intval($row['c']) : 0;
        } catch (\PDOException $e) {
            return 0;
        }
    }

    /**
     * Set limit
     *
     * @param  mixed $limit
     * @return object       Cursor
     */
    public function limit(int $limit): self
    {
        $this->limit = intval($limit);
        $this->data = []; // Reset data when limit changes
        return $this;
    }

    /**
     * Set sort
     *
     * @param  mixed $sorts
     * @return object       Cursor
     */
    public function sort($sorts): self
    {
        $this->sort = $sorts;
        $this->data = []; // Reset data when sort changes
        return $this;
    }

    /**
     * Set skip
     *
     * @param  mixed $skip
     * @return object       Cursor
     */
    public function skip(int $skip): self
    {
        $this->skip = $skip;
        $this->data = []; // Reset data when skip changes
        return $this;
    }

    /**
     * Loop through result set
     *
     * @param  mixed $callable
     * @return object
     */
    public function each($callable): self
    {
        $this->rewind();
        while ($document = $this->current()) {
            $callable($document);
            $this->next();
        }
        return $this;
    }

    /**
     * Get documents matching criteria
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getData();
    }


    /**
     * Get documents matching criteria
     *
     * @return array
     */
    protected function getData(): array
    {
        // This method is no longer fetching all rows into memory. Instead it's
        // used as a helper for consumers that expect an array. We'll iterate
        // the statement and decode rows one by one to avoid fetchAll().
        $documents = [];
        $this->rewind();
        while ($this->valid()) {
            $documents[] = $this->current();
            $this->next();
        }
        return $documents;
    }

    /**
     * Iterator implementation
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = 0;
        $this->data = [];

        $sql = ['SELECT document FROM ' . $this->collection->name];

        if (!empty($this->criteriaSql)) {
            $sql[] = 'WHERE ' . $this->criteriaSql;
        } elseif ($this->criteria) {
            $sql[] = 'WHERE document_criteria("' . $this->criteria . '", document)';
        }

        if ($this->sort) {
            $orders = [];
            foreach ($this->sort as $field => $direction) {
                $orders[] = 'document_key("' . $field . '", document) ' . ($direction == -1 ? 'DESC' : 'ASC');
            }
            $sql[] = 'ORDER BY ' . \implode(',', $orders);
        }

        if ($this->limit) {
            $sql[] = 'LIMIT ' . $this->limit;

            if ($this->skip) {
                $sql[] = 'OFFSET ' . $this->skip;
            }
        }

        // Allow OFFSET without LIMIT
        if (!$this->limit && $this->skip) {
            $sql[] = 'LIMIT -1 OFFSET ' . intval($this->skip);
        }

        $sql = implode(' ', $sql);

        try {
            $this->stmt = $this->collection->database->connection->query($sql);
        } catch (\PDOException $e) {
            $this->stmt = null;
        }

        if ($this->stmt) {
            $row = $this->stmt->fetch(\PDO::FETCH_ASSOC);
            $this->currentRow = $row ? $this->decodeAndProject($row['document']) : null;
            $this->lastKey = $this->currentRow !== null ? 0 : -1;
        } else {
            $this->currentRow = null;
            $this->lastKey = -1;
        }
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        // Only auto-rewind when iteration hasn't started (position==0).
        // This prevents an exhausted cursor from rewinding and causing
        // an infinite loop when consumers call current() in loop conditions.
        if ($this->currentRow === null && $this->position === 0) {
            $this->rewind();
        }

        return $this->currentRow;
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        if ($this->position <= $this->lastKey) return $this->position;
        return $this->lastKey >= 0 ? $this->lastKey : 0;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->position;

        if ($this->stmt) {
            $row = $this->stmt->fetch(\PDO::FETCH_ASSOC);
            $this->currentRow = $row ? $this->decodeAndProject($row['document']) : null;
            if ($this->currentRow !== null) {
                $this->lastKey = $this->position;
            }
        } else {
            $this->currentRow = null;
        }
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->currentRow !== null;
    }

    /**
     * Decode JSON document and apply projection if any
     */
    protected function decodeAndProject($json): ?array
    {
        $item = \json_decode($json, true);

        if (!$this->projection) return $item;

        $exclude = [];
        $include = [];

        foreach ($this->projection as $key => $value) {
            if ($value) {
                $include[$key] = 1;
            } else {
                $exclude[$key] = 1;
            }
        }

        $id = $item['_id'] ?? null;

        if ($exclude) {
            $item = \array_diff_key($item, $exclude);
        }

        if ($include) {
            $item = array_key_intersect($item, $include);
        }

        if ($id !== null && !isset($exclude['_id'])) {
            $item['_id'] = $id;
        }

        return $item;
    }
}

function array_key_intersect($a, $b)
{
    $array = [];

    foreach ($a as $key => $value) {
        if (isset($b[$key])) $array[$key] = $value;
    }

    return $array;
}
