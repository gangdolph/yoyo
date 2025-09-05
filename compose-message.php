<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/notifications.php';

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if ($recipient_id > 0 && $body !== '') {
      $stmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
      $stmt->bind_param('i', $recipient_id);
      $stmt->execute();
      $stmt->bind_result($rid);
      if ($stmt->fetch()) {
        $stmt->close();
        $insert = $conn->prepare('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
        $insert->bind_param('iis', $user_id, $recipient_id, $body);
        $insert->execute();
        $insert->close();
        if (!empty($_SESSION['is_admin'])) {
          $msg = notification_message('admin_message');
          create_notification($conn, $recipient_id, 'admin_message', $msg);
        }
        header("Location: message-thread.php?user=$recipient_id");
        exit;
      }
      $stmt->close();
      $error = 'Recipient not found.';
    } else {
      $error = 'Please select a recipient and enter a message.';
    }
  }
}

$stmt = $conn->prepare(
  'SELECT u.id, u.username FROM users u '
  . 'JOIN friends f ON u.id = f.friend_id '
  . 'WHERE f.user_id = ? AND f.status = "accepted" '
  . 'ORDER BY u.username'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php require 'includes/layout.php'; ?>
  <title>Compose Message</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Compose Message</h2>
  <?php if (!empty($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error); ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <label for="recipient">Recipient</label>
    <select name="recipient_id" id="recipient" required>
      <?php foreach ($users as $u): ?>
        <option value="<?= $u['id']; ?>"><?= htmlspecialchars($u['username']); ?></option>
      <?php endforeach; ?>
    </select>
    <label for="body">Message</label>
    <textarea name="body" id="body" required></textarea>
    <button type="submit" class="btn">Send</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
