<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/auth.php';

if (!defined('HEADER_SKIP_AUTH')) {
  define('HEADER_SKIP_AUTH', true);
}

auth_bootstrap(false);
send_no_store_headers();

if (is_authenticated()) {
  header('Location: /dashboard.php');
  exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$maxAttempts = 5;
$lockout = 600; // seconds
$twofaStage = !empty($_SESSION['pending_2fa']);
$error = '';
$now = time();

$sessionKey = 'login_failures';
if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
  $_SESSION[$sessionKey] = [];
}
$sessionAttempts = $_SESSION[$sessionKey][$ip] ?? [];
$sessionAttempts = array_values(array_filter($sessionAttempts, static function ($ts) use ($now, $lockout) {
  return ($now - (int) $ts) < $lockout;
}));
$_SESSION[$sessionKey][$ip] = $sessionAttempts;
$sessionFailureCount = count($sessionAttempts);

$dbFailures = 0;
try {
  $conn->query("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL $lockout SECOND)");
  if ($stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempt_time > (NOW() - INTERVAL ? SECOND)")) {
    $stmt->bind_param('si', $ip, $lockout);
    if ($stmt->execute()) {
      $stmt->bind_result($dbFailures);
      $stmt->fetch();
    }
    $stmt->close();
  }
} catch (Throwable $e) {
  error_log('Login throttle query failed: ' . $e->getMessage());
}

$aggregateFailures = max($dbFailures, $sessionFailureCount);

$throttleLogKey = 'login_throttle_notified';
if (!isset($_SESSION[$throttleLogKey]) || !is_array($_SESSION[$throttleLogKey])) {
  $_SESSION[$throttleLogKey] = [];
}
$logThrottle = static function (int $count, string $username = '') use ($ip, $throttleLogKey, $now) {
  if (empty($_SESSION[$throttleLogKey][$ip])) {
    $username = trim(preg_replace('/\s+/', ' ', $username));
    $userNote = $username !== '' ? ' username=' . $username : '';
    log_security_event(sprintf('Login throttled for %s after %d failures%s', $ip, $count, $userNote));
    $_SESSION[$throttleLogKey][$ip] = $now;
  }
};

$recordFailure = function () use (&$sessionAttempts, $sessionKey, $ip, $now, $lockout, &$sessionFailureCount, &$aggregateFailures, &$dbFailures) {
  $sessionAttempts[] = $now;
  $sessionAttempts = array_values(array_filter($sessionAttempts, static function ($ts) use ($now, $lockout) {
    return ($now - (int) $ts) < $lockout;
  }));
  $_SESSION[$sessionKey][$ip] = $sessionAttempts;
  $sessionFailureCount = count($sessionAttempts);
  $aggregateFailures = max($dbFailures + 1, $sessionFailureCount);
  $dbFailures = max($dbFailures, $aggregateFailures);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($aggregateFailures >= $maxAttempts && !$twofaStage) {
    sleep(2);
  }

  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } elseif ($twofaStage) {
    $code = trim((string) ($_POST['code'] ?? ''));
    $pendingId = (int) ($_SESSION['pending_2fa'] ?? 0);
    $stmt = $conn->prepare('SELECT secret, recovery_code FROM user_2fa WHERE user_id = ?');
    if ($stmt === false) {
      error_log('Prepare failed for user_2fa: ' . $conn->error);
      $error = $conn->errno === 1146
        ? 'Two-factor authentication is not available. Please contact support.'
        : 'Database error.';
    } else {
      $stmt->bind_param('i', $pendingId);
      if ($stmt->execute()) {
        $stmt->bind_result($secret, $recovery);
        $stmt->fetch();
        $stmt->close();
        if (verify_totp($secret, $code) || hash_equals((string) $recovery, $code)) {
          $_SESSION['user_id'] = $pendingId;
          $_SESSION['user_role'] = $_SESSION['pending_role'] ?? 'user';
          $_SESSION['is_admin'] = ($_SESSION['user_role'] ?? 'user') === 'admin' ? 1 : 0;
          unset($_SESSION['pending_2fa'], $_SESSION['pending_role']);
          if ($clear = $conn->prepare('DELETE FROM login_attempts WHERE ip = ?')) {
            $clear->bind_param('s', $ip);
            $clear->execute();
            $clear->close();
          }
          $_SESSION[$sessionKey][$ip] = [];
          unset($_SESSION[$throttleLogKey][$ip]);
          header('Location: dashboard.php');
          exit;
        }

        $error = 'Invalid authentication code.';
      } else {
        error_log('Execute failed for user_2fa: ' . $stmt->error);
        $error = 'Unable to verify two-factor authentication at this time.';
        $stmt->close();
      }
    }
  } elseif ($aggregateFailures >= $maxAttempts) {
    $error = 'Too many failed login attempts. Please try again in a few minutes.';
    $logThrottle($aggregateFailures, (string) ($_POST['username'] ?? ''));
  } else {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    $stmt = $conn->prepare('SELECT id, password, is_verified, role FROM users WHERE username = ?');
    if ($stmt === false) {
      error_log('Prepare failed: ' . $conn->error);
      $error = 'Database error.';
    } else {
      $stmt->bind_param('s', $user);
      if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        $error = 'Database error.';
      } else {
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
          $stmt->bind_result($id, $hash, $verified, $role);
          $stmt->fetch();

          if (!password_verify($pass, (string) $hash)) {
            $error = 'Incorrect username or password.';
            if ($insert = $conn->prepare('INSERT INTO login_attempts (ip, attempt_time) VALUES (?, NOW())')) {
              $insert->bind_param('s', $ip);
              $insert->execute();
              $insert->close();
            }
            $recordFailure();
            if ($aggregateFailures >= $maxAttempts) {
              $logThrottle($aggregateFailures, $user);
            }
          } elseif (!(int) $verified) {
            $error = 'Please verify your email first.';
          } else {
            if ($stmt2 = $conn->prepare('SELECT secret FROM user_2fa WHERE user_id = ?')) {
              $stmt2->bind_param('i', $id);
              $stmt2->execute();
              $stmt2->store_result();
              if ($stmt2->num_rows === 1) {
                $_SESSION['pending_2fa'] = $id;
                $_SESSION['pending_role'] = $role ?: 'user';
                $twofaStage = true;
              } else {
                if ($clear = $conn->prepare('DELETE FROM login_attempts WHERE ip = ?')) {
                  $clear->bind_param('s', $ip);
                  $clear->execute();
                  $clear->close();
                }
                $_SESSION['user_id'] = $id;
                $_SESSION['user_role'] = $role ?: 'user';
                $_SESSION['is_admin'] = ($_SESSION['user_role'] ?? 'user') === 'admin' ? 1 : 0;
                $_SESSION[$sessionKey][$ip] = [];
                unset($_SESSION[$throttleLogKey][$ip]);
                header('Location: dashboard.php');
                exit;
              }
              $stmt2->close();
            }
          }
        } else {
          $error = 'Incorrect username or password.';
          if ($insert = $conn->prepare('INSERT INTO login_attempts (ip, attempt_time) VALUES (?, NOW())')) {
            $insert->bind_param('s', $ip);
            $insert->execute();
            $insert->close();
          }
          $recordFailure();
          if ($aggregateFailures >= $maxAttempts) {
            $logThrottle($aggregateFailures, $user);
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
      <input type="text" name="username" required placeholder="Username" autocomplete="username">
      <input type="password" name="password" required placeholder="Password" autocomplete="current-password">
      <button type="submit">Login</button>
    </form>
    <p><a href="forgot.php">Forgot Password?</a> | <a href="register.php">Register</a></p>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
