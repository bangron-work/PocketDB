<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;

class ClientTest extends TestCase
{
    private $client;
    private $testDir;
    public $uniqueSuffix;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/test_databases';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        // Use unique database names for each test
        $this->uniqueSuffix = uniqid();
        $this->client = new Client($this->testDir);
    }

    protected function tearDown(): void
    {
        // Ensure client and DB connections are closed before removing files
        $this->client = null;

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

    public function testClientConstructorWithDirectory()
    {
        $this->assertInstanceOf(Client::class, $this->client);
        $this->assertEquals($this->testDir, $this->client->path);
    }

    public function testClientConstructorWithMemory()
    {
        $memoryClient = new Client(':memory:');
        $this->assertInstanceOf(Client::class, $memoryClient);
        $this->assertEquals(':memory:', $memoryClient->path);
    }

    public function testSelectDatabase()
    {
        $db = $this->client->selectDB('testdb');
        $this->assertInstanceOf(\PocketDB\Database::class, $db);

        // Test that the same database instance is returned when selecting again
        $db2 = $this->client->selectDB('testdb');
        $this->assertSame($db, $db2);
    }

    public function testSelectDatabaseViaMagicGet()
    {
        $db = $this->client->testdb;
        $this->assertInstanceOf(\PocketDB\Database::class, $db);

        // Test that the same database instance is returned when accessing again
        $db2 = $this->client->testdb;
        $this->assertSame($db, $db2);
    }

    public function testSelectCollection()
    {
        $collection = $this->client->selectCollection('testdb', 'testcollection');
        $this->assertInstanceOf(\PocketDB\Collection::class, $collection);
    }

    public function testListDatabasesWithDirectory()
    {
        // Create some test databases
        $db1 = $this->client->selectDB('db1');
        $db2 = $this->client->selectDB('db2');

        $databases = $this->client->listDBs();
        $this->assertIsArray($databases);
        $this->assertContains('db1', $databases);
        $this->assertContains('db2', $databases);
    }

    public function testListDatabasesWithMemory()
    {
        $memoryClient = new Client(':memory:');
        $db1 = $memoryClient->selectDB('db1');
        $db2 = $memoryClient->selectDB('db2');

        $databases = $memoryClient->listDBs();
        $this->assertIsArray($databases);
        $this->assertContains('db1', $databases);
        $this->assertContains('db2', $databases);
    }

    public function testListDatabasesEmpty()
    {
        $databases = $this->client->listDBs();
        $this->assertIsArray($databases);
        $this->assertEmpty($databases);
    }

    public function testDatabasePersistence()
    {
        $db = $this->client->selectDB('persistentdb');
        $collection = $db->selectCollection('users');

        // Insert a document
        $result = $collection->insert(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->assertIsString($result);

        // Create a new client instance pointing to the same directory
        $newClient = new Client($this->testDir);
        $newDb = $newClient->selectDB('persistentdb');
        $newCollection = $newDb->selectCollection('users');

        // Verify the document still exists
        $document = $newCollection->findOne(['name' => 'John Doe']);
        $this->assertNotNull($document);
        $this->assertEquals('John Doe', $document['name']);
        $this->assertEquals('john@example.com', $document['email']);
    }
}
