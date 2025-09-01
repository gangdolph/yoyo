<?php
/**
 * Centralised database connection.
 *
 * Loads credentials from the application config or environment
 * and exposes an open `mysqli` instance via `$conn`.
 */

// Attempt to load configuration file (../config.php relative to this file)
$configPath = __DIR__ . '/../config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    $config = [
        'db_host' => getenv('DB_HOST'),
        'db_user' => getenv('DB_USER'),
        'db_pass' => getenv('DB_PASS'),
        'db_name' => getenv('DB_NAME'),
    ];
}

// Create the database connection using config values.
$conn = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name']
);

// Abort immediately if the connection fails.
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

?>
