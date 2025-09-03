<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require_once 'mail.php';

$success = false;
$error = '';
$filename = null;
$type = htmlspecialchars(trim($_POST['type'] ?? 'service'), ENT_QUOTES, 'UTF-8');

$user_id = $_SESSION['user_id'];
$is_vip = false;
if ($stmtVip = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id=?')) {
    $stmtVip->bind_param('i', $user_id);
    $stmtVip->execute();
    $stmtVip->bind_result($vipStatus, $vipExpires);
    if ($stmtVip->fetch()) {
        $is_vip = $vipStatus && (!$vipExpires || strtotime($vipExpires) > time());
    }
    $stmtVip->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $category   = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
        $brand_id   = isset($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $model_id   = isset($_POST['model_id']) ? (int)$_POST['model_id'] : null;
        $make       = isset($_POST['make']) ? htmlspecialchars(trim($_POST['make']), ENT_QUOTES, 'UTF-8') : null;
        $model      = isset($_POST['model']) ? htmlspecialchars(trim($_POST['model']), ENT_QUOTES, 'UTF-8') : null;
        $serial     = isset($_POST['serial']) ? htmlspecialchars(trim($_POST['serial']), ENT_QUOTES, 'UTF-8') : null;
        $issue      = htmlspecialchars(trim($_POST['issue'] ?? ''), ENT_QUOTES, 'UTF-8');
        $build      = htmlspecialchars(trim($_POST['build'] ?? 'no'), ENT_QUOTES, 'UTF-8');
        $device_type= isset($_POST['device_type']) ? htmlspecialchars(trim($_POST['device_type']), ENT_QUOTES, 'UTF-8') : null;

        if ($category === '' || $issue === '' || !$brand_id || !$model_id) {
          $error = 'Category, brand and model are required.';
        }

        if (!$error) {
          if ($stmtB = $conn->prepare('SELECT id FROM service_brands WHERE id=?')) {
            $stmtB->bind_param('i', $brand_id);
            $stmtB->execute();
            $stmtB->store_result();
            if ($stmtB->num_rows === 0) {
              $error = 'Invalid brand.';
            }
            $stmtB->close();
          }
        }

        if (!$error) {
          if ($stmtM = $conn->prepare('SELECT id FROM service_models WHERE id=? AND brand_id=?')) {
            $stmtM->bind_param('ii', $model_id, $brand_id);
            $stmtM->execute();
            $stmtM->store_result();
            if ($stmtM->num_rows === 0) {
              $error = 'Invalid model.';
            }
            $stmtM->close();
          }
        }

        // ✅ Handle optional file upload
        if (!$error && !empty($_FILES['photo']['name'])) {
          $upload_path = __DIR__ . '/uploads/';
          if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
          }
          $maxSize = 5 * 1024 * 1024; // 5MB
          $allowed = ['image/jpeg', 'image/png'];

          if ($_FILES['photo']['size'] > $maxSize) {
            $error = "Image exceeds 5MB limit.";
          } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed)) {
              $error = "Only JPEG and PNG images allowed.";
            } else {
              $ext = $mime === 'image/png' ? '.png' : '.jpg';
              $filename = uniqid('upload_', true) . $ext;
              $target = $upload_path . $filename;
              if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                $error = "Failed to upload image.";
              }
            }
          }
        }

        // Proceed if no file errors
        if (!$error) {
          $status = $is_vip ? 'In Progress' : 'New';
          $stmt = $conn->prepare("INSERT INTO service_requests
            (user_id, type, category, brand_id, model_id, make, model, serial, issue, build, device_type, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

          if ($stmt) {
            $stmt->bind_param("issiiisssssss", $user_id, $type, $category, $brand_id, $model_id, $make, $model, $serial, $issue, $build, $device_type, $filename, $status);
            if ($stmt->execute()) {
              $success = true;

              // ✅ Send admin notification
              $adminEmail = 'owner@skuze.tech';
              $subject = "New Service Request Submitted";
              $body = "User ID: $user_id\nType: $type\nCategory: $category\nMake/Model: $make $model\nSerial: $serial\nBuild Request: $build\nDevice Type: $device_type\nIssue: $issue";
              if ($filename) {
                $body .= "\nPhoto stored at: uploads/$filename";
              }
              try {
                send_email($adminEmail, $subject, $body);
              } catch (Exception $e) {
                error_log('Email dispatch failed: ' . $e->getMessage());
                $error = 'Request saved but email notification failed.';
              }
            } else {
              error_log('Execute failed: ' . $stmt->error);
              $error = "Error executing query.";
            }
            $stmt->close();
          } else {
            error_log('Prepare failed: ' . $conn->error);
            $error = "Database error.";
          }
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Request Submitted</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <?php if ($success): ?>
    <h2>Request Submitted</h2>
    <p>Thank you! We'll review your request and get back to you shortly.</p>
    <p><a href="dashboard.php">Back to Dashboard</a></p>
  <?php else: ?>
    <h2>Error</h2>
    <p><?= htmlspecialchars($error) ?></p>
    <?php $map = ['buy' => 'buy.php', 'sell' => 'sell.php', 'trade' => 'trade.php', 'service' => 'services.php'];
          $origin = $map[$type] ?? 'services.php'; ?>
    <p><a href="<?= $origin ?>">Try Again</a></p>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
