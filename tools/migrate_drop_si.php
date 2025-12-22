<?php
/**
 * Simple migration utility to remove a searchable index column (si_{field})
 * from a SQLite PocketDB collection while preserving other columns, indexes
 * and triggers where possible.
 *
 * Usage:
 *   php tools/migrate_drop_si.php /path/to/db.sqlite collection_name field_name
 *
 * This will rebuild the table without the `si_{field_name}` column. Indexes
 * or triggers that reference the dropped column will be skipped.
 */
if ($argc < 4) {
    echo "Usage: php tools/migrate_drop_si.php <db_path> <collection> <field>\n";
    exit(2);
}

$dbPath = $argv[1];
$table = $argv[2];
$field = $argv[3];
$colToDrop = 'si_'.$field;

if (!file_exists($dbPath) && $dbPath !== ':memory:') {
    echo "Database file not found: {$dbPath}\n";
    exit(3);
}

$dsn = "sqlite:{$dbPath}";
$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check table exists
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='".addslashes($table)."'");
$exists = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exists) {
    echo "Table '{$table}' not found in database.\n";
    exit(4);
}

try {
    $pdo->beginTransaction();

    // Read table columns
    $stmt = $pdo->query("PRAGMA table_info('".$table."')");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');

    if (!in_array($colToDrop, $colNames, true)) {
        echo "Column {$colToDrop} does not exist on table {$table}. Nothing to do.\n";
        $pdo->rollBack();
        exit(0);
    }

    // Find indexes and triggers for this table
    $stmt = $pdo->query("SELECT type,name,sql FROM sqlite_master WHERE tbl_name='".addslashes($table)."' AND (type='index' OR type='trigger')");
    $objects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determine which indexes reference the column to drop
    $indexesToRecreate = [];
    foreach ($objects as $obj) {
        if ($obj['type'] === 'index') {
            $idxName = $obj['name'];
            if (empty($obj['sql'])) {
                // implicit index (sqlite auto-created), skip
                continue;
            }
            // get columns used by index
            $ixinfo = $pdo->query("PRAGMA index_info('".$idxName."')")->fetchAll(PDO::FETCH_ASSOC);
            $colsInIndex = array_column($ixinfo, 'name');
            if (in_array($colToDrop, $colsInIndex, true)) {
                echo "Skipping index {$idxName} because it references {$colToDrop}\n";
                continue;
            }
            $indexesToRecreate[] = $obj['sql'];
        }
    }

    // triggers: collect SQLs (some may reference dropped column, leave to fail harmlessly)
    $triggersToRecreate = [];
    foreach ($objects as $obj) {
        if ($obj['type'] === 'trigger' && !empty($obj['sql'])) {
            // crude check: if trigger SQL contains the column name, skip
            if (stripos($obj['sql'], $colToDrop) !== false) {
                echo "Skipping trigger {$obj['name']} because it references {$colToDrop}\n";
                continue;
            }
            $triggersToRecreate[] = $obj['sql'];
        }
    }

    // Columns to keep
    $keepCols = array_filter($cols, function ($c) use ($colToDrop) { return $c['name'] !== $colToDrop; });
    $keepNames = array_column($keepCols, 'name');
    $colsList = implode(', ', array_map(function ($n) { return "`{$n}`"; }, $keepNames));

    $tmp = $table.'_tmp_'.uniqid();

    // Build CREATE TABLE for temp table with simple column defs
    $createCols = [];
    foreach ($keepCols as $c) {
        $type = $c['type'] ?? '';
        $notnull = $c['notnull'] ? ' NOT NULL' : '';
        $dflt = $c['dflt_value'] !== null ? ' DEFAULT '.$c['dflt_value'] : '';
        $createCols[] = "`{$c['name']}` {$type}{$notnull}{$dflt}";
    }

    $createSql = "CREATE TABLE `{$tmp}` (".implode(', ', $createCols).')';
    $pdo->exec($createSql);

    // Copy data across
    $pdo->exec("INSERT INTO `{$tmp}` ({$colsList}) SELECT {$colsList} FROM `{$table}`");

    // Drop original and rename tmp
    $pdo->exec("DROP TABLE `{$table}`");
    $pdo->exec("ALTER TABLE `{$tmp}` RENAME TO `{$table}`");

    // Recreate indexes
    foreach ($indexesToRecreate as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            echo 'Failed to recreate index: '.$e->getMessage()."\n";
        }
    }

    // Recreate triggers
    foreach ($triggersToRecreate as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            echo 'Failed to recreate trigger: '.$e->getMessage()."\n";
        }
    }

    $pdo->commit();
    echo "Migration completed: dropped column {$colToDrop} from {$table}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Migration failed: '.$e->getMessage()."\n";
    exit(1);
}
