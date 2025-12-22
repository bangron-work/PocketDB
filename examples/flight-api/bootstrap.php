<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config/database.php';

// Initialize PocketDB client
$client = new \PocketDB\Client($config['path'], $config['options']);
$db = $client->selectDB($config['database']);

// Register Flight framework
Flight::set('flight.views.path', __DIR__ . '/views');

// Register database instance
Flight::set('db', $db);

// Register error handler
Flight::map('error', function(Throwable $error) {
    // Log the error
    error_log($error->getMessage());
    
    // Return JSON response
    Flight::json([
        'error' => true,
        'message' => 'Terjadi kesalahan pada server',
        'code' => $error->getCode() ?: 500
    ], 500);
});

// Register 404 handler
Flight::map('notFound', function() {
    Flight::json([
        'error' => true,
        'message' => 'Endpoint tidak ditemukan',
        'code' => 404
    ], 404);
});

// Middleware untuk validasi JSON
Flight::before('start', function() {
    if (Flight::request()->type === 'application/json') {
        $data = json_decode(Flight::request()->getBody(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Flight::json([
                'error' => true,
                'message' => 'Format JSON tidak valid',
                'code' => 400
            ], 400);
            Flight::stop();
        }
        Flight::request()->data = $data;
    }
});
