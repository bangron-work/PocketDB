<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure any open database connections are closed before removing files
if (class_exists('\PocketDB\Database')) {
    \PocketDB\Database::closeAll();
}

// Clean up any existing test databases (cross-platform)
$testDirs = [
    __DIR__ . '/../test_databases',
    __DIR__ . '/test_databases'
];

foreach ($testDirs as $dir) {
    if (is_dir($dir)) {
        // Remove all files in the directory (including -wal and -shm)
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                // attempt unlink, ignore errors
                @unlink($file);
            }
        }

        // Attempt to remove directory if empty
        @rmdir($dir);
    }
}

// Create test databases directory
$testDir = __DIR__ . '/test_databases';
if (!is_dir($testDir)) {
    mkdir($testDir, 0777, true);
}
