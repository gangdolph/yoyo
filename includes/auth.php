<?php
session_start();
// Ensure the database connection file is loaded relative to this directory
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
  // Redirect to the login page at the site root
  header("Location: /login.php");
  exit;
}

// Optional: update last_active for online tracking
$conn->query("UPDATE users SET last_active = NOW() WHERE id = " . intval($_SESSION['user_id']));
?>
