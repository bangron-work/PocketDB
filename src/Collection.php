<?php

namespace PocketDB;

/**
 * Collection object.
 */
class Collection
{
    /**
     * ID Generation Mode Constants.
     */
    public const ID_MODE_AUTO = 'auto';          // Generate UUID v4 automatically
    public const ID_MODE_MANUAL = 'manual';      // Use provided _id only
    public const ID_MODE_PREFIX = 'prefix';      // Generate with prefix

    public Database $database;

    public string $name;

    /**
     * @var string ID generation mode
     */
    protected $idMode = self::ID_MODE_AUTO;

    /**
     * @var string|null ID prefix (for prefix mode)
     */
    protected ?string $idPrefix = null;

    /**
     * @var int Auto-increment counter (for prefix mode)
     */
    protected int $idCounter = 0;

    /**
     * Hooks storage: event name => list of callables.
     *
     * @var array<string,array<int,callable>>
     */
    protected array $hooks = [];

    /**
     * (Removed) In-memory cache was intentionally removed to avoid loading
     * entire collection into PHP memory. SQLite should handle paging/caching.
     */

    /**
     * Constructor.
     *
     * @param object $database
     */
    public function __construct(string $name, Database $database)
    {
        $this->name = $name;
        $this->database = $database;
    }

    /**
     * Set ID generation mode to AUTO (UUID v4).
     */
    public function setIdModeAuto(): self
    {
        $this->idMode = self::ID_MODE_AUTO;
        $this->idPrefix = null;

        return $this;
    }

    /**
     * Set ID generation mode to MANUAL (use provided _id).
     */
    public function setIdModeManual(): self
    {
        $this->idMode = self::ID_MODE_MANUAL;
        $this->idPrefix = null;

        return $this;
    }

    /**
     * Set ID generation mode to PREFIX (auto with prefix).
     *
     * @param string $prefix Prefix for generated IDs (e.g., 'USR', 'PRD', 'ORD')
     */
    public function setIdModePrefix(string $prefix): self
    {
        $this->idMode = self::ID_MODE_PREFIX;
        $this->idPrefix = $prefix;
        $this->_initializeCounter();

        return $this;
    }

    /**
     * Get current ID mode.
     */
    public function getIdMode(): string
    {
        return $this->idMode;
    }

    /**
     * Initialize counter for prefix mode.
     */
    private function _initializeCounter(): void
    {
        // Get highest number from existing IDs with this prefix
        if ($this->idPrefix) {
            $prefixPattern = $this->idPrefix.'-';
            $sql = "SELECT document FROM {$this->name} ORDER BY id DESC LIMIT 1";

            try {
                $stmt = $this->database->connection->query($sql);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result) {
                    $doc = \json_decode($result['document'], true);
                    if (isset($doc['_id']) && strpos($doc['_id'], $prefixPattern) === 0) {
                        $parts = explode('-', $doc['_id']);
                        $lastNum = (int) end($parts);
                        $this->idCounter = $lastNum;
                    }
                }
            } catch (\Exception $e) {
                // Table might not exist yet, initialize to 0
                $this->idCounter = 0;
            }
        }
    }

    /**
     * Generate ID based on current mode.
     */
    protected function _generateId(): ?string
    {
        switch ($this->idMode) {
            case self::ID_MODE_PREFIX:
                $this->idCounter++;

                return $this->idPrefix.'-'.str_pad($this->idCounter, 6, '0', STR_PAD_LEFT);

            case self::ID_MODE_MANUAL:
                return null; // Will be checked in _insert

            case self::ID_MODE_AUTO:
            default:
                return createMongoDbLikeId();
        }
    }

    /**
     * Drop collection.
     */
    public function drop()
    {
        $this->database->dropCollection($this->name);
    }

    /**
     * Insert many documents.
     *
     * @return count of inserted documents for arrays
     */
    public function insertMany(array $documents): int
    {
        return $this->insert($documents);
    }

    /**
     * Insert document.
     *
     * @return mixed last_insert_id for single document or
     *               count count of inserted documents for arrays
     */
    public function insert(array $document = [])
    {
        if (isset($document[0])) {
            $this->database->connection->beginTransaction();

            try {
                foreach ($document as $doc) {
                    if (!\is_array($doc)) {
                        continue;
                    }

                    $res = $this->_insert($doc);

                    if (!$res) {
                        // Failure - roll back and return
                        $this->database->connection->rollBack();

                        return $res;
                    }
                }

                $this->database->connection->commit();

                return \count($document);
            } catch (\Throwable $e) {
                if ($this->database->connection && $this->database->connection->inTransaction()) {
                    $this->database->connection->rollBack();
                }
                throw $e;
            }
        } else {
            return $this->_insert($document);
        }
    }

    /**
     * Insert document.
     */
    protected function _insert(array $document): mixed
    {
        $table = $this->name;
        $doc = $document;

        // Allow beforeInsert hooks to modify the document. Hooks should return
        // the modified document (or null to keep original).
        if (!empty($this->hooks['beforeInsert'])) {
            foreach ($this->hooks['beforeInsert'] as $h) {
                $ret = $h($doc);

                // Jika hook mengembalikan false secara eksplisit, batalkan proses!
                if ($ret === false) {
                    return false;
                }

                // Jika hook mengembalikan array, gunakan array tersebut (update data)
                if (is_array($ret)) {
                    $doc = $ret;
                }
            }
        }

        // Handle _id based on mode
        if (!isset($doc['_id'])) {
            $generated_id = $this->_generateId();

            if ($this->idMode === self::ID_MODE_MANUAL && $generated_id === null) {
                return false;
            }

            $doc['_id'] = $generated_id;
        }

        $encoded = \json_encode($doc, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: '.json_last_error_msg());
        }

        $data = ['document' => $encoded];

        $fields = [];
        $values = [];

        foreach ($data as $col => $value) {
            $fields[] = "`{$col}`";
            $values[] = (\is_null($value) ? 'NULL' : $this->database->connection->quote($value));
        }

        $fields = \implode(',', $fields);
        $values = \implode(',', $values);

        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
        $res = $this->database->connection->exec($sql);

        if ($res) {
            // trigger afterInsert hooks
            if (!empty($this->hooks['afterInsert'])) {
                foreach ($this->hooks['afterInsert'] as $h) {
                    try {
                        $h($doc, $doc['_id']);
                    } catch (\Throwable $e) { /* ignore hook errors */
                    }
                }
            }
            // flush collection cache on write
            $this->flushCache();

            return $doc['_id'];
        } else {
            trigger_error('SQL Error: '.\implode(', ', $this->database->connection->errorInfo()).":\n".$sql);

            return false;
        }
    }

    /**
     * Save document.
     */
    public function save(array $document, bool $create = false): mixed
    {
        // Use a single upsert-style SQL statement to avoid race conditions
        if (isset($document['_id'])) {
            $json = json_encode($document, JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('JSON encode error: '.json_last_error_msg());
            }

            // OPTIMIZATION: use json_extract on the _id field so SQLite can use indexes
            // and avoid invoking the PHP callback `document_criteria` for every row.
            $idVal = $document['_id'];
            if (is_int($idVal) || is_float($idVal) || (is_string($idVal) && is_numeric($idVal))) {
                $quotedId = $idVal;
            } else {
                $quotedId = $this->database->connection->quote((string) $idVal);
            }

            $subQuery = "SELECT id FROM {$this->name} WHERE json_extract(document, '$._id') = {$quotedId} LIMIT 1";

            $sql = "INSERT INTO {$this->name} (id, document) VALUES (({$subQuery}), ".$this->database->connection->quote($json).') '
                .'ON CONFLICT(id) DO UPDATE SET document='.$this->database->connection->quote($json);

            $res = $this->database->connection->exec($sql);

            if ($res === false) {
                trigger_error('SQL Error: '.implode(', ', $this->database->connection->errorInfo())."\n".$sql);

                return false;
            }

            return $document['_id'];
        }

        return $this->insert($document);
    }

    /**
     * Update documents.
     */
    public function update($criteria, array $data, bool $merge = true): int
    {
        // allow beforeUpdate hooks to modify criteria/data
        if (!empty($this->hooks['beforeUpdate'])) {
            foreach ($this->hooks['beforeUpdate'] as $h) {
                $ret = $h($criteria, $data);
                if (is_array($ret)) {
                    if (isset($ret['criteria'])) {
                        $criteria = $ret['criteria'];
                    }
                    if (isset($ret['data'])) {
                        $data = $ret['data'];
                    }
                    // also support numeric-indexed [criteria,data]
                    if (isset($ret[0])) {
                        $criteria = $ret[0];
                    }
                    if (isset($ret[1])) {
                        $data = $ret[1];
                    }
                }
            }
        }

        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE '.$where;
        } else {
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';
        }

        $stmt = $this->database->connection->query($sql);
        $result = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        foreach ($result as $doc) {
            $_doc = \json_decode($doc['document'], true);

            // Handle null case for $_doc
            if ($_doc === null) {
                $_doc = [];
            }

            if ($merge) {
                $document = \array_merge($_doc, $data);
            } else {
                $document = $data;

                // Preserve the _id field if it exists in the original document
                if (isset($_doc['_id'])) {
                    $document['_id'] = $_doc['_id'];
                }
            }

            $encoded = json_encode($document, JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // skip this document update if encoding fails
                continue;
            }

            $sql = 'UPDATE '.$this->name.' SET document='.$this->database->connection->quote($encoded).' WHERE id='.$doc['id'];

            $this->database->connection->exec($sql);

            // trigger afterUpdate hooks with original and updated document
            if (!empty($this->hooks['afterUpdate'])) {
                foreach ($this->hooks['afterUpdate'] as $h) {
                    try {
                        $h($_doc, $document);
                    } catch (\Throwable $e) {
                        // ignore hook errors
                    }
                }
            }
        }

        $updated = count($result);
        if ($updated > 0) {
            // no in-memory cache to flush
        }

        return $updated;
    }

    /**
     * Remove documents.
     *
     * @return mixed
     */
    public function remove($criteria): int
    {
        // Fetch matching rows so we can run hooks per-document
        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE '.$where;
        } else {
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';
        }

        $stmt = $this->database->connection->query($sql);
        $result = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $deleted = 0;

        foreach ($result as $row) {
            $doc = \json_decode($row['document'], true) ?: [];

            // Before remove hooks can veto by returning false
            $skip = false;
            if (!empty($this->hooks['beforeRemove'])) {
                foreach ($this->hooks['beforeRemove'] as $h) {
                    try {
                        $ret = $h($doc);
                        if ($ret === false) {
                            $skip = true;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // ignore hook errors
                    }
                }
            }

            if ($skip) {
                continue;
            }

            // perform deletion by id
            $delSql = 'DELETE FROM '.$this->name.' WHERE id='.$row['id'];
            $this->database->connection->exec($delSql);
            ++$deleted;

            // afterRemove hooks
            if (!empty($this->hooks['afterRemove'])) {
                foreach ($this->hooks['afterRemove'] as $h) {
                    try {
                        $h($doc);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        if ($deleted > 0) {
            // no in-memory cache to flush
        }

        return $deleted;
    }

    /**
     * Count documents in collections.
     */
    public function count($criteria = null): int
    {
        return $this->find($criteria)->count();
    }

    /**
     * Find documents.
     *
     * @return object Cursor
     */
    public function find($criteria = null, $projection = null): Cursor
    {
        // If criteria can be translated to native JSON SQL (simple equality or supported operators), do so
        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            // still register criteria function id for compatibility
            $critId = $this->database->registerCriteriaFunction($criteria);

            return new Cursor($this, $critId, $projection, $where);
        }

        return new Cursor($this, $this->database->registerCriteriaFunction($criteria), $projection);
    }

    /**
     * Detect simple equality criteria (no $ operators).
     */
    private function _isSimpleEqualityCriteria($criteria): bool
    {
        if (!is_array($criteria)) {
            return false;
        }
        foreach ($criteria as $k => $v) {
            if (strpos($k, '$') === 0) {
                return false;
            }
            if (is_array($v)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if a criteria array can be translated to a JSON-based SQL WHERE clause.
     * Supports simple equality and a limited set of operators: $gt, $gte, $lt, $lte, $in, $nin, $exists.
     */
    private function _canTranslateToJsonWhere($criteria): bool
    {
        if (!is_array($criteria)) {
            return false;
        }

        $allowedOps = ['$gt', '$gte', '$lt', '$lte', '$in', '$nin', '$exists'];

        foreach ($criteria as $k => $v) {
            if (strpos($k, '$') === 0) {
                return false;
            } // top-level logical operators not supported here

            if (is_array($v)) {
                // operator-style value expected
                foreach ($v as $op => $val) {
                    if (strpos($op, '$') !== 0) {
                        return false;
                    }
                    if (!in_array($op, $allowedOps, true)) {
                        return false;
                    }
                    if (in_array($op, ['$in', '$nin'], true) && !is_array($val)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Build SQL WHERE clause using json_extract for simple equality criteria.
     */
    private function _buildJsonWhere(array $criteria): string
    {
        $parts = [];
        foreach ($criteria as $key => $value) {
            $path = '$.'.str_replace("'", "\\'", $key);
            $expr = "json_extract(document, '".$path."')";

            if (is_array($value)) {
                // support basic operators: $gt, $gte, $lt, $lte, $in, $nin, $exists
                foreach ($value as $op => $v) {
                    switch ($op) {
                        case '$gt':
                            $val = is_numeric($v) ? $v : $this->database->connection->quote((string) $v);
                            $parts[] = "{$expr} > {$val}";
                            break;
                        case '$gte':
                            $val = is_numeric($v) ? $v : $this->database->connection->quote((string) $v);
                            $parts[] = "{$expr} >= {$val}";
                            break;
                        case '$lt':
                            $val = is_numeric($v) ? $v : $this->database->connection->quote((string) $v);
                            $parts[] = "{$expr} < {$val}";
                            break;
                        case '$lte':
                            $val = is_numeric($v) ? $v : $this->database->connection->quote((string) $v);
                            $parts[] = "{$expr} <= {$val}";
                            break;
                        case '$in':
                            if (!is_array($v) || empty($v)) {
                                $parts[] = '0';
                                break;
                            }
                            $vals = [];
                            foreach ($v as $item) {
                                $vals[] = is_numeric($item) ? $item : $this->database->connection->quote((string) $item);
                            }
                            $parts[] = "{$expr} IN (".implode(',', $vals).')';
                            break;
                        case '$nin':
                            if (!is_array($v) || empty($v)) {
                                // nothing to exclude
                                break;
                            }
                            $vals = [];
                            foreach ($v as $item) {
                                $vals[] = is_numeric($item) ? $item : $this->database->connection->quote((string) $item);
                            }
                            $parts[] = "{$expr} NOT IN (".implode(',', $vals).')';
                            break;
                        case '$exists':
                            $parts[] = $v ? "{$expr} IS NOT NULL" : "{$expr} IS NULL";
                            break;
                        default:
                            // unsupported operator - fallback to strict equality check on string representation
                            $val = $this->database->connection->quote((string) $v);
                            $parts[] = "{$expr} = {$val}";
                    }
                }
            } else {
                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                    $val = $value;
                } elseif (is_bool($value)) {
                    // Use numeric boolean representation for comparison
                    $val = $value ? '1' : '0';
                } else {
                    $val = $this->database->connection->quote((string) $value);
                }

                $parts[] = "{$expr} = {$val}";
            }
        }

        return implode(' AND ', $parts);
    }

    /**
     * Find one document.
     */
    public function findOne($criteria = null, $projection = null): ?array
    {
        $items = $this->find($criteria, $projection)->limit(1)->toArray();

        return isset($items[0]) ? $items[0] : null;
    }

    /**
     * Register an event hook for this collection.
     * Events: beforeInsert, afterInsert, beforeUpdate.
     */
    public function on(string $event, callable $fn): void
    {
        if (!isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }
        $this->hooks[$event][] = $fn;
    }

    /**
     * Remove hooks for an event. If $fn is null removes all listeners.
     */
    public function off(string $event, ?callable $fn = null): void
    {
        if (!isset($this->hooks[$event])) {
            return;
        }
        if ($fn === null) {
            unset($this->hooks[$event]);

            return;
        }
        foreach ($this->hooks[$event] as $k => $h) {
            if ($h === $fn) {
                unset($this->hooks[$event][$k]);
            }
        }
        $this->hooks[$event] = array_values($this->hooks[$event]);
    }

    /**
     * Populate references in given documents.
     * $foreign may be "collection" or "db.collection".
     * Returns populated documents (array). If single document passed, returns single document.
     */
    public function populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed
    {
        $single = false;
        if (array_keys($documents) !== range(0, count($documents) - 1)) {
            // associative or single document
            $single = true;
            $documents = [$documents];
        }

        // collect keys to fetch
        $keys = [];
        foreach ($documents as $d) {
            if (isset($d[$localField])) {
                if (is_array($d[$localField])) {
                    foreach ($d[$localField] as $v) {
                        $keys[] = $v;
                    }
                } else {
                    $keys[] = $d[$localField];
                }
            }
        }
        $keys = array_values(array_unique($keys));

        if (empty($keys)) {
            return $single ? $documents[0] : $documents;
        }

        // resolve client and target collection
        $client = $this->database->client ?? null;
        if (!$client) {
            throw new \RuntimeException('Client not available for populate');
        }

        $dbName = null;
        $collName = $foreign;
        if (strpos($foreign, '.') !== false) {
            list($dbName, $collName) = explode('.', $foreign, 2);
        }

        $targetDb = $dbName ? $client->selectDB($dbName) : $this->database;
        $targetColl = $targetDb->selectCollection($collName);

        $foreignDocs = $targetColl->find([$foreignField => ['$in' => $keys]])->toArray();

        $map = [];
        foreach ($foreignDocs as $fd) {
            $map[$fd[$foreignField]] = $fd;
        }

        $out = [];
        foreach ($documents as $d) {
            $copy = $d;
            $value = $d[$localField] ?? null;
            if ($value === null) {
                $copy[$as ?? $collName] = null;
            } elseif (is_array($value)) {
                $arr = [];
                foreach ($value as $v) {
                    if (isset($map[$v])) {
                        $arr[] = $map[$v];
                    }
                }
                $copy[$as ?? $collName] = $arr;
            } else {
                $copy[$as ?? $collName] = $map[$value] ?? null;
            }
            $out[] = $copy;
        }

        return $single ? $out[0] : $out;
    }

    /**
     * Rename Collection.
     *
     * @param string $newname [description]
     */
    public function renameCollection($newname): bool
    {
        if (!in_array($newname, $this->database->getCollectionNames())) {
            $this->database->connection->exec('ALTER TABLE '.$this->name.' RENAME TO '.$newname);

            $this->name = $newname;

            return true;
        }

        return false;
    }

    /**
     * Create a JSON index for a field on this collection.
     */
    public function createIndex(string $field, ?string $indexName = null): void
    {
        $this->database->createJsonIndex($this->name, $field, $indexName);
    }

    /**
     * Backwards-compatible no-op for flushCache removed in optimization.
     */
    protected function flushCache(): void
    {
        // intentionally left blank — cache removed to avoid memory growth
    }

    // Note: in-memory collection caching removed to avoid unbounded memory growth.
}
