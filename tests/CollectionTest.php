<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;
use PocketDB\Collection;

class CollectionTest extends TestCase
{
    private $client;
    private $collection;
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/test_databases';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        $this->client = new Client($this->testDir);
        $db = $this->client->selectDB('testdb');
        $this->collection = $db->selectCollection('testcollection');
    }

    protected function tearDown(): void
    {
        // Ensure client and DB connections are closed before removing files
        $this->client = null;
        $this->collection = null;

        // Close any lingering DB connections and clean up test databases
        \PocketDB\Database::closeAll();

        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*.sqlite');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
    }

    public function testInsertSingleDocument()
    {
        $document = ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30];
        $result = $this->collection->insert($document);

        $this->assertIsString($result);
        $this->assertStringContainsString('-', $result); // UUID format

        // Verify the document was inserted
        $found = $this->collection->findOne(['name' => 'John Doe']);
        $this->assertNotNull($found);
        $this->assertEquals('John Doe', $found['name']);
        $this->assertEquals('john@example.com', $found['email']);
        $this->assertEquals(30, $found['age']);
    }

    public function testInsertMultipleDocuments()
    {
        $documents = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
            ['name' => 'Charlie', 'email' => 'charlie@example.com']
        ];

        $result = $this->collection->insertMany($documents);
        $this->assertEquals(3, $result);

        // Verify all documents were inserted
        $count = $this->collection->count();
        $this->assertEquals(3, $count);
    }

    public function testInsertWithCustomId()
    {
        $document = ['_id' => 'custom123', 'name' => 'John Doe', 'email' => 'john@example.com'];
        $result = $this->collection->insert($document);

        $this->assertEquals('custom123', $result);

        // Verify the document was inserted with custom ID
        $found = $this->collection->findOne(['_id' => 'custom123']);
        $this->assertNotNull($found);
        $this->assertEquals('custom123', $found['_id']);
    }

    public function testInsertManualIdMode()
    {
        $this->collection->setIdModeManual();

        $documentWithoutId = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $result = $this->collection->insert($documentWithoutId);

        $this->assertFalse($result); // Should fail without _id in manual mode

        // Test with _id
        $documentWithId = ['_id' => 'manual123', 'name' => 'Jane Doe', 'email' => 'jane@example.com'];
        $result2 = $this->collection->insert($documentWithId);
        $this->assertEquals('manual123', $result2);
    }

    public function testInsertPrefixIdMode()
    {
        $this->collection->setIdModePrefix('USR');

        $document = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $result = $this->collection->insert($document);

        $this->assertIsString($result);
        $this->assertStringStartsWith('USR-', $result);

        // Insert another document to test increment
        $document2 = ['name' => 'Jane Doe', 'email' => 'jane@example.com'];
        $result2 = $this->collection->insert($document2);
        $this->assertStringStartsWith('USR-', $result2);
        $this->assertNotEquals($result, $result2);
    }

    public function testFindDocuments()
    {
        // Insert test data
        $this->collection->insert([
            ['name' => 'Alice', 'age' => 25, 'city' => 'New York'],
            ['name' => 'Bob', 'age' => 30, 'city' => 'London'],
            ['name' => 'Charlie', 'age' => 35, 'city' => 'Paris']
        ]);

        // Find all documents
        $cursor = $this->collection->find();
        $this->assertInstanceOf(\PocketDB\Cursor::class, $cursor);

        $documents = $cursor->toArray();
        $this->assertCount(3, $documents);

        // Find with criteria
        $cursor2 = $this->collection->find(['age' => 30]);
        $documents2 = $cursor2->toArray();
        $this->assertCount(1, $documents2);
        $this->assertEquals('Bob', $documents2[0]['name']);

        // Find with multiple criteria
        $cursor3 = $this->collection->find(['age' => 30, 'city' => 'London']);
        $documents3 = $cursor3->toArray();
        $this->assertCount(1, $documents3);
        $this->assertEquals('Bob', $documents3[0]['name']);
    }

    public function testFindOneDocument()
    {
        // Insert test data
        $this->collection->insert([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30]
        ]);

        // Find one document
        $document = $this->collection->findOne(['age' => 30]);
        $this->assertNotNull($document);
        $this->assertEquals('Bob', $document['name']);

        // Find non-existent document
        $document2 = $this->collection->findOne(['age' => 99]);
        $this->assertNull($document2);
    }

    public function testUpdateDocuments()
    {
        // Insert test data
        $this->collection->insert([
            ['name' => 'Alice', 'age' => 25, 'city' => 'New York'],
            ['name' => 'Bob', 'age' => 30, 'city' => 'London']
        ]);

        // Update documents
        $result = $this->collection->update(['age' => 25], ['age' => 26, 'city' => 'Boston']);
        $this->assertEquals(1, $result);

        // Verify the update
        $document = $this->collection->findOne(['name' => 'Alice']);
        $this->assertEquals(26, $document['age']);
        $this->assertEquals('Boston', $document['city']);
    }

    public function testUpdateWithoutMerge()
    {
        // Insert test data
        $this->collection->insert(['name' => 'Alice', 'age' => 25, 'city' => 'New York']);

        // Update without merge (replace)
        $result = $this->collection->update(['name' => 'Alice'], ['name' => 'Alice Updated', 'status' => 'active'], false);
        $this->assertEquals(1, $result);

        // Verify the update
        $document = $this->collection->findOne(['name' => 'Alice Updated']);
        $this->assertNotNull($document);
        $this->assertEquals('active', $document['status']);
        $this->assertArrayNotHasKey('age', $document); // Should be removed
    }

    public function testRemoveDocuments()
    {
        // Insert test data
        $this->collection->insert([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Charlie', 'age' => 35]
        ]);

        // Remove documents
        $result = $this->collection->remove(['age' => 30]);
        $this->assertEquals(1, $result);

        // Verify the removal
        $count = $this->collection->count();
        $this->assertEquals(2, $count);

        // Verify Bob was removed
        $document = $this->collection->findOne(['name' => 'Bob']);
        $this->assertNull($document);
    }

    public function testCountDocuments()
    {
        // Insert test data
        $this->collection->insert([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Charlie', 'age' => 35]
        ]);

        // Count all documents
        $count = $this->collection->count();
        $this->assertEquals(3, $count);

        // Count with criteria
        $count2 = $this->collection->count(['age' => 30]);
        $this->assertEquals(1, $count2);
    }

    public function testSaveDocument()
    {
        // Insert new document
        $document = ['name' => 'Alice', 'email' => 'alice@example.com'];
        $result = $this->collection->save($document);
        $this->assertIsString($result);

        // Update existing document
        $document2 = ['_id' => $result, 'name' => 'Alice Updated', 'email' => 'alice.updated@example.com'];
        $result2 = $this->collection->save($document2);
        $this->assertEquals($result, $result2);

        // Verify the update
        $found = $this->collection->findOne(['_id' => $result]);
        $this->assertEquals('Alice Updated', $found['name']);
        $this->assertEquals('alice.updated@example.com', $found['email']);
    }

    public function testSaveDocumentWithCreate()
    {
        // Save document with _id (should update if exists, create if not)
        $document = ['_id' => 'test123', 'name' => 'Test User', 'email' => 'test@example.com'];
        $result = $this->collection->save($document, true);
        $this->assertEquals('test123', $result);

        // Verify the document was created
        $found = $this->collection->findOne(['_id' => 'test123']);
        $this->assertNotNull($found);
        $this->assertEquals('Test User', $found['name']);
    }

    public function testDropCollection()
    {
        // Insert test data
        $this->collection->insert(['name' => 'Test']);

        // Drop the collection
        $this->collection->drop();

        // Verify the collection was dropped
        $count = $this->collection->count();
        $this->assertEquals(0, $count);
    }

    public function testRenameCollection()
    {
        // Insert test data
        $this->collection->insert(['name' => 'Test']);

        // Rename the collection
        $result = $this->collection->renameCollection('renamed_collection');
        $this->assertTrue($result);

        // Verify the collection was renamed
        $count = $this->collection->count();
        $this->assertEquals(1, $count);

        // Verify old collection name doesn't exist
        $db = $this->collection->database;
        $collections = $db->getCollectionNames();
        $this->assertNotContains('testcollection', $collections);
        $this->assertContains('renamed_collection', $collections);
    }

    public function testRenameCollectionToExistingName()
    {
        // Create another collection
        $db = $this->collection->database;
        $db->createCollection('existing_collection');

        // Try to rename to existing collection
        $result = $this->collection->renameCollection('existing_collection');
        $this->assertFalse($result);
    }
}
