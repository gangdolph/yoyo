<?php
require 'includes/csrf.php';
require 'includes/db.php';
// Mail helper lives in the project root
require 'mail.php';

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
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
    if ($stmt === false) {
      error_log('Prepare failed: ' . $conn->error);
      $error = "Database error.";
    } else {
      $stmt->bind_param("ss", $user, $email);
      if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        $error = "Database error.";
        $stmt->close();
      } else {
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
          $error = "Username or email already exists.";
          $stmt->close();
        } else {
          $hash = password_hash($pass, PASSWORD_DEFAULT);
          $status = 'offline';
          $accountType = $isBusiness ? 'business' : 'standard';
          $stmt->close();
          $stmt = $conn->prepare("INSERT INTO users (username, email, password, status, account_type, company_name, company_website) VALUES (?, ?, ?, ?, ?, ?, ?)");
          if ($stmt === false) {
            error_log('Prepare failed: ' . $conn->error);
            $error = "Registration failed. Please try again.";
          } else {
            $stmt->bind_param("sssssss", $user, $email, $hash, $status, $accountType, $company, $website);
            if (!$stmt->execute()) {
              error_log('Execute failed: ' . $stmt->error);
              $error = "Registration failed. Please try again.";
            } elseif ($stmt->affected_rows !== 1) {
              error_log('Unexpected insert result for user registration.');
              $error = "Registration failed. Please try again.";
            } else {
              $uid = $stmt->insert_id;
              $token = bin2hex(random_bytes(32));
              $expires = date('Y-m-d H:i:s', time() + 3600);
              $stmt->close();
              $stmt = $conn->prepare("INSERT INTO tokens (user_id, token, type, expires_at) VALUES (?, ?, 'verify', ?)");
              if ($stmt === false) {
                error_log('Prepare failed: ' . $conn->error);
                $error = "Database error.";
              } else {
                $stmt->bind_param("iss", $uid, $token, $expires);
                if (!$stmt->execute()) {
                  error_log('Execute failed: ' . $stmt->error);
                  $error = "Database error.";
                } else {
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
                $stmt->close();
              }
            }
            $stmt->close();
          }
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
