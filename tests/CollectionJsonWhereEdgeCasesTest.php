<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;

require_once __DIR__.'/TestPDORecorder.php';

class CollectionJsonWhereEdgeCasesTest extends TestCase
{
    private $client;
    private $collection;
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__.'/test_databases';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        $this->client = new Client($this->testDir);
        $db = $this->client->selectDB('testdb_json_edge');
        $this->collection = $db->selectCollection('jsonedge');
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->collection = null;
        \PocketDB\Database::closeAll();

        if (is_dir($this->testDir)) {
            $files = glob($this->testDir.'/*.sqlite');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            @rmdir($this->testDir);
        }
    }

    public function testEmptyInArrayReturnsNoResults()
    {
        $docs = [
            ['k' => 'v1'],
            ['k' => 'v2'],
        ];
        $this->collection->insertMany($docs);

        $results = $this->collection->find(['k' => ['$in' => []]])->toArray();
        $this->assertCount(0, $results, 'Empty $in should produce no results');
    }

    public function testMixedTypeComparisonStringNumber()
    {
        $docs = [
            ['num' => 10],
            ['num' => 20],
            ['num' => '30'],
        ];
        $this->collection->insertMany($docs);

        // Numeric string should be treated as numeric for comparison
        $results = $this->collection->find(['num' => ['$gt' => '15']])->toArray();
        $this->assertCount(2, $results);
        $values = array_column($results, 'num');
        sort($values);
        $this->assertEquals([20, '30'], $values);
    }

    public function testBooleanAndNullBehavior()
    {
        $docs = [
            ['flag' => true],
            ['flag' => false],
            ['other' => 1],
        ];

        $this->collection->insertMany($docs);

        $trueResults = $this->collection->find(['flag' => true])->toArray();
        $this->assertCount(1, $trueResults);

        $falseResults = $this->collection->find(['flag' => false])->toArray();
        $this->assertCount(1, $falseResults);

        $existsResults = $this->collection->find(['flag' => ['$exists' => false]])->toArray();
        $this->assertCount(1, $existsResults);
    }

    public function testSqlTranslationQueryIsUsed()
    {
        // Insert some data
        $docs = [
            ['x' => 1],
            ['x' => 5],
        ];
        $this->collection->insertMany($docs);

        // Replace PDO with recorder pointing to same file (so data stays in file)
        $db = $this->collection->database;
        $dsn = 'sqlite:'.$db->path;
        $rec = new TestPDORecorder($dsn);

        // Register the sqlite helper functions required by the library
        $rec->sqliteCreateFunction('document_key', function ($key, $document) {
            if ($document === null) {
                return '';
            }
            $document = json_decode($document, true);
            if (!is_array($document)) {
                return '';
            }
            if (strpos($key, '.') !== false) {
                $keys = explode('.', $key);
                $ref = $document;
                foreach ($keys as $k) {
                    if (!is_array($ref) || !array_key_exists($k, $ref)) {
                        $ref = null;
                        break;
                    }
                    $ref = $ref[$k];
                }
                $val = $ref ?? '';
            } else {
                $val = array_key_exists($key, $document) ? $document[$key] : '';
            }

            return is_array($val) || is_object($val) ? json_encode($val) : $val;
        }, 2);
        $rec->sqliteCreateFunction('document_criteria', ['\\PocketDB\\Database', 'staticCallCriteria'], 2);

        // swap connection
        $db->connection = $rec;

        // perform a find that should translate to a json_extract based WHERE
        $items = $this->collection->find(['x' => ['$gt' => 2]])->toArray();
        $this->assertCount(1, $items);

        $last = $rec->lastQuery();
        $this->assertNotNull($last, 'Recorder should have captured a query');
        $this->assertStringContainsString("json_extract(document, '$.x') >", $last);
    }
}
