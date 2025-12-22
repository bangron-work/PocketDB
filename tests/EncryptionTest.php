<?php

use PocketDB\Collection;
use PocketDB\Database;

class EncryptionTest extends PHPUnit\Framework\TestCase
{
    public function testInsertAndReadEncryptedCollection()
    {
        $db = new Database(':memory:');
        $col = $db->selectCollection('users');
        $col->setEncryptionKey('my-secret-key');

        $doc = ['_id' => 'u1', 'email' => 'rony@mail.com', 'name' => 'Rony'];
        $col->insert($doc);

        // Raw stored wrapper should contain encrypted_data and iv
        $stmt = $db->connection->query("SELECT document FROM users WHERE json_extract(document, '$._id') = 'u1'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertStringContainsString('encrypted_data', $row['document']);
        $this->assertStringNotContainsString('Rony', $row['document']);

        // Reading through collection should return decrypted document
        $f = $col->findOne(['_id' => 'u1']);
        $this->assertIsArray($f);
        $this->assertEquals('Rony', $f['name']);
        $this->assertEquals('rony@mail.com', $f['email']);
    }

    public function testSearchableFieldEnabled()
    {
        $db = new Database(':memory:');
        $col = $db->selectCollection('accounts');
        $col->setEncryptionKey('another-key');
        $col->setSearchableFields(['email'], false);

        $doc = ['_id' => 'a1', 'email' => 'alice@example.com', 'name' => 'Alice'];
        $col->insert($doc);

        // The si_email column should store the plain email
        $stmt = $db->connection->query("SELECT si_email FROM accounts WHERE json_extract(document, '$._id') = 'a1'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertEquals('alice@example.com', $row['si_email']);

        // Now we should be able to find by email using normal find()
        $found = $col->find(['email' => 'alice@example.com'])->toArray();
        $this->assertCount(1, $found);
        $this->assertEquals('Alice', $found[0]['name']);
    }
}
