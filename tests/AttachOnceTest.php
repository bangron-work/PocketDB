<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;

class AttachOnceTest extends TestCase
{
    private Client $client;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/test_databases';
        if (!is_dir($this->testDir)) mkdir($this->testDir, 0777, true);

        $this->client = new Client($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->client->close();
        \PocketDB\Database::closeAll();
        $files = glob($this->testDir . '/*.sqlite');
        foreach ($files as $f) @unlink($f);
        @rmdir($this->testDir);
    }

    public function testAttachOnceNormal()
    {
        $base = $this->client->selectDB('base');
        $users = $base->selectCollection('users');
        $users->insert(['_id' => 'u1', 'name' => 'Bob']);

        $ecom = $this->client->selectDB('ecommerce');
        $orders = $ecom->selectCollection('orders');
        $orders->insert(['_id' => 'o1', 'user_id' => 'u1', 'total' => 20]);

        $path = $this->testDir . '/base.sqlite';

        $result = $ecom->attachOnce($path, 'base_tmp', function ($db, $alias) use ($ecom) {
            $table = $ecom->selectCollection('orders')->name;
            $sql = "SELECT a.document as order_doc, b.document as user_doc FROM {$table} a JOIN {$alias}.users b ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')";
            $stmt = $ecom->connection->query($sql);
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return count($rows);
        });

        $this->assertEquals(1, $result);
        // ensure alias detached by attempting to attach again
        $attachedAgain = $ecom->attach($path, 'base_tmp');
        $this->assertTrue($attachedAgain);
        $ecom->detach('base_tmp');
    }

    public function testAttachOnceExceptionDetaches()
    {
        $base = $this->client->selectDB('base');
        $users = $base->selectCollection('users');
        $users->insert(['_id' => 'u2', 'name' => 'Eve']);

        $ecom = $this->client->selectDB('ecommerce');

        $path = $this->testDir . '/base.sqlite';

        $this->expectException(\RuntimeException::class);

        try {
            $ecom->attachOnce($path, 'base_x', function () {
                throw new \RuntimeException('callback failed');
            });
        } finally {
            // after exception, attaching same alias again should succeed (meaning detach happened)
            $this->assertTrue($ecom->attach($path, 'base_x'));
            $ecom->detach('base_x');
        }
    }
}
