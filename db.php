<?php
$config = require __DIR__ . '/config.php';

$conn = new mysqli(
  $config['db_host'],
  $config['db_user'],
  $config['db_pass'],
  $config['db_name']
);

if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}
?>
