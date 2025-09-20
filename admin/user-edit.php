<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/db.php';
require '../includes/user.php';
require '../includes/csrf.php';

ensure_admin('../dashboard.php');

$statuses = ['online', 'offline', 'busy', 'away']; // flair presets
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header('Location: users.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'offline';
    $role = $_POST['role'] ?? 'user';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email.';
    } elseif (!in_array($status, $statuses, true)) {
      $error = 'Invalid status.';
    } elseif (!in_array($role, ['user', 'skuze_official', 'admin'], true)) {
      $error = 'Invalid role selection.';
    } else {
      $is_admin = $role === 'admin' ? 1 : 0;
      $stmt = $conn->prepare('UPDATE users SET email = ?, status = ?, role = ?, is_admin = ? WHERE id = ?');
      if ($stmt) {
        $stmt->bind_param('sssii', $email, $status, $role, $is_admin, $id);
        if ($stmt->execute()) {
          $success = 'User updated successfully.';
        } else {
          error_log('Execute failed for user update: ' . $stmt->error);
          $error = 'Failed to update user.';
        }
        $stmt->close();
      } else {
        error_log('Prepare failed for user update: ' . $conn->error);
        $error = 'Failed to update user.';
      }
    }
  }
}

$stmt = $conn->prepare('SELECT username, email, status, role FROM users WHERE id = ?');
if ($stmt) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->bind_result($username, $email, $status, $role);
 if (!$stmt->fetch()) {
   $stmt->close();
   header('Location: users.php');
   exit;
 }
 $stmt->close();
  $role = $role ?: 'user';
} else {
  error_log('Prepare failed fetching user: ' . $conn->error);
  header('Location: users.php');
  exit;
}
?>
<?php require '../includes/layout.php'; ?>
  <title>Edit User</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Edit User <?= htmlspecialchars($username) ?></h2>
  <?php if (!empty($error)) echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>'; ?>
  <?php if (!empty($success)) echo '<p style="color:green;">' . htmlspecialchars($success) . '</p>'; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <label>Email:
      <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">
    </label><br>
    <label>Status:
      <select name="status">
        <?php foreach ($statuses as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($opt)) ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <p class="notice">Status controls the animated border shown with the user's name.</p>
    <label>Role:
      <select name="role">
        <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Standard</option>
        <option value="skuze_official" <?= $role === 'skuze_official' ? 'selected' : '' ?>>SkuzE Official</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
    </label><br>
    <button type="submit">Save</button>
  </form>
  <p><a class="btn" href="users.php">Back to Users</a></p>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
