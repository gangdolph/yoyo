<?php
require 'includes/csrf.php';
require 'includes/db.php';

$token = $_GET['token'] ?? '';
$valid = false;

if ($token) {
  $stmt = $conn->prepare("SELECT user_id, expires_at FROM tokens WHERE token = ? AND type = 'reset'");
  if ($stmt === false) {
    error_log('Prepare failed: ' . $conn->error);
    $error = "Database error.";
  } else {
    $stmt->bind_param("s", $token);
    if (!$stmt->execute()) {
      error_log('Execute failed: ' . $stmt->error);
      $error = "Database error.";
      $stmt->close();
    } else {
      $stmt->store_result();

      if ($stmt->num_rows === 1) {
        $stmt->bind_result($uid, $expires);
        $stmt->fetch();
        if (strtotime($expires) > time()) {
          $valid = true;
        } else {
          $error = "Reset token expired.";
        }
      } else {
        $error = "Invalid token.";
      }
      $stmt->close();
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
      if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
      } else {
      $newpass = $_POST['new_password'];
      $confirm = $_POST['confirm_password'];

      if ($newpass !== $confirm) {
        $error = "Passwords do not match.";
      } elseif (strlen($newpass) < 6) {
        $error = "Password too short.";
      } else {
          $hash = password_hash($newpass, PASSWORD_DEFAULT);
          $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
          if ($stmt === false) {
            error_log('Prepare failed: ' . $conn->error);
            $error = "Database error.";
          } else {
            $stmt->bind_param("si", $hash, $uid);
            if (!$stmt->execute()) {
              error_log('Execute failed: ' . $stmt->error);
              $error = "Database error.";
              $stmt->close();
            } else {
              $stmt->close();
              $stmt = $conn->prepare("DELETE FROM tokens WHERE token = ?");
              if ($stmt === false) {
                error_log('Prepare failed: ' . $conn->error);
                $error = "Database error.";
              } else {
                $stmt->bind_param("s", $token);
                if (!$stmt->execute()) {
                  error_log('Execute failed: ' . $stmt->error);
                  $error = "Database error.";
                } else {
                  $msg = "Password reset. You may now <a href='login.php'>login</a>.";
                  $valid = false;
                }
                $stmt->close();
              }
            }
          }
        }
      }
    }
} else {
  $error = "No token provided.";
}
?>
<?php require 'includes/layout.php'; ?>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Reset Password</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
    <?php if (!empty($msg)) echo "<p style='color:green;'>" . htmlspecialchars($msg) . "</p>"; ?>

    <?php if ($valid): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="password" name="new_password" required placeholder="New password">
      <input type="password" name="confirm_password" required placeholder="Confirm new password">
      <button type="submit">Reset Password</button>
    </form>
    <?php endif; ?>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
