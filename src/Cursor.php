<?php

namespace PocketDB;

class Cursor implements \IteratorAggregate
{
    protected int $position = 0;
    protected ?\PDOStatement $stmt = null;
    protected ?array $currentRow = null;
    protected ?string $criteriaSql = null;
    protected int $lastKey = -1;

    public Collection $collection;
    public $criteria;

    protected ?array $projection = null;
    protected ?int $limit = null;
    protected ?int $skip = null;
    protected ?array $sort = null;

    /* populate rules */
    protected array $populate = [];

    public function __construct(Collection $collection, $criteria, $projection = null, ?string $criteriaSql = null)
    {
        $this->collection = $collection;
        $this->criteria = $criteria;
        $this->projection = $projection;
        $this->criteriaSql = $criteriaSql;
    }

    /* ================= LEGACY ================= */

    public function count(): int
    {
        $table = $this->collection->database->quoteIdentifier($this->collection->name);
        $sql = 'SELECT COUNT(*) c FROM '.$table;
        $where = '';

        if ($this->criteriaSql) {
            $where = ' WHERE '.$this->criteriaSql;
        } elseif ($this->criteria) {
            $where = ' WHERE document_criteria("'.$this->criteria.'", document)';
        }

        if ($this->limit || $this->skip) {
            $limit = $this->limit ? " LIMIT {$this->limit}" : ' LIMIT -1';
            $offset = $this->skip ? " OFFSET {$this->skip}" : '';
            $sql = "SELECT COUNT(*) c FROM (SELECT 1 FROM {$table}{$where}{$limit}{$offset})";
        } else {
            $sql .= $where;
        }

        try {
            $stmt = $this->collection->database->connection->query($sql);
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;

            return $row ? (int) $row['c'] : 0;
        } catch (\PDOException $e) {
            // If the underlying table doesn't exist (dropped), treat as empty
            return 0;
        }
    }

    public function each($callable): self
    {
        $this->rewind();
        while ($this->valid()) {
            $callable($this->current());
            $this->next();
        }

        return $this;
    }

    /* ================= QUERY ================= */

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function skip(int $skip): self
    {
        $this->skip = $skip;

        return $this;
    }

    public function sort($sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /* ================= POPULATE API ================= */

    public function populate(string $path, Collection $collection, array $options = []): self
    {
        $this->populate[] = compact('path', 'collection', 'options');

        return $this;
    }

    public function populateMany(array $defs): self
    {
        foreach ($defs as $path => $def) {
            if ($def instanceof Collection) {
                $this->populate($path, $def);
            } else {
                $this->populate($path, $def[0], $def[1] ?? []);
            }
        }

        return $this;
    }

    public function with(string|array $path, ?Collection $collection = null, array $options = []): self
    {
        return is_array($path)
            ? $this->populateMany($path)
            : $this->populate($path, $collection, $options);
    }

    /* ================= OUTPUT ================= */

    public function toArray(): array
    {
        $data = [];
        $this->rewind();
        while ($this->valid()) {
            $data[] = $this->current();
            $this->next();
        }

        foreach ($this->populate as $rule) {
            $data = $this->applyPopulate($data, $rule);
        }

        // Apply projection after populate so populate can rely on original fields
        if ($this->projection) {
            foreach ($data as &$doc) {
                if (is_array($doc)) {
                    $doc = $this->applyProjection($doc);
                }
            }
            unset($doc);
        }

        return $data;
    }

    /* ================= ITERATOR ================= */

    public function rewind()
    {
        $this->position = 0;

        $table = $this->collection->database->quoteIdentifier($this->collection->name);

        $sql = ['SELECT document FROM '.$table];

        if ($this->criteriaSql) {
            $sql[] = 'WHERE '.$this->criteriaSql;
        } elseif ($this->criteria) {
            $sql[] = 'WHERE document_criteria("'.$this->criteria.'", document)';
        }

        if ($this->sort) {
            $orders = [];
            foreach ($this->sort as $f => $d) {
                $orders[] = 'document_key("'.$f.'", document) '.($d == -1 ? 'DESC' : 'ASC');
            }
            $sql[] = 'ORDER BY '.implode(',', $orders);
        }

        if ($this->limit) {
            $sql[] = 'LIMIT '.$this->limit;
            if ($this->skip) {
                $sql[] = 'OFFSET '.$this->skip;
            }
        } elseif ($this->skip) {
            $sql[] = 'LIMIT -1 OFFSET '.$this->skip;
        }

        $this->stmt = $this->collection->database->connection->query(implode(' ', $sql));
        $row = $this->stmt ? $this->stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $this->currentRow = $row ? $this->collection->decodeStored($row['document']) : null;
        $this->lastKey = $this->currentRow ? 0 : -1;
    }

    public function current()
    {
        if ($this->currentRow === null && $this->position === 0) {
            $this->rewind();
        }

        return $this->currentRow;
    }

    public function key()
    {
        return min($this->position, $this->lastKey);
    }

    public function next()
    {
        ++$this->position;
        $row = $this->stmt ? $this->stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $this->currentRow = $row ? $this->collection->decodeStored($row['document']) : null;
        if ($this->currentRow !== null) {
            $this->lastKey = $this->position;
        }
    }

    public function valid()
    {
        return $this->currentRow !== null;
    }

    public function getIterator(): \Traversable
    {
        $this->rewind();
        while ($this->valid()) {
            yield $this->current();
            $this->next();
        }
    }

    /* ================= POPULATE CORE ================= */

    protected function applyPopulate(array $data, array $rule): array
    {
        $segments = explode('.', $rule['path']);
        $as = $rule['options']['as'] ?? preg_replace('/_id$/', '', end($segments));
        $collection = $rule['collection'];

        $ids = [];
        foreach ($data as $doc) {
            $this->collectIds($doc, $segments, $ids);
        }

        $ids = array_unique($ids);
        if (!$ids) {
            return $data;
        }

        $related = $collection->find(['_id' => ['$in' => $ids]])->toArray();
        $map = [];
        foreach ($related as $r) {
            $map[$r['_id']] = $r;
        }

        foreach ($data as &$doc) {
            $this->inject($doc, $segments, $map, $as);
        }

        return $data;
    }

    protected function collectIds($node, $path, &$ids, $i = 0)
    {
        if (!is_array($node) || !isset($path[$i]) || !isset($node[$path[$i]])) {
            return;
        }
        if ($i === count($path) - 1) {
            $ids[] = $node[$path[$i]];

            return;
        }
        foreach ($node[$path[$i]] as $child) {
            $this->collectIds($child, $path, $ids, $i + 1);
        }
    }

    protected function inject(&$node, $path, $map, $as, $i = 0)
    {
        if (!isset($path[$i]) || !isset($node[$path[$i]])) {
            return;
        }

        if ($i === count($path) - 1) {
            $node[$as] = $map[$node[$path[$i]]] ?? null;

            return;
        }

        foreach ($node[$path[$i]] as &$child) {
            if (is_array($child)) {
                $this->inject($child, $path, $map, $as, $i + 1);
            }
        }
    }

    protected function applyProjection(array $doc): array
    {
        $proj = $this->projection;

        // Determine mode: include (any value == 1) or exclude (all values == 0)
        $inclusive = false;
        foreach ($proj as $k => $v) {
            if ($v) {
                $inclusive = true;
                break;
            }
        }

        // Always include _id when doing inclusion
        if ($inclusive) {
            $result = [];
            foreach ($proj as $k => $v) {
                if ($v && array_key_exists($k, $doc)) {
                    $result[$k] = $doc[$k];
                }
            }
            if (array_key_exists('_id', $doc)) {
                $result['_id'] = $doc['_id'];
            }

            return $result;
        }

        // Exclusion mode: remove keys with value == 0
        foreach ($proj as $k => $v) {
            if ($v === 0 && array_key_exists($k, $doc)) {
                unset($doc[$k]);
            }
        }

        return $doc;
    }
}
