<?php

require __DIR__.'/../vendor/autoload.php';

use PocketDB\Database;

// Simple example showing collection-level encryption + searchable field
$db = new Database(':memory:');
$col = $db->selectCollection('users');
$col->setEncryptionKey('example-key');
$col->setSearchableFields(['email'], false);

$col->insert(['_id' => 'u100', 'email' => 'bob@example.com', 'name' => 'Bob']);

// Raw stored value (shows encrypted_data wrapper)
$stmt = $db->connection->query("SELECT document, si_email FROM users WHERE json_extract(document, '$._id') = 'u100'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Raw stored document: \n".$row['document']."\n\n";
echo 'Searchable email column: '.$row['si_email']."\n\n";

// Read via API -> decrypted
$doc = $col->findOne(['_id' => 'u100']);
echo 'Decrypted: '.json_encode($doc, JSON_UNESCAPED_UNICODE)."\n";
