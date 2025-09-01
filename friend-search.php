<?php
require 'includes/auth.php';
require 'includes/csrf.php';
require 'includes/user.php';

$id = $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $target = (int)($_POST['target_id'] ?? 0);
    if ($target) {
      if (isset($_POST['add_friend'])) {
        $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status='pending'");
        if ($stmt) {
          $stmt->bind_param('ii', $id, $target);
          $stmt->execute();
          $stmt->close();
        }
      } elseif (isset($_POST['follow'])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO follows (follower_id, followee_id) VALUES (?, ?)");
        if ($stmt) {
          $stmt->bind_param('ii', $id, $target);
          $stmt->execute();
          $stmt->close();
        }
      }
    }
  }
}

if ($query !== '') {
  $stmt = $conn->prepare("SELECT id, username FROM users WHERE username LIKE CONCAT('%', ?, '%') AND id != ?");
  if ($stmt) {
    $stmt->bind_param('si', $query, $id);
    if ($stmt->execute()) {
      $stmt->bind_result($uid, $uname);
      while ($stmt->fetch()) {
        $results[] = ['id' => $uid, 'username' => $uname];
      }
    }
    $stmt->close();
  }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Find Friends</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Find Friends</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
  <form method="get">
    <input type="text" name="q" value="<?= htmlspecialchars($query); ?>" placeholder="Search by username">
    <button type="submit">Search</button>
  </form>
  <?php foreach ($results as $user): ?>
    <div>
      <a href="view-profile.php?id=<?= $user['id']; ?>"><?= username_with_avatar($conn, $user['id'], $user['username']); ?></a>
      <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="target_id" value="<?= $user['id']; ?>">
        <button type="submit" name="add_friend">Add Friend</button>
        <button type="submit" name="follow">Follow</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
