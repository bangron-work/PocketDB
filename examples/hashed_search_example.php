<?php
/**
 * Example demonstrating hashed searchable fields.
 *
 * Run:
 *   php examples/hashed_search_example.php
 */

require __DIR__.'/../vendor/autoload.php';

use PocketDB\Database;

$db = new Database(':memory:');
$col = $db->selectCollection('secure_users');
$col->setEncryptionKey('example-key');
// store only a hash of email for searching
$col->setSearchableFields(['email'], true);

$col->insert(['_id' => 'h1', 'email' => 'hashme@example.com', 'name' => 'Hashed']);

// raw si_email value is a sha256
$row = $db->connection->query("SELECT si_email, document FROM secure_users WHERE json_extract(document, '$._id') = 'h1'")->fetch(PDO::FETCH_ASSOC);
echo 'si_email stored: '.$row['si_email']."\n";

// search by plain email should work
$found = $col->find(['email' => 'hashme@example.com'])->toArray();
print_r($found);
