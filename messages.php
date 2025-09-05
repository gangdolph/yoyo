<?php
require 'includes/auth.php';
require 'includes/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT u.id AS other_id, u.username,
            SUM(CASE WHEN m.recipient_id = ? AND m.read_at IS NULL THEN 1 ELSE 0 END) AS unread,
            MAX(m.created_at) AS last_time
     FROM users u
     JOIN messages m ON (u.id = m.sender_id AND m.recipient_id = ?) OR (u.id = m.recipient_id AND m.sender_id = ?)
     GROUP BY u.id, u.username
     ORDER BY last_time DESC"
);
$stmt->bind_param('iii', $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php require 'includes/layout.php'; ?>
  <title>Messages</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Messages <a class="btn" role="button" href="compose-message.php" aria-label="Compose new message" title="Compose new message">+</a>
    <a class="btn" href="message-requests.php" aria-label="View message requests">Requests</a>
  </h2>
  <?php if (empty($conversations)): ?>
    <p>No conversations yet.</p>
  <?php else: ?>
    <ul class="conversation-list">
      <?php foreach ($conversations as $c): ?>
        <li>
          <a class="btn" href="message-thread.php?user=<?= $c['other_id'] ?>">
            <?= htmlspecialchars($c['username']) ?>
            <?php if ($c['unread'] > 0): ?>
              <span class="badge"><?= $c['unread'] ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
