<?php
declare(strict_types=1);

/**
 * Robust MySQL connector for cPanel/shared hosting:
 * - Prefer UNIX socket when host == 'localhost' (avoids TCP “connection refused”)
 * - Fallback to TCP (127.0.0.1:port)
 * - Throws on errors, sets utf8mb4
 * - Returns mysqli instance
 */

$config = require __DIR__ . '/../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host   = (string)($config['db_host']   ?? 'localhost');
$user   = (string)($config['db_user']   ?? '');
$pass   = (string)($config['db_pass']   ?? '');
$db     = (string)($config['db_name']   ?? '');
$port   = (int)   ($config['db_port']   ?? 3306);
$socket = $config['db_socket'] ?? null; // string|null

// If using 'localhost' and no explicit socket, try to auto-detect.
if ($host === 'localhost' && !$socket) {
  $socket = ini_get('mysqli.default_socket') ?: null;
  if (!$socket) {
    foreach (['/var/lib/mysql/mysql.sock', '/tmp/mysql.sock'] as $guess) {
      if (@is_readable($guess)) { $socket = $guess; break; }
    }
  }
}

try {
  if ($host === 'localhost') {
    // Socket-first
    $mysqli = new mysqli('localhost', $user, $pass, $db, 0, $socket ?: null);
  } else {
    // Explicit TCP
    $mysqli = new mysqli($host, $user, $pass, $db, $port);
  }
  $mysqli->set_charset('utf8mb4');

  /* Back-compat for legacy code expecting $conn */
  if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = $mysqli;
  }

  return $mysqli;
} catch (mysqli_sql_exception $e) {
  error_log(sprintf('[db.php] Connect failed: %s | host=%s port=%d socket=%s',
    $e->getMessage(), $host, $port, $socket ?: '(none)'
  ));
  throw $e;
}
