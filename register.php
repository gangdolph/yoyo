<?php
require 'includes/csrf.php';
require 'includes/db.php';
// Mail helper lives in the project root
require 'mail.php';

if (!defined('HEADER_SKIP_AUTH')) {
  define('HEADER_SKIP_AUTH', true);
}

$user = '';
$email = '';
$isBusiness = false;
$company = '';
$website = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = "Invalid CSRF token.";
  } else {
    $user = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $isBusiness = isset($_POST['is_business']);
    $company = trim($_POST['company_name'] ?? '');
    $website = trim($_POST['company_website'] ?? '');
    
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Please enter a valid email address.";
  } elseif (strlen($user) < 3 || strlen($pass) < 6) {
    $error = "Username must be 3+ chars and password 6+.";
  } elseif ($isBusiness && ($company === '' || !filter_var($website, FILTER_VALIDATE_URL))) {
    $error = "Please provide a valid company name and website.";
  } else {
    // NOTE: Use try/finally to close mysqli_stmt exactly once.
    try {
      $stmtCheckUser = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
      if (!$stmtCheckUser) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
      }
      try {
        $stmtCheckUser->bind_param("ss", $user, $email);
        if (!$stmtCheckUser->execute()) {
          error_log('Execute failed: ' . $stmtCheckUser->error);
          $error = "Database error.";
        } else {
          $stmtCheckUser->store_result();
          $existingUserCount = $stmtCheckUser->num_rows;
        }
      } finally {
        if ($stmtCheckUser instanceof mysqli_stmt) {
          $stmtCheckUser->close();
        }
      }
    } catch (RuntimeException $e) {
      error_log($e->getMessage());
      $error = "Database error.";
    }

    if (empty($error) && isset($existingUserCount) && $existingUserCount > 0) {
      $error = "Username or email already exists.";
    }

    if (empty($error)) {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $status = 'offline';
      $accountType = $isBusiness ? 'business' : 'standard';

      try {
        $stmtInsertUser = $conn->prepare("INSERT INTO users (username, email, password, status, account_type, company_name, company_website) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmtInsertUser) {
          throw new RuntimeException('Prepare failed: ' . $conn->error);
        }
        try {
          $stmtInsertUser->bind_param("sssssss", $user, $email, $hash, $status, $accountType, $company, $website);
          if (!$stmtInsertUser->execute()) {
            error_log('Execute failed: ' . $stmtInsertUser->error);
            $error = "Registration failed. Please try again.";
          } elseif ($stmtInsertUser->affected_rows !== 1) {
            error_log('Unexpected insert result for user registration.');
            $error = "Registration failed. Please try again.";
          } else {
            $uid = $stmtInsertUser->insert_id;
          }
        } finally {
          if ($stmtInsertUser instanceof mysqli_stmt) {
            $stmtInsertUser->close();
          }
        }
      } catch (RuntimeException $e) {
        error_log($e->getMessage());
        $error = "Registration failed. Please try again.";
      }
    }

    if (empty($error) && isset($uid)) {
      $token = bin2hex(random_bytes(32));
      $expires = date('Y-m-d H:i:s', time() + 3600);

      try {
        $stmtInsertToken = $conn->prepare("INSERT INTO tokens (user_id, token, type, expires_at) VALUES (?, ?, 'verify', ?)");
        if (!$stmtInsertToken) {
          throw new RuntimeException('Prepare failed: ' . $conn->error);
        }
        try {
          $stmtInsertToken->bind_param("iss", $uid, $token, $expires);
          if (!$stmtInsertToken->execute()) {
            error_log('Execute failed: ' . $stmtInsertToken->error);
            $error = "Database error.";
          }
        } finally {
          if ($stmtInsertToken instanceof mysqli_stmt) {
            $stmtInsertToken->close();
          }
        }
      } catch (RuntimeException $e) {
        error_log($e->getMessage());
        $error = "Database error.";
      }

      if (empty($error)) {
        $link = "https://skuze.tech/verify.php?token=$token";
        $body = "Welcome to SkuzE!\n\nPlease verify your email: $link";
        try {
          send_email($email, "Verify your account", $body);
          $success = "Check your email to verify your account.";
        } catch (Exception $e) {
          error_log('Email dispatch failed: ' . $e->getMessage());
          $error = "Failed to send verification email.";
        }
      }
    }
  }
}
}

?>
<?php require 'includes/layout.php'; ?>
  <title>Register</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Register</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
  <?php if (!empty($success)) echo "<p style='color:green;'>" . htmlspecialchars($success) . "</p>"; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="text" name="username" required placeholder="Username" value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="email" name="email" required placeholder="Email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="password" name="password" required placeholder="Password">
      <label><input type="checkbox" name="is_business" id="is_business" <?= $isBusiness ? 'checked' : ''; ?>> Register as Business</label>
      <div id="business_fields" style="<?= $isBusiness ? '' : 'display:none;'; ?>">
        <input type="text" name="company_name" placeholder="Company Name" value="<?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="url" name="company_website" placeholder="Company Website" value="<?= htmlspecialchars($website, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <button type="submit">Register</button>
    </form>
    <script>
      document.getElementById('is_business').addEventListener('change', function() {
        document.getElementById('business_fields').style.display = this.checked ? '' : 'none';
      });
    </script>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
