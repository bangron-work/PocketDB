<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PocketDB\Client;

class AttachTest extends TestCase
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
        // cleanup files
        $files = glob($this->testDir . '/*.sqlite');
        foreach ($files as $f) @unlink($f);
        @rmdir($this->testDir);
    }

    public function testAttachAndJoin()
    {
        // Create base DB with users
        $base = $this->client->selectDB('base');
        $users = $base->selectCollection('users');
        $uid = $users->insert(['_id' => 'u1', 'name' => 'Alice']);

        // Create ecommerce DB with orders referencing user id
        $ecom = $this->client->selectDB('ecommerce');
        $orders = $ecom->selectCollection('orders');
        $orders->insert(['_id' => 'o1', 'user_id' => 'u1', 'total' => 10]);

        // Attach base DB into ecommerce connection
        $path = $this->testDir . '/base.sqlite';
        $attached = $ecom->attach($path, 'base_alias');
        $this->assertTrue($attached);

        // Run join query via connection
        $table = $orders->name;
        $sql = "SELECT a.document as order_doc, b.document as user_doc FROM {$table} a JOIN base_alias.users b ON json_extract(a.document, '$.user_id') = json_extract(b.document, '$._id')";
        $stmt = $ecom->connection->query($sql);
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $this->assertCount(1, $rows);

        // Detach
        $detached = $ecom->detach('base_alias');
        $this->assertTrue($detached);
    }
}
