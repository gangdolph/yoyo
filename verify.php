<?php
require 'includes/db.php';

$token = $_GET['token'] ?? '';
if ($token) {
  $stmt = $conn->prepare("SELECT user_id, expires_at FROM tokens WHERE token = ? AND type = 'verify'");
  if ($stmt === false) {
    error_log('Prepare failed: ' . $conn->error);
    $msg = "Database error.";
  } else {
    $stmt->bind_param("s", $token);
    if (!$stmt->execute()) {
      error_log('Execute failed: ' . $stmt->error);
      $msg = "Database error.";
      $stmt->close();
    } else {
      $stmt->store_result();
      if ($stmt->num_rows === 1) {
        $stmt->bind_result($uid, $exp);
        $stmt->fetch();
        $stmt->close();
        if (strtotime($exp) > time()) {
          $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
          if ($update === false) {
            error_log('Prepare failed (update user): ' . $conn->error);
            $msg = "Could not verify email. Please try again later.";
          } else {
            $update->bind_param("i", $uid);
            if (!$update->execute()) {
              error_log('Execute failed (update user): ' . $update->error);
              $msg = "Could not verify email. Please try again later.";
            } else {
              $update->close();
              $delete = $conn->prepare("DELETE FROM tokens WHERE user_id = ? AND token = ? AND type = 'verify'");
              if ($delete === false) {
                error_log('Prepare failed (delete token): ' . $conn->error);
                $msg = "Could not verify email. Please try again later.";
              } else {
                $delete->bind_param("is", $uid, $token);
                if (!$delete->execute()) {
                  error_log('Execute failed (delete token): ' . $delete->error);
                  $msg = "Could not verify email. Please try again later.";
                } else {
                  $msg = "Email verified! You can now login.";
                }
                $delete->close();
              }
            }
          }
        } else {
          $msg = "Token expired.";
        }
      } else {
        $msg = "Invalid token.";
        $stmt->close();
      }
    }
  }
} else {
  $msg = "No token provided.";
}
?>
<?php require 'includes/layout.php'; ?>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Email Verification</h2>
  <p><?= $msg ?></p>
  <p><a href="login.php">Login</a></p>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
