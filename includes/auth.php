<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$db = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
  // Redirect to the login page at the site root
  header("Location: /login.php");
  exit;
}

// Optional: update last_active for online tracking
if ($db instanceof mysqli) {
  $db->query("UPDATE users SET last_active = NOW() WHERE id = " . intval($_SESSION['user_id']));
}
?>
