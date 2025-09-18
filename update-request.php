<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: trade.php');
  exit;
}

if (!validate_token($_POST['csrf_token'] ?? '')) {
  die('Invalid CSRF token');
}

$user_id = $_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);
$make = htmlspecialchars(trim($_POST['make'] ?? ''), ENT_QUOTES, 'UTF-8');
$model = htmlspecialchars(trim($_POST['model'] ?? ''), ENT_QUOTES, 'UTF-8');
$device_type = htmlspecialchars(trim($_POST['device_type'] ?? ''), ENT_QUOTES, 'UTF-8');
$issue = htmlspecialchars(trim($_POST['issue'] ?? ''), ENT_QUOTES, 'UTF-8');
$filename = null;

if (!$id || $make === '' || $model === '' || $device_type === '' || $issue === '') {
  die('Missing required fields');
}

if (!empty($_FILES['photo']['name'])) {
  $upload_path = __DIR__ . '/uploads/';
  if (!is_dir($upload_path)) {
    mkdir($upload_path, 0755, true);
  }
  $maxSize = 5 * 1024 * 1024;
  $allowed = ['image/jpeg', 'image/png'];
  if ($_FILES['photo']['size'] <= $maxSize) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);
    if (in_array($mime, $allowed)) {
      $ext = $mime === 'image/png' ? '.png' : '.jpg';
      $filename = uniqid('upload_', true) . $ext;
      move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path . $filename);
    }
  }
}

$stmt = $conn->prepare("UPDATE service_requests SET make=?, model=?, device_type=?, issue=?, photo=IFNULL(?, photo) WHERE id=? AND user_id=? AND type='trade' AND status IN ('New','Pending')");
if ($stmt) {
  $stmt->bind_param("sssssii", $make, $model, $device_type, $issue, $filename, $id, $user_id);
  $stmt->execute();
  $stmt->close();
}

header('Location: trade.php');
exit;
