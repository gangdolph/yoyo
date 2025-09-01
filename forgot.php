<?php
require 'includes/csrf.php';
require 'includes/db.php';
// Mail helper lives in the project root
require 'mail.php';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
      $msg = "Invalid CSRF token.";
    } else {
    $email = trim($_POST['email']);

  $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
  if ($stmt === false) {
    error_log('Prepare failed: ' . $conn->error);
    $msg = "Database error.";
  } else {
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
      error_log('Execute failed: ' . $stmt->error);
      $msg = "Database error.";
      $stmt->close();
    } else {
      $stmt->store_result();

      if ($stmt->num_rows === 1) {
        $stmt->bind_result($uid);
        $stmt->fetch();
        $stmt->close();

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $conn->prepare("INSERT INTO tokens (user_id, token, type, expires_at) VALUES (?, ?, 'reset', ?)");
        if ($stmt === false) {
          error_log('Prepare failed: ' . $conn->error);
          $msg = "Database error.";
        } else {
          $stmt->bind_param("iss", $uid, $token, $expires);
          if (!$stmt->execute()) {
            error_log('Execute failed: ' . $stmt->error);
            $msg = "Database error.";
          } else {
            $link = "https://skuze.tech/reset.php?token=$token";
            $body = "To reset your password, visit: $link\nThis link expires in 1 hour.";
            try {
              send_email($email, "SkuzE Password Reset", $body);
              $msg = "Reset link sent if account exists.";
            } catch (Exception $e) {
              error_log('Email dispatch failed: ' . $e->getMessage());
              $msg = "Failed to send reset email.";
            }
          }
          $stmt->close();
        }
      } else {
        $msg = "Reset link sent if account exists.";
        $stmt->close();
      }
    }
  }
  }
}
?>
<?php require 'includes/layout.php'; ?>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Forgot Password</h2>
  <?php if (!empty($msg)) echo "<p>" . htmlspecialchars($msg) . "</p>"; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="email" name="email" required placeholder="Your email">
    <button type="submit">Send Reset Link</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
