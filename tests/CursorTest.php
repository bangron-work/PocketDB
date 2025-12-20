<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;
use PocketDB\Cursor;

class CursorTest extends TestCase
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

        // Insert test data
        $this->collection->insert([
            ['name' => 'Alice', 'age' => 25, 'city' => 'New York', 'active' => true],
            ['name' => 'Bob', 'age' => 30, 'city' => 'London', 'active' => false],
            ['name' => 'Charlie', 'age' => 35, 'city' => 'Paris', 'active' => true],
            ['name' => 'David', 'age' => 25, 'city' => 'Tokyo', 'active' => true],
            ['name' => 'Eve', 'age' => 30, 'city' => 'Sydney', 'active' => false]
        ]);
    }

    protected function tearDown(): void
    {
        // 1. Putuskan referensi
        $this->collection = null;
        $this->client = null;

        // 2. Tutup semua koneksi static
        \PocketDB\Database::closeAll();

        // 3. Paksa Garbage Collection untuk melepas file lock di Windows
        gc_collect_cycles();

        if (is_dir($this->testDir)) {
            // Gunakan glob untuk menghapus semua file termasuk file tersembunyi/temp
            $files = array_merge(
                glob($this->testDir . '/*.sqlite'),
                glob($this->testDir . '/*.sqlite-wal'),
                glob($this->testDir . '/*.sqlite-shm')
            );
            foreach ($files as $file) {
                if (is_file($file)) @unlink($file);
            }
            @rmdir($this->testDir);
        }
    }

    public function testCursorConstructor()
    {
        $cursor = $this->collection->find(['age' => 25]);
        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertSame($this->collection, $cursor->collection);
        $this->assertNotNull($cursor->criteria);
    }

    public function testToArray()
    {
        $cursor = $this->collection->find(['age' => 25]);
        $documents = $cursor->toArray();

        $this->assertIsArray($documents);
        $this->assertCount(2, $documents);

        // Verify the documents
        $names = array_column($documents, 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('David', $names);
    }

    public function testLimit()
    {
        $cursor = $this->collection->find()->limit(2);
        $documents = $cursor->toArray();

        $this->assertCount(2, $documents);
    }

    public function testSkip()
    {
        $cursor = $this->collection->find()->skip(2);
        $documents = $cursor->toArray();

        $this->assertCount(3, $documents); // Total 5 documents, skip 2

        // Verify the first document is the third one
        $this->assertEquals('Charlie', $documents[0]['name']);
    }

    public function testSkipAndLimit()
    {
        $cursor = $this->collection->find()->skip(1)->limit(2);
        $documents = $cursor->toArray();

        $this->assertCount(2, $documents);

        // Verify the documents are the second and third
        $this->assertEquals('Bob', $documents[0]['name']);
        $this->assertEquals('Charlie', $documents[1]['name']);
    }

    public function testSortAscending()
    {
        $cursor = $this->collection->find()->sort(['age' => 1]);
        $documents = $cursor->toArray();

        $this->assertCount(5, $documents);

        // Verify sorting by age
        $ages = array_column($documents, 'age');
        $this->assertEquals([25, 25, 30, 30, 35], $ages);
    }

    public function testSortDescending()
    {
        $cursor = $this->collection->find()->sort(['age' => -1]);
        $documents = $cursor->toArray();

        $this->assertCount(5, $documents);

        // Verify sorting by age descending
        $ages = array_column($documents, 'age');
        $this->assertEquals([35, 30, 30, 25, 25], $ages);
    }

    public function testSortMultipleFields()
    {
        $cursor = $this->collection->find()->sort(['age' => 1, 'name' => 1]);
        $documents = $cursor->toArray();

        $this->assertCount(5, $documents);

        // Verify sorting by age, then by name
        $ages = array_column($documents, 'age');
        $names = array_column($documents, 'name');

        // First two should be age 25: Alice, David
        $this->assertEquals(25, $ages[0]);
        $this->assertEquals('Alice', $names[0]);
        $this->assertEquals(25, $ages[1]);
        $this->assertEquals('David', $names[1]);

        // Next two should be age 30: Bob, Eve
        $this->assertEquals(30, $ages[2]);
        $this->assertEquals('Bob', $names[2]);
        $this->assertEquals(30, $ages[3]);
        $this->assertEquals('Eve', $names[3]);

        // Last should be age 35: Charlie
        $this->assertEquals(35, $ages[4]);
        $this->assertEquals('Charlie', $names[4]);
    }

    public function testCount()
    {
        $cursor = $this->collection->find(['age' => 25]);
        $count = $cursor->count();

        $this->assertEquals(2, $count);
    }

    public function testCountWithLimit()
    {
        $cursor = $this->collection->find()->limit(3);
        $count = $cursor->count();

        $this->assertEquals(3, $count);
    }

    public function testEach()
    {
        $names = [];
        $cursor = $this->collection->find(['age' => 25]);

        $cursor->each(function ($document) use (&$names) {
            $names[] = $document['name'];
        });

        $this->assertCount(2, $names);
        $this->assertContains('Alice', $names);
        $this->assertContains('David', $names);
    }

    public function testIteratorInterface()
    {
        $cursor = $this->collection->find(['age' => 25]);
        $documents = [];

        foreach ($cursor as $document) {
            $documents[] = $document;
        }

        $this->assertCount(2, $documents);
        $this->assertContains('Alice', array_column($documents, 'name'));
        $this->assertContains('David', array_column($documents, 'name'));
    }

    public function testIteratorRewind()
    {
        $cursor = $this->collection->find(['age' => 25]);

        // First iteration
        $firstDoc = $cursor->current();
        $cursor->next();
        $secondDoc = $cursor->current();

        // Rewind and iterate again
        $cursor->rewind();
        $firstDocAgain = $cursor->current();

        $this->assertEquals($firstDoc['name'], $firstDocAgain['name']);
    }

    public function testIteratorKey()
    {
        $cursor = $this->collection->find(['age' => 25]);

        $cursor->rewind();
        $this->assertEquals(0, $cursor->key());

        $cursor->next();
        $this->assertEquals(1, $cursor->key());

        $cursor->next();
        $this->assertEquals(1, $cursor->key()); // Should stay at 1 since there are only 2 documents
    }

    public function testIteratorValid()
    {
        $cursor = $this->collection->find(['age' => 25]);

        $cursor->rewind();
        $this->assertTrue($cursor->valid());

        $cursor->next();
        $this->assertTrue($cursor->valid());

        $cursor->next();
        $this->assertFalse($cursor->valid());
    }

    public function testProjectionInclude()
    {
        $cursor = $this->collection->find(['name' => 'Alice'], ['name' => 1, 'age' => 1]);
        $documents = $cursor->toArray();

        $this->assertCount(1, $documents);
        $document = $documents[0];

        $this->assertArrayHasKey('name', $document);
        $this->assertArrayHasKey('age', $document);
        $this->assertArrayNotHasKey('city', $document);
        $this->assertArrayNotHasKey('active', $document);
        $this->assertArrayHasKey('_id', $document); // _id should always be included
    }

    public function testProjectionExclude()
    {
        $cursor = $this->collection->find(['name' => 'Alice'], ['city' => 0, 'active' => 0]);
        $documents = $cursor->toArray();

        $this->assertCount(1, $documents);
        $document = $documents[0];

        $this->assertArrayHasKey('name', $document);
        $this->assertArrayHasKey('age', $document);
        $this->assertArrayNotHasKey('city', $document);
        $this->assertArrayNotHasKey('active', $document);
        $this->assertArrayHasKey('_id', $document); // _id should always be included
    }

    public function testComplexCriteriaWithCursor()
    {
        $cursor = $this->collection->find(['age' => ['$gt' => 25, '$lt' => 35]]);
        $documents = $cursor->toArray();

        $this->assertCount(2, $documents); // Bob (30) and Eve (30)

        $names = array_column($documents, 'name');
        $this->assertContains('Bob', $names);
        $this->assertContains('Eve', $names);
    }

    public function testEmptyResult()
    {
        $cursor = $this->collection->find(['age' => 99]);
        $documents = $cursor->toArray();

        $this->assertCount(0, $documents);

        $count = $cursor->count();
        $this->assertEquals(0, $count);
    }

    public function testChainMethods()
    {
        $cursor = $this->collection->find(['active' => true])
            ->sort(['age' => 1])
            ->skip(1)
            ->limit(1);

        $documents = $cursor->toArray();

        $this->assertCount(1, $documents);

        // Should be David (age 25, skipped Alice, only one result)
        $this->assertEquals('David', $documents[0]['name']);
    }
}
