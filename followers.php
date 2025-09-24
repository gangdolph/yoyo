<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/user.php';

$id = $_SESSION['user_id'];
$followers = [];
$following = [];

$stmt = $conn->prepare("SELECT u.id, u.username FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.followee_id = ?");
if ($stmt) {
  $stmt->bind_param('i', $id);
  if ($stmt->execute()) {
    $stmt->bind_result($uid, $uname);
    while ($stmt->fetch()) {
      $followers[] = ['id' => $uid, 'username' => $uname];
    }
  }
  $stmt->close();
}

$stmt = $conn->prepare("SELECT u.id, u.username FROM follows f JOIN users u ON f.followee_id = u.id WHERE f.follower_id = ?");
if ($stmt) {
  $stmt->bind_param('i', $id);
  if ($stmt->execute()) {
    $stmt->bind_result($uid, $uname);
    while ($stmt->fetch()) {
      $following[] = ['id' => $uid, 'username' => $uname];
    }
  }
  $stmt->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Followers</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Your Followers</h2>
  <ul>
    <?php foreach ($followers as $u): ?>
      <li><?= username_with_avatar($conn, $u['id'], $u['username']); ?></li>
    <?php endforeach; ?>
  </ul>
  <h2>Following</h2>
  <ul>
    <?php foreach ($following as $u): ?>
      <li><?= username_with_avatar($conn, $u['id'], $u['username']); ?></li>
    <?php endforeach; ?>
  </ul>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
