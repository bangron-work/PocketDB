<?php

use PocketDB\Database;

class HashedSearchableFieldTest extends PHPUnit\Framework\TestCase
{
    public function testHashedSearchableFieldMatchesPlainCriteria()
    {
        $db = new Database(':memory:');
        $col = $db->selectCollection('secure_accounts');
        $col->setEncryptionKey('k');
        $col->setSearchableFields(['email'], true); // hashed

        $doc = ['_id' => 's1', 'email' => 'secret@example.com', 'name' => 'Secret'];
        $col->insert($doc);

        // si_email should be the sha256 of the email
        $stmt = $db->connection->query("SELECT si_email FROM secure_accounts WHERE json_extract(document, '$._id') = 's1'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertEquals(hash('sha256', 'secret@example.com'), $row['si_email']);

        // Searching with plain email should match because criteria is hashed internally
        $found = $col->find(['email' => 'secret@example.com'])->toArray();
        $this->assertCount(1, $found);
        $this->assertEquals('Secret', $found[0]['name']);
    }

    public function testRemoveSearchableFieldDropsColumnWhenRequested()
    {
        $db = new Database(':memory:');
        $col = $db->selectCollection('to_remove');
        $col->setSearchableFields(['email'], false);

        $col->insert(['_id' => 'r1', 'email' => 'a@b', 'name' => 'A']);

        // column exists
        $stmt = $db->connection->query("PRAGMA table_info('to_remove')");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        $this->assertContains('si_email', $names);

        // remove and drop column
        $col->removeSearchableField('email', true);

        $stmt = $db->connection->query("PRAGMA table_info('to_remove')");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        $this->assertNotContains('si_email', $names);
    }
}
