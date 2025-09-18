<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
  }
  $id = (int)($_POST['id'] ?? 0);
  $user_id = $_SESSION['user_id'];
  if ($id) {
    $stmt = $conn->prepare("UPDATE service_requests SET status='Canceled' WHERE id=? AND user_id=? AND type='trade' AND status IN ('New','Pending')");
    if ($stmt) {
      $stmt->bind_param("ii", $id, $user_id);
      $stmt->execute();
      $stmt->close();
    }
  }
}
header('Location: trade.php');
exit;
