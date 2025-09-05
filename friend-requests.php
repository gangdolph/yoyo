<?php
require 'includes/auth.php';
require 'includes/csrf.php';
require 'includes/user.php';
require 'includes/notifications.php';
require 'includes/components.php';

$id = $_SESSION['user_id'];
$requests = [];
$sent = [];

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
        create_notification($conn, $from, 'friend_accept', ($_SESSION['username'] ?? 'Someone') . ' accepted your friend request.');
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

$stmt = $conn->prepare("SELECT f.friend_id, f.status, u.username FROM friends f JOIN users u ON f.friend_id = u.id WHERE f.user_id = ? AND f.status IN ('pending','accepted')");
if ($stmt) {
  $stmt->bind_param('i', $id);
  if ($stmt->execute()) {
    $stmt->bind_result($fid, $fstatus, $funame);
    while ($stmt->fetch()) {
      $sent[] = ['id' => $fid, 'username' => $funame, 'status' => $fstatus];
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
  <h3>Requests You've Received</h3>
  <?php if (empty($requests)): ?>
    <p>No incoming requests.</p>
  <?php else: ?>
    <?php foreach ($requests as $req): ?>
      <div class="card">
        <?= username_with_avatar($conn, $req['id'], $req['username']); ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
          <input type="hidden" name="from_id" value="<?= $req['id']; ?>">
          <button type="submit" class="btn" name="accept">Accept</button>
          <button type="submit" class="btn" name="decline">Decline</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3>Requests You've Sent</h3>
  <?php if (empty($sent)): ?>
    <p>No sent requests.</p>
  <?php else: ?>
    <?php foreach ($sent as $s): ?>
      <div class="card">
        <?= username_with_avatar($conn, $s['id'], $s['username']); ?>
        <span><?= htmlspecialchars(ucfirst($s['status'])); ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
