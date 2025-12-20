<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;
use PocketDB\Database;

class DatabaseTest extends TestCase
{
    private $client;
    private $db;
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/test_databases';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        $this->client = new Client($this->testDir);
        $this->db = $this->client->selectDB('testdb');
    }

    protected function tearDown(): void
    {
        // Ensure client and DB connections are closed before removing files
        $this->client = null;
        $this->db = null;

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

    public function testDatabaseConstructorWithMemory()
    {
        $db = new Database(':memory:');
        $this->assertInstanceOf(Database::class, $db);
        $this->assertEquals(':memory:', $db->path);
        $this->assertInstanceOf(\PDO::class, $db->connection);
    }

    public function testDatabaseConstructorWithFile()
    {
        $db = new Database($this->testDir . '/test.sqlite');
        $this->assertInstanceOf(Database::class, $db);
        $this->assertEquals($this->testDir . '/test.sqlite', $db->path);
        $this->assertInstanceOf(\PDO::class, $db->connection);
    }

    public function testCreateCollection()
    {
        $this->db->createCollection('testcollection');
        $this->assertTrue(true); // If no exception, test passes

        // Verify collection was created
        $collections = $this->db->getCollectionNames();
        $this->assertContains('testcollection', $collections);
    }

    public function testDropCollection()
    {
        // Create a collection first
        $this->db->createCollection('droptest');
        $collections = $this->db->getCollectionNames();
        $this->assertContains('droptest', $collections);

        // Drop the collection
        $this->db->dropCollection('droptest');
        $collections = $this->db->getCollectionNames();
        $this->assertNotContains('droptest', $collections);
    }

    public function testSelectCollectionAutoCreates()
    {
        $collection = $this->db->selectCollection('newcollection');
        $this->assertInstanceOf(\PocketDB\Collection::class, $collection);

        // Verify collection was created
        $collections = $this->db->getCollectionNames();
        $this->assertContains('newcollection', $collections);
    }

    public function testSelectCollectionMagicGet()
    {
        $collection = $this->db->magiccollection;
        $this->assertInstanceOf(\PocketDB\Collection::class, $collection);

        // Verify collection was created
        $collections = $this->db->getCollectionNames();
        $this->assertContains('magiccollection', $collections);
    }

    public function testGetCollectionNames()
    {
        // Create some collections
        $this->db->createCollection('users');
        $this->db->createCollection('products');
        $this->db->createCollection('orders');

        $names = $this->db->getCollectionNames();
        $this->assertIsArray($names);
        $this->assertContains('users', $names);
        $this->assertContains('products', $names);
        $this->assertContains('orders', $names);
        $this->assertNotContains('sqlite_sequence', $names);
    }

    public function testListCollections()
    {
        // Create some collections
        $this->db->createCollection('users');
        $this->db->createCollection('products');

        $collections = $this->db->listCollections();
        $this->assertIsArray($collections);
        $this->assertInstanceOf(\PocketDB\Collection::class, $collections['users']);
        $this->assertInstanceOf(\PocketDB\Collection::class, $collections['products']);
    }

    public function testVacuum()
    {
        // This test just ensures vacuum doesn't throw an exception
        $this->db->vacuum();
        $this->assertTrue(true);
    }

    public function testDropMemoryDatabase()
    {
        $memoryDb = new Database(':memory:');
        $memoryDb->drop(); // Should not throw exception
        $this->assertTrue(true);
    }

    public function testDropFileDatabase()
    {
        $fileDb = new Database($this->testDir . '/tempdb.sqlite');
        $fileDb->drop();
        $this->assertFalse(file_exists($this->testDir . '/tempdb.sqlite'));
    }

    public function testRegisterCriteriaFunctionWithArray()
    {
        $criteria = ['name' => 'John'];
        $id = $this->db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test that the function is callable
        $document = ['name' => 'John', 'age' => 30];
        $result = $this->db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with non-matching document
        $document2 = ['name' => 'Jane', 'age' => 25];
        $result2 = $this->db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testRegisterCriteriaFunctionWithCallable()
    {
        $criteria = function ($document) {
            return isset($document['active']) && $document['active'] === true;
        };

        $id = $this->db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with matching document
        $document = ['active' => true, 'name' => 'John'];
        $result = $this->db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with non-matching document
        $document2 = ['active' => false, 'name' => 'Jane'];
        $result2 = $this->db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testRegisterCriteriaFunctionWithNull()
    {
        $id = $this->db->registerCriteriaFunction(null);
        $this->assertNull($id);
    }
}
