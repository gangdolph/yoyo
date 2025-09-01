<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

// Optional: update last_active for online tracking
$conn->query("UPDATE users SET last_active = NOW() WHERE id = " . intval($_SESSION['user_id']));
?>
