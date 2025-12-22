<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;

class CollectionJsonWhereTest extends TestCase
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
        $db = $this->client->selectDB('testdb_json_where');
        $this->collection = $db->selectCollection('jsonwhere');
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->collection = null;
        \PocketDB\Database::closeAll();

        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*.sqlite');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            @rmdir($this->testDir);
        }
    }

    public function testGreaterThanOperator()
    {
        $docs = [
            ['name' => 'a', 'score' => 5],
            ['name' => 'b', 'score' => 15],
            ['name' => 'c', 'score' => 25],
        ];

        $this->collection->insertMany($docs);

        $results = $this->collection->find(['score' => ['$gt' => 10]])->toArray();

        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        sort($names);
        $this->assertEquals(['b', 'c'], $names);
    }

    public function testLessThanOrEqualOperator()
    {
        $docs = [
            ['name' => 'x', 'value' => 1],
            ['name' => 'y', 'value' => 10],
            ['name' => 'z', 'value' => 20],
        ];

        $this->collection->insertMany($docs);

        $results = $this->collection->find(['value' => ['$lte' => 10]])->toArray();

        $this->assertCount(2, $results);
        $values = array_column($results, 'value');
        sort($values);
        $this->assertEquals([1,10], $values);
    }

    public function testInAndNinOperators()
    {
        $docs = [
            ['title' => 't1', 'tag' => 'red'],
            ['title' => 't2', 'tag' => 'blue'],
            ['title' => 't3', 'tag' => 'green'],
            ['title' => 't4', 'tag' => 'red'],
        ];

        $this->collection->insertMany($docs);

        $inResults = $this->collection->find(['tag' => ['$in' => ['red', 'green']]])->toArray();
        $this->assertCount(3, $inResults);

        $ninResults = $this->collection->find(['tag' => ['$nin' => ['red', 'green']]])->toArray();
        $this->assertCount(1, $ninResults);
        $this->assertEquals('blue', $ninResults[0]['tag']);
    }

    public function testExistsOperator()
    {
        $docs = [
            ['a' => 1, 'present' => true],
            ['a' => 2],
            ['a' => 3, 'present' => false],
        ];

        $this->collection->insertMany($docs);

        $existsTrue = $this->collection->find(['present' => ['$exists' => true]])->toArray();
        $this->assertCount(2, $existsTrue);

        $existsFalse = $this->collection->find(['present' => ['$exists' => false]])->toArray();
        $this->assertCount(1, $existsFalse);
    }

    public function testNestedFieldOperators()
    {
        $docs = [
            ['name' => 'n1', 'profile' => ['age' => 21]],
            ['name' => 'n2', 'profile' => ['age' => 30]],
            ['name' => 'n3', 'profile' => ['age' => 40]],
        ];

        $this->collection->insertMany($docs);

        $results = $this->collection->find(['profile.age' => ['$gt' => 25]])->toArray();
        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        sort($names);
        $this->assertEquals(['n2','n3'], $names);
    }
}
