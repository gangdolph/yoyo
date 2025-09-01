<?php
require 'includes/auth.php';
require 'includes/csrf.php';
require 'includes/user.php';

$id = $_SESSION['user_id'];
$requests = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $from = (int)($_POST['from_id'] ?? 0);
    if ($from) {
      if (isset($_POST['accept'])) {
        $stmt = $conn->prepare("UPDATE friends SET status='accepted' WHERE user_id=? AND friend_id=?");
        if ($stmt) {
          $stmt->bind_param('ii', $from, $id);
          $stmt->execute();
          $stmt->close();
        }
        $stmt = $conn->prepare("REPLACE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
        if ($stmt) {
          $stmt->bind_param('ii', $id, $from);
          $stmt->execute();
          $stmt->close();
        }
      } elseif (isset($_POST['decline'])) {
        $stmt = $conn->prepare("DELETE FROM friends WHERE user_id=? AND friend_id=?");
        if ($stmt) {
          $stmt->bind_param('ii', $from, $id);
          $stmt->execute();
          $stmt->close();
        }
      }
    }
  }
}

$stmt = $conn->prepare("SELECT f.user_id, u.username FROM friends f JOIN users u ON f.user_id = u.id WHERE f.friend_id = ? AND f.status = 'pending'");
if ($stmt) {
  $stmt->bind_param('i', $id);
  if ($stmt->execute()) {
    $stmt->bind_result($uid, $uname);
    while ($stmt->fetch()) {
      $requests[] = ['id' => $uid, 'username' => $uname];
    }
  }
  $stmt->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Friend Requests</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Friend Requests</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
  <?php foreach ($requests as $req): ?>
    <div>
      <?= username_with_avatar($conn, $req['id'], $req['username']); ?>
      <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="from_id" value="<?= $req['id']; ?>">
        <button type="submit" name="accept">Accept</button>
        <button type="submit" name="decline">Decline</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
