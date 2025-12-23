<?php

namespace PocketDB;

/**
 * Cursor for iterating over query results.
 */
class Cursor implements \IteratorAggregate
{
    // Iterator state
    protected int $position = 0;
    protected ?\PDOStatement $stmt = null;
    protected ?array $currentRow = null;
    protected ?string $criteriaSql = null;
    protected int $lastKey = -1;

    // Core properties
    public Collection $collection;
    public $criteria;

    // Query modifiers
    protected ?array $projection = null;
    protected ?int $limit = null;
    protected ?int $skip = null;
    protected ?array $sort = null;

    // Populate configuration
    protected array $populate = [];

    /**
     * Constructor.
     */
    public function __construct(Collection $collection, $criteria, $projection = null, ?string $criteriaSql = null)
    {
        $this->collection = $collection;
        $this->criteria = $criteria;
        $this->projection = $projection;
        $this->criteriaSql = $criteriaSql;
    }

    /* ================= LEGACY METHODS ================= */

    /**
     * Count documents matching the query.
     */
    public function count(): int
    {
        try {
            return $this->executeCountQuery();
        } catch (\PDOException $e) {
            // If the underlying table doesn't exist (dropped), treat as empty
            return 0;
        }
    }

    /**
     * Execute count query with proper handling of limit/skip.
     */
    private function executeCountQuery(): int
    {
        $table = $this->collection->database->quoteIdentifier($this->collection->name);
        $sql = $this->buildCountSql($table);

        $stmt = $this->collection->database->connection->query($sql);
        $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;

        return $row ? (int) $row['c'] : 0;
    }

    /**
     * Build SQL for count query.
     */
    private function buildCountSql(string $table): string
    {
        $sql = 'SELECT COUNT(*) c FROM '.$table;
        $where = $this->buildWhereClause();

        if ($where) {
            $sql .= ' WHERE '.$where;
        }

        if ($this->hasPagination()) {
            $limit = $this->limit ? " LIMIT {$this->limit}" : ' LIMIT -1';
            $offset = $this->skip ? " OFFSET {$this->skip}" : '';
            $sql = "SELECT COUNT(*) c FROM (SELECT 1 FROM {$table}{$where}{$limit}{$offset})";
        }

        return $sql;
    }

    /**
     * Build WHERE clause for criteria.
     */
    private function buildWhereClause(): string
    {
        if ($this->criteriaSql) {
            return $this->criteriaSql;
        }

        if ($this->criteria) {
            return 'document_criteria("'.$this->criteria.'", document)';
        }

        return '';
    }

    /**
     * Check if pagination is applied.
     */
    private function hasPagination(): bool
    {
        return $this->limit || $this->skip;
    }

    /**
     * Iterate over each document and apply callback.
     */
    public function each($callable): self
    {
        $this->rewind();
        while ($this->valid()) {
            $callable($this->current());
            $this->next();
        }

        return $this;
    }

    /* ================= QUERY MODIFIERS ================= */

    /**
     * Set maximum number of documents to return.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set number of documents to skip.
     */
    public function skip(int $skip): self
    {
        $this->skip = $skip;

        return $this;
    }

    /**
     * Set sort order for results.
     */
    public function sort($sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /* ================= POPULATE API ================= */

    /**
     * Add population rule for a single relationship.
     */
    public function populate(string $path, Collection $collection, array $options = []): self
    {
        $this->populate[] = compact('path', 'collection', 'options');

        return $this;
    }

    /**
     * Add multiple population rules.
     */
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

    /**
     * Alternative populate API with fluent interface.
     */
    public function with(string|array $path, ?Collection $collection = null, array $options = []): self
    {
        return is_array($path)
            ? $this->populateMany($path)
            : $this->populate($path, $collection, $options);
    }

    /* ================= OUTPUT METHODS ================= */

    /**
     * Convert cursor to array of documents.
     */
    public function toArray(): array
    {
        $data = $this->fetchAllDocuments();

        // Apply population rules
        $data = $this->applyPopulationRules($data);

        // Apply projection
        return $this->applyProjection($data);
    }

    /**
     * Fetch all documents from the cursor.
     */
    private function fetchAllDocuments(): array
    {
        $data = [];
        $this->rewind();
        while ($this->valid()) {
            $data[] = $this->current();
            $this->next();
        }

        return $data;
    }

    /**
     * Apply population rules to documents.
     */
    private function applyPopulationRules(array $data): array
    {
        foreach ($this->populate as $rule) {
            $data = $this->applyPopulate($data, $rule);
        }

        return $data;
    }

    /* ================= ITERATOR INTERFACE ================= */

    /**
     * Reset cursor to beginning.
     */
    public function rewind(): void
    {
        $this->position = 0;
        $this->executeQuery();
    }

    /**
     * Execute the query and prepare first row.
     */
    private function executeQuery(): void
    {
        $table = $this->collection->database->quoteIdentifier($this->collection->name);
        $sql = $this->buildQuerySql($table);

        $this->stmt = $this->collection->database->connection->query($sql);
        $this->loadCurrentRow();
    }

    /**
     * Build SQL query for cursor.
     */
    private function buildQuerySql(string $table): string
    {
        $sql = ['SELECT document FROM '.$table];

        // Add WHERE clause
        $where = $this->buildWhereClause();
        if ($where) {
            $sql[] = 'WHERE '.$where;
        }

        // Add ORDER BY clause
        $orderBy = $this->buildOrderByClause();
        if ($orderBy) {
            $sql[] = $orderBy;
        }

        // Add LIMIT and OFFSET
        $this->addPaginationClauses($sql);

        return implode(' ', $sql);
    }

    /**
     * Build ORDER BY clause for sort criteria.
     */
    private function buildOrderByClause(): string
    {
        if (!$this->sort) {
            return '';
        }

        $orders = [];
        foreach ($this->sort as $field => $direction) {
            $direction = $direction == -1 ? 'DESC' : 'ASC';
            $orders[] = 'document_key("'.$field.'", document) '.$direction;
        }

        return 'ORDER BY '.implode(',', $orders);
    }

    /**
     * Add LIMIT and OFFSET clauses to SQL.
     */
    private function addPaginationClauses(array &$sql): void
    {
        if ($this->limit) {
            $sql[] = 'LIMIT '.$this->limit;
            if ($this->skip) {
                $sql[] = 'OFFSET '.$this->skip;
            }
        } elseif ($this->skip) {
            $sql[] = 'LIMIT -1 OFFSET '.$this->skip;
        }
    }

    /**
     * Load current row from statement.
     */
    private function loadCurrentRow(): void
    {
        $row = $this->stmt ? $this->stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $this->currentRow = $row ? $this->collection->decodeStored($row['document']) : null;
        $this->lastKey = $this->currentRow !== null ? $this->position : -1;
    }

    /**
     * Get current document.
     */
    public function current(): ?array
    {
        if ($this->currentRow === null && $this->position === 0) {
            $this->rewind();
        }

        return $this->currentRow;
    }

    /**
     * Get current position key.
     */
    public function key(): int
    {
        if ($this->position === 0) {
            return 0;
        }

        return $this->lastKey >= 0 ? $this->lastKey : $this->position - 1;
    }

    /**
     * Move to next document.
     */
    public function next(): void
    {
        ++$this->position;
        $this->loadCurrentRow();
    }

    /**
     * Check if current position is valid.
     */
    public function valid(): bool
    {
        return $this->currentRow !== null;
    }

    /**
     * Get iterator for foreach loops.
     */
    public function getIterator(): \Traversable
    {
        $this->rewind();
        while ($this->valid()) {
            yield $this->current();
            $this->next();
        }
    }

    /* ================= POPULATION CORE ================= */

    /**
     * Apply a single population rule to data.
     */
    protected function applyPopulate(array $data, array $rule): array
    {
        $segments = explode('.', $rule['path']);
        $as = $rule['options']['as'] ?? preg_replace('/_id$/', '', end($segments));
        $collection = $rule['collection'];

        $ids = $this->collectIdsFromData($data, $segments);

        if (empty($ids)) {
            return $data;
        }

        $relatedDocuments = $this->fetchRelatedDocuments($collection, $ids);
        $documentMap = $this->buildDocumentMap($relatedDocuments);

        return $this->injectDocuments($data, $segments, $documentMap, $as);
    }

    /**
     * Collect IDs from data based on path segments.
     */
    private function collectIdsFromData(array $data, array $segments): array
    {
        $ids = [];
        foreach ($data as $doc) {
            $this->collectIds($doc, $segments, $ids);
        }

        return array_unique($ids);
    }

    /**
     * Fetch related documents from collection.
     */
    private function fetchRelatedDocuments(Collection $collection, array $ids): array
    {
        return $collection->find(['_id' => ['$in' => $ids]])->toArray();
    }

    /**
     * Build map of related documents by ID.
     */
    private function buildDocumentMap(array $relatedDocuments): array
    {
        $map = [];
        foreach ($relatedDocuments as $doc) {
            $map[$doc['_id']] = $doc;
        }

        return $map;
    }

    /**
     * Inject related documents into main data.
     */
    private function injectDocuments(array $data, array $segments, array $map, string $as): array
    {
        foreach ($data as &$doc) {
            $this->inject($doc, $segments, $map, $as);
        }

        return $data;
    }

    /**
     * Collect IDs from a node recursively.
     */
    protected function collectIds($node, array $path, array &$ids, int $index = 0): void
    {
        if (!$this->isValidPathNode($node, $path, $index)) {
            return;
        }

        if ($this->isFinalPathSegment($path, $index)) {
            $ids[] = $node[$path[$index]];

            return;
        }

        foreach ($node[$path[$index]] as $child) {
            $this->collectIds($child, $path, $ids, $index + 1);
        }
    }

    /**
     * Check if path node is valid.
     */
    private function isValidPathNode($node, array $path, int $index): bool
    {
        return is_array($node)
               && isset($path[$index])
               && isset($node[$path[$index]]);
    }

    /**
     * Check if current index is the final path segment.
     */
    private function isFinalPathSegment(array $path, int $index): bool
    {
        return $index === count($path) - 1;
    }

    /**
     * Inject related document into node.
     */
    protected function inject(&$node, array $path, array $map, string $as, int $index = 0): void
    {
        if (!$this->isValidPathNode($node, $path, $index)) {
            return;
        }

        if ($this->isFinalPathSegment($path, $index)) {
            $node[$as] = $map[$node[$path[$index]]] ?? null;

            return;
        }

        foreach ($node[$path[$index]] as &$child) {
            if (is_array($child)) {
                $this->inject($child, $path, $map, $as, $index + 1);
            }
        }
    }

    /**
     * Apply projection to documents.
     */
    protected function applyProjection(array $data): array
    {
        if (!$this->projection) {
            return $data;
        }

        $isInclusive = $this->isInclusiveProjection();

        foreach ($data as &$doc) {
            if (is_array($doc)) {
                $doc = $this->applyProjectionToDocument($doc, $isInclusive);
            }
        }

        return $data;
    }

    /**
     * Check if projection is inclusive (include fields).
     */
    private function isInclusiveProjection(): bool
    {
        foreach ($this->projection as $value) {
            if ($value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply projection to a single document.
     */
    private function applyProjectionToDocument(array $doc, bool $isInclusive): array
    {
        if ($isInclusive) {
            return $this->applyInclusiveProjection($doc);
        } else {
            return $this->applyExclusiveProjection($doc);
        }
    }

    /**
     * Apply inclusive projection (include specified fields).
     */
    private function applyInclusiveProjection(array $doc): array
    {
        $result = [];

        foreach ($this->projection as $field => $include) {
            if ($include && array_key_exists($field, $doc)) {
                $result[$field] = $doc[$field];
            }
        }

        // Always include _id when doing inclusion
        if (array_key_exists('_id', $doc)) {
            $result['_id'] = $doc['_id'];
        }

        return $result;
    }

    /**
     * Apply exclusive projection (exclude specified fields).
     */
    private function applyExclusiveProjection(array $doc): array
    {
        foreach ($this->projection as $field => $exclude) {
            if ($exclude === 0 && array_key_exists($field, $doc)) {
                unset($doc[$field]);
            }
        }

        return $doc;
    }
}
