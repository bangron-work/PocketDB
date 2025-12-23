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

    /**
     * Hook Event Constants.
     */
    public const HOOK_BEFORE_INSERT = 'beforeInsert';
    public const HOOK_AFTER_INSERT = 'afterInsert';
    public const HOOK_BEFORE_UPDATE = 'beforeUpdate';
    public const HOOK_AFTER_UPDATE = 'afterUpdate';
    public const HOOK_BEFORE_REMOVE = 'beforeRemove';
    public const HOOK_AFTER_REMOVE = 'afterRemove';

    /**
     * JSON Query Operator Constants.
     */
    private const JSON_OP_GT = '$gt';
    private const JSON_OP_GTE = '$gte';
    private const JSON_OP_LT = '$lt';
    private const JSON_OP_LTE = '$lte';
    private const JSON_OP_IN = '$in';
    private const JSON_OP_NIN = '$nin';
    private const JSON_OP_EXISTS = '$exists';

    /**
     * Searchable Field Prefix.
     */
    private const SEARCHABLE_PREFIX = 'si_';

    public Database $database;

    public string $name;

    /**
     * Optional per-collection encryption key. If set, it takes precedence
     * over `Database->encryptionKey` for encoding/decoding stored documents.
     * This allows enabling encryption on a per-collection basis.
     */
    protected ?string $encryptionKey = null;

    /**
     * Searchable fields configuration. Map of fieldName => ['hash' => bool]
     * When set, the collection will maintain `si_{field}` TEXT columns
     * containing the plain or hashed value to enable searching on encrypted docs.
     *
     * @var array<string,array{hash:bool}>
     */
    protected array $searchableFields = [];

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
     * Set per-collection encryption key (overrides Database->encryptionKey when set).
     */
    public function setEncryptionKey(?string $key): self
    {
        $this->encryptionKey = $key;

        return $this;
    }

    /**
     * Configure searchable fields. Each field will be stored into a dedicated
     * `si_{field}` TEXT column. If $hash is true the stored value will be
     * a hex SHA-256 of the string (useful for privacy-preserving search).
     */
    public function setSearchableFields(array $fields, bool $hash = false): self
    {
        $this->searchableFields = [];
        foreach ($fields as $f) {
            $this->searchableFields[(string) $f] = ['hash' => $hash];
        }

        $this->ensureSearchableColumnsExist();

        return $this;
    }

    /**
     * Remove a searchable field configuration. If $dropColumn is true the
     * method will attempt to remove the physical `si_{field}` column from
     * the SQLite table by rebuilding the table without that column.
     */
    public function removeSearchableField(string $field, bool $dropColumn = false): self
    {
        if (isset($this->searchableFields[$field])) {
            unset($this->searchableFields[$field]);
        }

        if ($dropColumn) {
            $col = self::SEARCHABLE_PREFIX.$field;
            // Check if column exists
            $stmt = $this->database->connection->query("PRAGMA table_info(`{$this->name}`)");
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            $existing = [];
            foreach ($cols as $c) {
                $existing[$c['name']] = $c;
            }

            if (isset($existing[$col])) {
                // SQLite has no DROP COLUMN; perform a safe table rebuild
                $colsToKeep = [];
                foreach ($cols as $c) {
                    if ($c['name'] === $col) {
                        continue;
                    }
                    $colsToKeep[] = $c['name'];
                }

                $colsList = implode(', ', array_map(function ($n) { return "`{$n}`"; }, $colsToKeep));

                $tmp = $this->name.'_tmp_'.uniqid();
                // Create temp table with only the kept columns
                $createCols = [];
                foreach ($cols as $c) {
                    if ($c['name'] === $col) {
                        continue;
                    }
                    $createCols[] = "`{$c['name']}` {$c['type']}".($c['notnull'] ? ' NOT NULL' : '');
                }

                $this->database->connection->beginTransaction();
                try {
                    $this->database->connection->exec("CREATE TABLE `{$tmp}` (".implode(',', $createCols).')');
                    $this->database->connection->exec("INSERT INTO `{$tmp}` ({$colsList}) SELECT {$colsList} FROM `{$this->name}`");
                    $this->database->connection->exec("DROP TABLE `{$this->name}`");
                    $this->database->connection->exec("ALTER TABLE `{$tmp}` RENAME TO `{$this->name}`");
                    $this->database->connection->commit();
                } catch (\Throwable $e) {
                    if ($this->database->connection->inTransaction()) {
                        $this->database->connection->rollBack();
                    }
                    throw $e;
                }
            }
        }

        return $this;
    }

    protected function ensureSearchableColumnsExist(): void
    {
        if (empty($this->searchableFields)) {
            return;
        }

        // Ensure table exists
        $this->database->createCollection($this->name);

        $stmt = $this->database->connection->query("PRAGMA table_info(`{$this->name}`)");
        $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $existing = [];
        foreach ($cols as $c) {
            $existing[$c['name']] = true;
        }

        foreach ($this->searchableFields as $field => $cfg) {
            $col = self::SEARCHABLE_PREFIX.$field;
            if (!isset($existing[$col])) {
                $this->database->connection->exec("ALTER TABLE `{$this->name}` ADD COLUMN `{$col}` TEXT NULL");
            }
        }
    }

    /**
     * Compute the map of searchable column => value for a given document.
     */
    protected function _computeSearchIndexValues(array $doc): array
    {
        $out = [];
        if (empty($this->searchableFields)) {
            return $out;
        }

        foreach ($this->searchableFields as $field => $cfg) {
            // support dot notation for nested fields
            $parts = explode('.', $field);
            $ref = $doc;
            foreach ($parts as $p) {
                if (!is_array($ref) || !array_key_exists($p, $ref)) {
                    $ref = null;
                    break;
                }
                $ref = $ref[$p];
            }

            if ($ref === null) {
                $val = null;
            } elseif (is_array($ref)) {
                // join arrays into comma separated string
                $val = implode(',', array_map('strval', $ref));
            } else {
                $val = (string) $ref;
            }

            if ($val !== null && $cfg['hash']) {
                $val = hash('sha256', $val);
            }

            $out[self::SEARCHABLE_PREFIX.$field] = $val;
        }

        return $out;
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
                    $doc = $this->decodeStored($result['document']);
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
        $doc = $document;

        // Apply before insert hooks
        $doc = $this->applyHooks('beforeInsert', $doc);
        if ($doc === false) {
            return false;
        }

        // Handle _id generation
        $doc = $this->ensureDocumentId($doc);
        if ($doc === false) {
            return false;
        }

        // Encode and prepare data for storage
        $data = $this->prepareDocumentForStorage($doc);

        // Execute insert
        $insertId = $this->executeInsert($data);

        if ($insertId) {
            // Trigger after insert hooks
            $this->applyHooks('afterInsert', $doc, $insertId);

            return $insertId;
        }

        return false;
    }

    /**
     * Apply hooks for a specific event.
     */
    protected function applyHooks(string $event, $data, $id = null): mixed
    {
        if (!empty($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $hook) {
                $result = $hook($data, $id);

                if ($result === false) {
                    return false;
                }

                if (is_array($result)) {
                    $data = $result;
                }
            }
        }

        return $data;
    }

    /**
     * Ensure document has proper _id based on current mode.
     */
    protected function ensureDocumentId(array $document): mixed
    {
        if (!isset($document['_id'])) {
            $generatedId = $this->_generateId();

            if ($this->idMode === self::ID_MODE_MANUAL && $generatedId === null) {
                return false;
            }

            $document['_id'] = $generatedId;
        }

        return $document;
    }

    /**
     * Prepare document data for storage (encoding + searchable fields).
     */
    protected function prepareDocumentForStorage(array $document): array
    {
        $encoded = $this->encodeStored($document);
        $data = ['document' => $encoded];

        // Add searchable index columns when configured
        $indexData = $this->_computeSearchIndexValues($document);
        foreach ($indexData as $col => $val) {
            $data[$col] = $val;
        }

        return $data;
    }

    /**
     * Execute the actual SQL insert statement.
     */
    protected function executeInsert(array $data): mixed
    {
        $table = $this->name;
        $fields = [];
        $values = [];

        foreach ($data as $col => $value) {
            $fields[] = "`{$col}`";
            $values[] = (\is_null($value) ? 'NULL' : $this->database->connection->quote($value));
        }

        $fields = \implode(',', $fields);
        $values = \implode(',', $values);

        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$values})";

        if ($this->database->connection->exec($sql)) {
            return $data['document'] ? json_decode($data['document'], true)['_id'] : null;
        }

        $this->logSqlError($sql);

        return false;
    }

    /**
     * Log SQL error for debugging.
     */
    protected function logSqlError(string $sql): void
    {
        trigger_error('SQL Error: '.\implode(', ', $this->database->connection->errorInfo()).":\n".$sql);
    }

    /**
     * Encode a document for storage. If `Database->encryptionKey` is set, the
     * document (except `_id`) will be encrypted with AES-256-CBC and stored as
     * an object: { _id, encrypted_data, iv }.
     *
     * Returns JSON string ready to be stored in `document` column.
     */
    protected function encodeStored(array $doc)
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;

        if (empty($key)) {
            return $this->encodeJson($doc);
        }

        return $this->encodeEncrypted($doc, $key);
    }

    /**
     * Encode document as JSON (no encryption).
     */
    private function encodeJson(array $doc): string
    {
        $json = \json_encode($doc, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: '.json_last_error_msg());
        }

        return $json;
    }

    /**
     * Encode document with AES-256-CBC encryption.
     */
    private function encodeEncrypted(array $doc, string $key): string
    {
        $id = $doc['_id'] ?? null;
        $payload = $doc;
        if ($id !== null) {
            unset($payload['_id']);
        }

        $plain = $this->encodeJson($payload);
        $encryptionData = $this->encryptData($plain, $key);

        $store = [
            '_id' => $id,
            'encrypted_data' => $encryptionData['encrypted_data'],
            'iv' => $encryptionData['iv'],
        ];

        return $this->encodeJson($store);
    }

    /**
     * Encrypt data using AES-256-CBC.
     */
    private function encryptData(string $plain, string $key): array
    {
        $rawKey = \hash('sha256', $key, true);
        $ivLen = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivLen);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);

        return [
            'encrypted_data' => base64_encode($cipher),
            'iv' => base64_encode($iv),
        ];
    }

    /**
     * Decode a stored document string from the database into an array.
     * If the stored value represents an encrypted payload and the database
     * has an `encryptionKey` configured, it will attempt to decrypt and
     * return the original document (including `_id`). Returns null on
     * parse/decrypt failure.
     */
    public function decodeStored(string $stored): ?array
    {
        $decoded = json_decode($stored, true);
        if ($decoded === null) {
            return null;
        }

        // If not encrypted format, assume it's the raw document
        if (!$this->isEncryptedFormat($decoded)) {
            return $decoded;
        }

        return $this->decryptDocument($decoded);
    }

    /**
     * Check if decoded data represents an encrypted document.
     */
    private function isEncryptedFormat(array $decoded): bool
    {
        return is_array($decoded) && isset($decoded['encrypted_data']);
    }

    /**
     * Decrypt an encrypted document.
     */
    private function decryptDocument(array $decoded): ?array
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;
        if (empty($key)) {
            // Cannot decrypt without key
            return null;
        }

        $decryptionResult = $this->decryptData($decoded);
        if ($decryptionResult === null) {
            return null;
        }

        $payload = json_decode($decryptionResult, true);
        if (!is_array($payload)) {
            return null;
        }

        // Restore _id if present in wrapper
        if (isset($decoded['_id'])) {
            $payload['_id'] = $decoded['_id'];
        }

        return $payload;
    }

    /**
     * Decrypt encrypted data using the provided key.
     */
    private function decryptData(array $decoded): ?string
    {
        $rawKey = hash('sha256', $this->encryptionKey ?? $this->database->encryptionKey ?? '', true);

        $cipher = base64_decode($decoded['encrypted_data'] ?? '');
        $iv = base64_decode($decoded['iv'] ?? '');
        if ($cipher === false || $iv === false) {
            return null;
        }

        return openssl_decrypt($cipher, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Low-level encrypt helper that returns an array with base64-encoded
     * `encrypted_data` and `iv`. Uses the collection key if present else
     * falls back to Database key.
     */
    private function _encryptPlaintext(string $plain): array
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;
        if (empty($key)) {
            throw new \RuntimeException('No encryption key available');
        }

        return $this->encryptData($plain, $key);
    }

    /**
     * Low-level decrypt helper that accepts encrypted_data and iv (base64)
     * and returns the decrypted plaintext or null on failure.
     */
    private function _decryptToPlaintext(string $encryptedBase64, string $ivBase64): ?string
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;
        if (empty($key)) {
            return null;
        }

        return $this->decryptDataString($encryptedBase64, $ivBase64, $key);
    }

    /**
     * Decrypt data using encrypted string and IV.
     */
    private function decryptDataString(string $encryptedBase64, string $ivBase64, string $key): ?string
    {
        $rawKey = hash('sha256', $key, true);
        $cipher = base64_decode($encryptedBase64);
        $iv = base64_decode($ivBase64);
        if ($cipher === false || $iv === false) {
            return null;
        }

        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? null : $plain;
    }

    /**
     * Save document.
     */
    public function save(array $document, bool $create = false): mixed
    {
        // Use upsert for existing documents, insert for new ones
        if (isset($document['_id'])) {
            return $this->upsertDocument($document);
        }

        return $this->insert($document);
    }

    /**
     * Perform an upsert operation (update if exists, insert if not).
     */
    protected function upsertDocument(array $document): mixed
    {
        $json = $this->encodeStored($document);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: '.json_last_error_msg());
        }

        // Prepare searchable columns for the upsert operation
        $indexData = $this->_computeSearchIndexValues($document);
        $upsertData = $this->prepareUpsertData($json, $indexData);

        // Build the upsert SQL statement
        $sql = $this->buildUpsertSql($document['_id'], $json, $upsertData);

        $res = $this->database->connection->exec($sql);

        if ($res === false) {
            $this->logSqlError($sql);

            return false;
        }

        return $document['_id'];
    }

    /**
     * Prepare data for upsert operation.
     */
    protected function prepareUpsertData(string $json, array $indexData): array
    {
        $indexCols = [];
        $indexVals = [];
        $updateAssignments = [];

        foreach ($indexData as $col => $val) {
            $indexCols[] = "`{$col}`";
            $indexVals[] = is_null($val) ? 'NULL' : $this->database->connection->quote($val);
            $updateAssignments[] = "{$col}=".(is_null($val) ? 'NULL' : $this->database->connection->quote($val));
        }

        return [
            'cols' => $indexCols ? ', '.implode(',', $indexCols) : '',
            'vals' => $indexVals ? ', '.implode(',', $indexVals) : '',
            'assignments' => $updateAssignments ? ', '.implode(',', $updateAssignments) : '',
        ];
    }

    /**
     * Build the upsert SQL statement.
     */
    protected function buildUpsertSql($idVal, string $json, array $upsertData): string
    {
        // Optimization: use json_extract on the _id field so SQLite can use indexes
        $quotedId = $this->quoteIdValue($idVal);
        $subQuery = "SELECT id FROM {$this->name} WHERE json_extract(document, '$._id') = {$quotedId} LIMIT 1";

        return "INSERT INTO {$this->name} (id, document{$upsertData['cols']}) VALUES (({$subQuery}), ".$this->database->connection->quote($json)."{$upsertData['vals']}) "
            .'ON CONFLICT(id) DO UPDATE SET document='.$this->database->connection->quote($json).$upsertData['assignments'];
    }

    /**
     * Quote ID value appropriately for SQL.
     */
    protected function quoteIdValue($idVal)
    {
        if (is_int($idVal) || is_float($idVal) || (is_string($idVal) && is_numeric($idVal))) {
            return $idVal;
        }

        return $this->database->connection->quote((string) $idVal);
    }

    /**
     * Update documents.
     */
    public function update($criteria, array $data, bool $merge = true): int
    {
        // Apply before update hooks to modify criteria/data
        $this->applyUpdateHooks($criteria, $data);

        // Build query to find documents matching criteria
        $documentsToUpdate = $this->findDocumentsToUpdate($criteria);

        $updated = 0;

        foreach ($documentsToUpdate as $doc) {
            $updated += $this->updateDocument($doc, $data, $merge);
        }

        return $updated;
    }

    /**
     * Apply before update hooks to modify criteria/data.
     */
    protected function applyUpdateHooks(&$criteria, array &$data): void
    {
        if (!empty($this->hooks['beforeUpdate'])) {
            foreach ($this->hooks['beforeUpdate'] as $hook) {
                $ret = $hook($criteria, $data);
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
    }

    /**
     * Find documents matching criteria for update.
     */
    protected function findDocumentsToUpdate($criteria): array
    {
        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE '.$where;
        } else {
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';
        }

        $stmt = $this->database->connection->query($sql);

        return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /**
     * Update a single document.
     */
    protected function updateDocument(array $doc, array $data, bool $merge): int
    {
        $_doc = $this->decodeStored($doc['document']);

        // Handle null case for $_doc
        if ($_doc === null) {
            $_doc = [];
        }

        $document = $this->mergeDocumentData($_doc, $data, $merge);

        $encoded = $this->encodeStored($document);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Skip this document update if encoding fails
            return 0;
        }

        // Execute update with searchable columns
        $this->executeDocumentUpdate($doc['id'], $document, $encoded);

        // Trigger after update hooks
        $this->triggerAfterUpdateHooks($_doc, $document);

        return 1;
    }

    /**
     * Merge document data based on merge flag.
     */
    protected function mergeDocumentData(array $originalDoc, array $newData, bool $merge): array
    {
        if ($merge) {
            return \array_merge($originalDoc, $newData);
        } else {
            $document = $newData;
            // Preserve the _id field if it exists in the original document
            if (isset($originalDoc['_id'])) {
                $document['_id'] = $originalDoc['_id'];
            }

            return $document;
        }
    }

    /**
     * Execute the actual document update in database.
     */
    protected function executeDocumentUpdate(int $docId, array $document, string $encoded): void
    {
        // Include searchable columns when present
        $indexData = $this->_computeSearchIndexValues($document);
        $assign = [];
        $assign[] = 'document='.$this->database->connection->quote($encoded);
        foreach ($indexData as $col => $val) {
            $assign[] = $col.'='.(is_null($val) ? 'NULL' : $this->database->connection->quote($val));
        }

        $sql = 'UPDATE '.$this->name.' SET '.implode(',', $assign).' WHERE id='.$docId;
        $this->database->connection->exec($sql);
    }

    /**
     * Trigger after update hooks with original and updated document.
     */
    protected function triggerAfterUpdateHooks(array $originalDoc, array $updatedDocument): void
    {
        if (!empty($this->hooks['afterUpdate'])) {
            foreach ($this->hooks['afterUpdate'] as $hook) {
                try {
                    $hook($originalDoc, $updatedDocument);
                } catch (\Throwable $e) {
                    // Ignore hook errors
                }
            }
        }
    }

    /**
     * Remove documents.
     *
     * @return mixed
     */
    public function remove($criteria): int
    {
        // Find documents matching removal criteria
        $documentsToRemove = $this->findDocumentsToRemove($criteria);

        $deleted = 0;

        foreach ($documentsToRemove as $row) {
            if ($this->shouldRemoveDocument($row)) {
                $this->removeDocument($row['id'], $row['document']);
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * Find documents matching criteria for removal.
     */
    protected function findDocumentsToRemove($criteria): array
    {
        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE '.$where;
        } else {
            $sql = 'SELECT id, document FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';
        }

        $stmt = $this->database->connection->query($sql);

        return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /**
     * Check if document should be removed based on before-remove hooks.
     */
    protected function shouldRemoveDocument(array $row): bool
    {
        $doc = $this->decodeStored($row['document']) ?: [];

        // Before remove hooks can veto by returning false
        if (!empty($this->hooks['beforeRemove'])) {
            foreach ($this->hooks['beforeRemove'] as $hook) {
                try {
                    $ret = $hook($doc);
                    if ($ret === false) {
                        return false;
                    }
                } catch (\Throwable $e) {
                    // Ignore hook errors
                }
            }
        }

        return true;
    }

    /**
     * Remove a single document from the database.
     */
    protected function removeDocument(int $docId, string $document): void
    {
        $doc = $this->decodeStored($document) ?: [];

        // Perform deletion by id
        $delSql = 'DELETE FROM '.$this->name.' WHERE id='.$docId;
        $this->database->connection->exec($delSql);

        // Trigger after remove hooks
        $this->triggerAfterRemoveHooks($doc);
    }

    /**
     * Trigger after remove hooks with the removed document.
     */
    protected function triggerAfterRemoveHooks(array $document): void
    {
        if (!empty($this->hooks['afterRemove'])) {
            foreach ($this->hooks['afterRemove'] as $hook) {
                try {
                    $hook($document);
                } catch (\Throwable $e) {
                    // Ignore hook errors
                }
            }
        }
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
            $expr = $this->buildExpressionForKey($key, $value);
            $condition = $this->buildConditionForValue($expr, $value);
            $parts[] = $condition;
        }

        return implode(' AND ', $parts);
    }

    /**
     * Build expression for a given key considering searchable fields.
     */
    private function buildExpressionForKey(string $key, $value): string
    {
        $path = '$.'.str_replace("'", "\\'", $key);

        // If this key is configured as searchable, use the searchable column
        if (isset($this->searchableFields[$key])) {
            return self::SEARCHABLE_PREFIX.$key;
        }

        return "json_extract(document, '".$path."')";
    }

    /**
     * Build condition for a given expression and value.
     */
    private function buildConditionForValue(string $expr, $value): string
    {
        if (is_array($value)) {
            return $this->buildOperatorCondition($expr, $value);
        }

        return $this->buildEqualityCondition($expr, $value);
    }

    /**
     * Build condition for operators ($gt, $gte, $lt, $lte, $in, $nin, $exists).
     */
    private function buildOperatorCondition(string $expr, array $operators): string
    {
        $conditions = [];

        foreach ($operators as $op => $v) {
            $condition = $this->buildSingleOperatorCondition($expr, $op, $v);
            if ($condition) {
                $conditions[] = $condition;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Build condition for a single operator.
     */
    private function buildSingleOperatorCondition(string $expr, string $op, $value): ?string
    {
        switch ($op) {
            case self::JSON_OP_GT:
                return $this->buildComparisonCondition($expr, '>', $value);
            case self::JSON_OP_GTE:
                return $this->buildComparisonCondition($expr, '>=', $value);
            case self::JSON_OP_LT:
                return $this->buildComparisonCondition($expr, '<', $value);
            case self::JSON_OP_LTE:
                return $this->buildComparisonCondition($expr, '<=', $value);
            case self::JSON_OP_IN:
                return $this->buildInCondition($expr, $value, false);
            case self::JSON_OP_NIN:
                return $this->buildInCondition($expr, $value, true);
            case self::JSON_OP_EXISTS:
                return $value ? "{$expr} IS NOT NULL" : "{$expr} IS NULL";
            default:
                // unsupported operator - fallback to strict equality check
                return $this->buildEqualityCondition($expr, $value);
        }
    }

    /**
     * Build comparison condition (>, >=, <, <=).
     */
    private function buildComparisonCondition(string $expr, string $operator, $value): string
    {
        // If this is a searchable field with hashing, hash the value
        if (strpos($expr, self::SEARCHABLE_PREFIX) === 0) {
            $field = substr($expr, strlen(self::SEARCHABLE_PREFIX));
            if (isset($this->searchableFields[$field]) && $this->searchableFields[$field]['hash']) {
                $value = hash('sha256', (string) $value);
            }
        }

        $val = is_numeric($value) ? $value : $this->database->connection->quote((string) $value);

        return "{$expr} {$operator} {$val}";
    }

    /**
     * Build IN/NOT IN condition.
     */
    private function buildInCondition(string $expr, array $values, bool $notIn): ?string
    {
        if (empty($values)) {
            return $notIn ? null : '0'; // Return false condition for empty IN
        }

        // If this is a searchable field with hashing, hash the values
        if (strpos($expr, self::SEARCHABLE_PREFIX) === 0) {
            $field = substr($expr, strlen(self::SEARCHABLE_PREFIX));
            if (isset($this->searchableFields[$field]) && $this->searchableFields[$field]['hash']) {
                $values = array_map(function ($v) {
                    return is_array($v) ? $v : hash('sha256', (string) $v);
                }, $values);
            }
        }

        $vals = [];
        foreach ($values as $item) {
            $vals[] = is_numeric($item) ? $item : $this->database->connection->quote((string) $item);
        }

        $operator = $notIn ? 'NOT IN' : 'IN';

        return "{$expr} {$operator} (".implode(',', $vals).')';
    }

    /**
     * Build equality condition.
     */
    private function buildEqualityCondition(string $expr, $value): string
    {
        // If this is a searchable field with hashing, hash the value
        if (strpos($expr, self::SEARCHABLE_PREFIX) === 0) {
            $field = substr($expr, strlen(self::SEARCHABLE_PREFIX));
            if (isset($this->searchableFields[$field]) && $this->searchableFields[$field]['hash']) {
                $value = hash('sha256', (string) $value);
            }
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $val = $value;
        } elseif (is_bool($value)) {
            // Use numeric boolean representation for comparison
            $val = $value ? '1' : '0';
        } else {
            $val = $this->database->connection->quote((string) $value);
        }

        return "{$expr} = {$val}";
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
