<?php
require 'includes/csrf.php';
require 'includes/db.php';
require 'includes/totp.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$maxAttempts = 5;
$lockout = 300; // seconds
$twofaStage = !empty($_SESSION['pending_2fa']);

// Clean up old login attempts and count recent failures for this IP
$conn->query("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL $lockout SECOND)");
$attempts = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempt_time > (NOW() - INTERVAL ? SECOND)");
if ($stmt === false) {
  error_log('Prepare failed for login_attempts: ' . $conn->error);
  if ($conn->errno === 1146) {
    $error = "Login is temporarily unavailable. Please contact support.";
  } else {
    $error = "Database error.";
  }
} else {
  $stmt->bind_param('si', $ip, $lockout);
  if ($stmt->execute()) {
    $stmt->bind_result($attempts);
    $stmt->fetch();
  } else {
    error_log('Execute failed for login_attempts: ' . $stmt->error);
    $error = "Database error.";
  }
  $stmt->close();
  if ($attempts >= $maxAttempts) {
    $error = "Too many failed login attempts. Please try again later.";
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = "Invalid CSRF token.";
  } elseif ($twofaStage) {
    $code = trim($_POST['code'] ?? '');
    $pendingId = $_SESSION['pending_2fa'];
    $stmt = $conn->prepare("SELECT secret, recovery_code FROM user_2fa WHERE user_id = ?");
    if ($stmt === false) {
      error_log('Prepare failed for user_2fa: ' . $conn->error);
      if ($conn->errno === 1146) {
        $error = "Two-factor authentication is not available. Please contact support.";
      } else {
        $error = "Database error.";
      }
    } else {
      $stmt->bind_param('i', $pendingId);
      if ($stmt->execute()) {
        $stmt->bind_result($secret, $recovery);
        $stmt->fetch();
        $stmt->close();
        if (verify_totp($secret, $code) || hash_equals($recovery, $code)) {
          $_SESSION['user_id'] = $pendingId;
          $_SESSION['is_admin'] = $_SESSION['pending_admin'] ?? 0;
          unset($_SESSION['pending_2fa'], $_SESSION['pending_admin']);
          $clear = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
          if ($clear) {
            $clear->bind_param('s', $ip);
            $clear->execute();
            $clear->close();
          }
          header("Location: dashboard.php");
          exit;
        } else {
          $error = "Invalid authentication code.";
        }
      } else {
        error_log('Execute failed for user_2fa: ' . $stmt->error);
        $error = "Unable to verify two-factor authentication at this time.";
        $stmt->close();
      }
    }
  } else {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, is_verified, is_admin FROM users WHERE username = ?");
    if ($stmt === false) {
      error_log('Prepare failed: ' . $conn->error);
      $error = "Database error.";
    } else {
      $stmt->bind_param("s", $user);
      if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        $error = "Database error.";
      } else {
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
          $stmt->bind_result($id, $hash, $verified, $admin);
          $stmt->fetch();

          if (!password_verify($pass, $hash)) {
            $error = "Incorrect password.";
            $insert = $conn->prepare("INSERT INTO login_attempts (ip, attempt_time) VALUES (?, NOW())");
            if ($insert) {
              $insert->bind_param('s', $ip);
              if (!$insert->execute()) {
                error_log('Execute failed for login_attempts insert: ' . $insert->error);
              }
              $insert->close();
            }
            $attempts++;
            error_log("Failed login for $user from $ip");
            if ($attempts >= $maxAttempts) {
              error_log("IP $ip temporarily blocked after $attempts failed logins");
            }
          } elseif (!$verified) {
            $error = "Please verify your email first.";
          } else {
            $stmt2 = $conn->prepare("SELECT secret FROM user_2fa WHERE user_id = ?");
            if ($stmt2) {
              $stmt2->bind_param('i', $id);
              $stmt2->execute();
              $stmt2->store_result();
              if ($stmt2->num_rows === 1) {
                $_SESSION['pending_2fa'] = $id;
                $_SESSION['pending_admin'] = $admin;
                $twofaStage = true;
              } else {
                $clear = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
                if ($clear) {
                  $clear->bind_param('s', $ip);
                  $clear->execute();
                  $clear->close();
                }
                $_SESSION['user_id'] = $id;
                $_SESSION['is_admin'] = $admin;
                header("Location: dashboard.php");
                exit;
              }
              $stmt2->close();
            }
          }
        } else {
          $error = "User not found.";
          $insert = $conn->prepare("INSERT INTO login_attempts (ip, attempt_time) VALUES (?, NOW())");
          if ($insert) {
            $insert->bind_param('s', $ip);
            if (!$insert->execute()) {
              error_log('Execute failed for login_attempts insert: ' . $insert->error);
            }
            $insert->close();
          }
          $attempts++;
          error_log("Failed login for $user from $ip");
          if ($attempts >= $maxAttempts) {
            error_log("IP $ip temporarily blocked after $attempts failed logins");
          }
        }
      }
      $stmt->close();
    }
  }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Login</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Login</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>

  <?php if ($twofaStage): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="text" name="code" required placeholder="2FA Code">
      <button type="submit">Verify</button>
    </form>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="text" name="username" required placeholder="Username">
      <input type="password" name="password" required placeholder="Password">
      <button type="submit">Login</button>
    </form>
    <p><a href="forgot.php">Forgot Password?</a> | <a href="register.php">Register</a></p>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
