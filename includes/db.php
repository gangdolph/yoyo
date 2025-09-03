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
        'db_port' => getenv('DB_PORT'),
    ];
}

// Legacy "host:port" strings are split so port can be passed separately.
$host = $config['db_host'] ?? '';
$port = $config['db_port'] ?? null;
if (strpos($host, ':') !== false) {
    [$host, $port] = explode(':', $host, 2);
}
$host = $host ?: '127.0.0.1';
$port = (int) ($port ?: 3306);

// Create the database connection using config values.
$conn = new mysqli(
    $host,
    $config['db_user'] ?? '',
    $config['db_pass'] ?? '',
    $config['db_name'] ?? '',
    $port
);

// Abort immediately if the connection fails.
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
$conn->query("SET sql_mode=''");

?>
