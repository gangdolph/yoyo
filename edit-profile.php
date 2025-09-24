<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/csrf.php';

$userId = $_SESSION['user_id'];
$bio = '';
$isPrivate = 0;
$showBio = 1;
$showFriends = 1;
$showListings = 1;

$stmt = $conn->prepare('SELECT bio, is_private, show_bio, show_friends, show_listings FROM profiles WHERE user_id = ?');
if ($stmt) {
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->bind_result($bio, $isPrivate, $showBio, $showFriends, $showListings);
  $stmt->fetch();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $bio = trim($_POST['bio'] ?? '');
    $isPrivate = isset($_POST['is_private']) ? 1 : 0;
    $showBio = isset($_POST['show_bio']) ? 1 : 0;
    $showFriends = isset($_POST['show_friends']) ? 1 : 0;
    $showListings = isset($_POST['show_listings']) ? 1 : 0;
    $stmt = $conn->prepare('INSERT INTO profiles (user_id, bio, is_private, show_bio, show_friends, show_listings) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE bio=VALUES(bio), is_private=VALUES(is_private), show_bio=VALUES(show_bio), show_friends=VALUES(show_friends), show_listings=VALUES(show_listings)');
    if ($stmt) {
      $stmt->bind_param('isiiii', $userId, $bio, $isPrivate, $showBio, $showFriends, $showListings);
      if ($stmt->execute()) {
        $msg = 'Profile updated.';
      } else {
        $error = 'Database error.';
      }
      $stmt->close();
    }
  }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Edit Profile</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Edit Profile</h2>
  <?php if (!empty($error)): ?><p><?= htmlspecialchars($error); ?></p><?php endif; ?>
  <?php if (!empty($msg)): ?><p><?= htmlspecialchars($msg); ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <label>Bio:<br>
      <textarea name="bio" rows="4" cols="40"><?= htmlspecialchars($bio); ?></textarea>
    </label><br>
    <label><input type="checkbox" name="is_private" value="1" <?= $isPrivate ? 'checked' : ''; ?>> Private Profile</label><br>
    <label><input type="checkbox" name="show_bio" value="1" <?= $showBio ? 'checked' : ''; ?>> Show bio to non-friends</label><br>
    <label><input type="checkbox" name="show_friends" value="1" <?= $showFriends ? 'checked' : ''; ?>> Show friends to non-friends</label><br>
    <label><input type="checkbox" name="show_listings" value="1" <?= $showListings ? 'checked' : ''; ?>> Show listings to non-friends</label><br>
    <button type="submit">Save</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
