<?php

return [
    'path' => __DIR__ . '/../../data', // Direktori penyimpanan database
    'database' => 'flight_api',        // Nama database
    'options' => [
        // Opsi koneksi PDO
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
