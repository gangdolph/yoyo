<?php
require_once __DIR__ . '/includes/require-auth.php';
require_once 'includes/notifications.php';

$user_id = $_SESSION['user_id'];
$notifications = get_notifications($conn, $user_id);
mark_notifications_read($conn, $user_id);
?>
<?php require 'includes/layout.php'; ?>
  <title>Notifications</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Notifications</h2>
  <?php if (empty($notifications)): ?>
    <p>No notifications.</p>
  <?php else: ?>
    <ul class="notification-list">
      <?php foreach ($notifications as $n): ?>
        <li>
          <?php if ($n['type'] === 'friend_request'): ?>
            <img src="/assets/user-plus.svg" alt="" width="16" height="16">
          <?php elseif ($n['type'] === 'friend_accept'): ?>
            <img src="/assets/user-check.svg" alt="" width="16" height="16">
          <?php endif; ?>
          <?= htmlspecialchars($n['message']) ?>
          <small><?= htmlspecialchars($n['created_at']) ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
