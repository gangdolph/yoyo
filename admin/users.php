<?php
require_once __DIR__ . '/../includes/auth.php';
require '../includes/db.php';
require '../includes/user.php';
require '../includes/csrf.php';

if (!$_SESSION['is_admin']) {
  header("Location: ../dashboard.php");
  exit;
}

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Invalid CSRF token.';
  } else {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
      if (isset($_POST['reset_2fa'])) {
        $secret = bin2hex(random_bytes(10));
        $code = bin2hex(random_bytes(10));
        $stmt = $conn->prepare('REPLACE INTO user_2fa (user_id, secret, recovery_code) VALUES (?, ?, ?)');
        if ($stmt) {
          $stmt->bind_param('iss', $uid, $secret, $code);
          if ($stmt->execute()) {
            $messages[] = "2FA reset for user ID $uid. Secret: $secret Recovery: $code";
          } else {
            error_log('Execute failed for reset_2fa: ' . $stmt->error);
            $errors[] = 'Failed to reset 2FA.';
          }
          $stmt->close();
        } else {
          error_log('Prepare failed for reset_2fa: ' . $conn->error);
          $errors[] = 'Failed to reset 2FA.';
        }
      } elseif (isset($_POST['delete_user'])) {
        $stmt = $conn->prepare('DELETE FROM user_2fa WHERE user_id = ?');
        if ($stmt) {
          $stmt->bind_param('i', $uid);
          $stmt->execute();
          $stmt->close();
        }
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        if ($stmt) {
          $stmt->bind_param('i', $uid);
          if ($stmt->execute()) {
            $messages[] = "Deleted user ID $uid.";
          } else {
            error_log('Execute failed for delete_user: ' . $stmt->error);
            $errors[] = 'Failed to delete user.';
          }
          $stmt->close();
        } else {
          error_log('Prepare failed for delete_user: ' . $conn->error);
          $errors[] = 'Failed to delete user.';
        }
      }
    }
  }
}

$users = [];
if ($result = $conn->query('SELECT id, username, email, status, is_admin FROM users ORDER BY id ASC')) {
  $users = $result->fetch_all(MYSQLI_ASSOC);
  $result->close();
}
?>
<?php require '../includes/layout.php'; ?>
  <title>Manage Users</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Manage Users</h2>
  <?php foreach ($errors as $e): ?>
    <p style="color:red;"><?= htmlspecialchars($e) ?></p>
  <?php endforeach; ?>
  <?php foreach ($messages as $m): ?>
    <p style="color:green;"><?= htmlspecialchars($m) ?></p>
  <?php endforeach; ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Status</th>
        <th>Admin</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= username_with_avatar($conn, $u['id'], $u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['status']) ?></td>
          <td><?= $u['is_admin'] ? 'Yes' : 'No' ?></td>
          <td>
            <a href="../view-profile.php?id=<?= $u['id'] ?>">View</a>
            <a href="user-edit.php?id=<?= $u['id'] ?>">Edit</a>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" name="reset_2fa">Reset 2FA</button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this user?');">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" name="delete_user">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
