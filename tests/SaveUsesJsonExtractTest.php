<?php

use PHPUnit\Framework\TestCase;

class SaveUsesJsonExtractTest extends TestCase
{
    public function testSaveUsesJsonExtractInUpsert()
    {
        // Use TestPDORecorder if available to capture executed SQL
        if (!class_exists('\Tests\TestPDORecorder')) {
            $this->markTestSkipped('TestPDORecorder not available');

            return;
        }

        // Prepare a file-backed sqlite path and recorder PDO
        $dbPath = sys_get_temp_dir().'/pocketdb_test_save.sqlite';
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }

        // instantiate recorder PDO pointing to the file-backed sqlite
        $rec = new Tests\TestPDORecorder('sqlite:'.$dbPath);

        // Create Database normally (which will create the file), then replace its PDO with recorder
        $database = new PocketDB\Database($dbPath);
        $database->connection = $rec;

        // ensure collection table exists (selectCollection will create if missing)
        $coll = $database->selectCollection('users');

        // create index on _id to ensure json_extract pattern is useful
        $database->createJsonIndex('users', '_id');

        $doc = ['_id' => 'user-123', 'name' => 'Tester'];

        // call save on collection
        $coll->save($doc);

        // search captured queries for json_extract call
        $found = false;
        foreach ($rec->queries as $q) {
            if (strpos($q, "json_extract(document, '$._id')") !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected save() upsert to use json_extract on _id in SQL');

        // cleanup
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
    }
}
