<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;
use PocketDB\Collection;

class CursorPopulateTest extends TestCase
{
    private ?Client $client;
    private string $testDir;

    private ?Collection $users;
    private ?Collection $products;
    private ?Collection $orders;

    protected function setUp(): void
    {
        $this->testDir = __DIR__.'/test_databases_populate';

        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        } else {
            $files = array_merge(
                glob($this->testDir.'/*.sqlite') ?: [],
                glob($this->testDir.'/*.sqlite-wal') ?: [],
                glob($this->testDir.'/*.sqlite-shm') ?: []
            );
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        $this->client = new Client($this->testDir);
        $db = $this->client->selectDB('populate_db');

        $this->users = $db->selectCollection('users');
        $this->products = $db->selectCollection('products');
        $this->orders = $db->selectCollection('orders');

        /* ===== Seed users ===== */
        $this->users->insert([
            ['_id' => 'u1', 'name' => 'Alice'],
            ['_id' => 'u2', 'name' => 'Bob'],
        ]);

        /* ===== Seed products ===== */
        $this->products->insert([
            ['_id' => 'p1', 'name' => 'Laptop'],
            ['_id' => 'p2', 'name' => 'Mouse'],
        ]);

        /* ===== Seed orders ===== */
        $this->orders->insert([
            [
                '_id' => 'o1',
                'user_id' => 'u1',
                'items' => [
                    ['product_id' => 'p1', 'qty' => 1],
                    ['product_id' => 'p2', 'qty' => 2],
                ],
            ],
            [
                '_id' => 'o2',
                'user_id' => 'u2',
                'items' => [
                    ['product_id' => 'p1', 'qty' => 1],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->users = null;
        $this->products = null;
        $this->orders = null;
        $this->client = null;

        \PocketDB\Database::closeAll();
        gc_collect_cycles();

        if (is_dir($this->testDir)) {
            $files = array_merge(
                glob($this->testDir.'/*.sqlite'),
                glob($this->testDir.'/*.sqlite-wal'),
                glob($this->testDir.'/*.sqlite-shm')
            );
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testDir);
        }
    }

    /* =========================================================
     * ======================= TESTS ===========================
     * ========================================================= */

    public function testPopulateSimple()
    {
        $orders = $this->orders
            ->find()
            ->populate('user_id', $this->users)
            ->toArray();

        $this->assertCount(2, $orders);

        $this->assertArrayHasKey('user', $orders[0]);
        $this->assertEquals('Alice', $orders[0]['user']['name']);
    }

    public function testPopulateNested()
    {
        $orders = $this->orders
            ->find(['_id' => 'o1'])
            ->populate('items.product_id', $this->products)
            ->toArray();

        $items = $orders[0]['items'];

        $this->assertArrayHasKey('product', $items[0]);
        $this->assertEquals('Laptop', $items[0]['product']['name']);

        $this->assertArrayHasKey('product', $items[1]);
        $this->assertEquals('Mouse', $items[1]['product']['name']);
    }

    public function testPopulateMany()
    {
        $orders = $this->orders
            ->find()
            ->populateMany([
                'user_id' => $this->users,
                'items.product_id' => $this->products,
            ])
            ->toArray();

        $this->assertEquals('Alice', $orders[0]['user']['name']);
        $this->assertEquals('Laptop', $orders[0]['items'][0]['product']['name']);
    }

    public function testWithAlias()
    {
        $orders = $this->orders
            ->find()
            ->with([
                'user_id' => [
                    $this->users,
                    ['as' => 'customer'],
                ],
            ])
            ->toArray();

        $this->assertArrayHasKey('customer', $orders[0]);
        $this->assertEquals('Alice', $orders[0]['customer']['name']);
    }

    public function testMissingReferenceReturnsNull()
    {
        $this->orders->insert([
            '_id' => 'o3',
            'user_id' => 'ux',
        ]);

        $orders = $this->orders
            ->find(['_id' => 'o3'])
            ->populate('user_id', $this->users)
            ->toArray();

        $this->assertNull($orders[0]['user']);
    }

    public function testBackwardCompatibilityWithoutPopulate()
    {
        $orders = $this->orders->find()->toArray();

        $this->assertArrayHasKey('user_id', $orders[0]);
        $this->assertArrayNotHasKey('user', $orders[0]);
    }
}
