<?php

require_once __DIR__ . '/../../bootstrap.php';

// Register routes
Flight::route('GET /api/todos', ['App\Controllers\TodoController', 'index']);
Flight::route('GET /api/todos/@id', ['App\Controllers\TodoController', 'show']);
Flight::route('POST /api/todos', ['App\Controllers\TodoController', 'store']);
Flight::route('PUT /api/todos/@id', ['App\Controllers\TodoController', 'update']);
Flight::route('PATCH /api/todos/@id/complete', ['App\Controllers\TodoController', 'toggleComplete']);
Flight::route('DELETE /api/todos/@id', ['App\Controllers\TodoController', 'destroy']);

// Root route
Flight::route('GET /', function() {
    Flight::json([
        'name' => 'PocketDB Flight API',
        'version' => '1.0.0',
        'documentation' => '/api-docs'
    ]);
});

// Start the application
Flight::start();
