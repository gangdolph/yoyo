<?php
require 'includes/auth.php';
require 'includes/csrf.php';

$target = (int)($_GET['id'] ?? 0);
if (!$target) {
  echo 'No user specified.';
  exit;
}

$stmt = $conn->prepare("SELECT u.username, p.avatar_path, p.is_private, u.account_type, u.company_name, u.company_website, u.company_logo, u.vip_status, u.vip_expires_at FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->bind_param('i', $target);
$stmt->execute();
$stmt->bind_result($username, $avatar, $isPrivate, $accountType, $companyName, $companyWebsite, $logoName, $vipStatus, $vipExpires);
$found = $stmt->fetch();
$stmt->close();
$vipActive = $vipStatus && (!$vipExpires || strtotime($vipExpires) > time());
if (!$found) {
  echo 'User not found.';
  exit;
}
if ($avatar) {
  $file = basename($avatar);
  $fs = __DIR__ . '/assets/avatars/' . $file;
  $avatar = is_file($fs) ? '/assets/avatars/' . $file : '';
}
$companyLogo = '';
if ($logoName) {
  $lf = basename($logoName);
  $ls = __DIR__ . '/assets/logos/' . $lf;
  $companyLogo = is_file($ls) ? '/assets/logos/' . $lf : '';
}

$viewer = $_SESSION['user_id'];
$isFriend = false;
if ($viewer === $target) {
  $isFriend = true;
} else {
  $stmt2 = $conn->prepare("SELECT 1 FROM friends WHERE user_id = ? AND friend_id = ? AND status='accepted'");
  if ($stmt2) {
    $stmt2->bind_param('ii', $target, $viewer);
    $stmt2->execute();
    $stmt2->store_result();
    $isFriend = $stmt2->num_rows === 1;
    $stmt2->close();
  }
}

$requestStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend']) && $viewer !== $target) {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $requestStatus = 'Invalid CSRF token.';
  } else {
    $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status='pending'");
    if ($stmt) {
      $stmt->bind_param('ii', $viewer, $target);
      $stmt->execute();
      $stmt->close();
      $requestStatus = 'Request sent';
    }
  }
}

$outgoingStatus = null;
$incomingStatus = null;
if ($viewer !== $target) {
  $stmt = $conn->prepare("SELECT status FROM friends WHERE user_id=? AND friend_id=?");
  if ($stmt) {
    $stmt->bind_param('ii', $viewer, $target);
    $stmt->execute();
    $stmt->bind_result($outgoingStatus);
    $stmt->fetch();
    $stmt->close();
  }
  $stmt = $conn->prepare("SELECT status FROM friends WHERE user_id=? AND friend_id=?");
  if ($stmt) {
    $stmt->bind_param('ii', $target, $viewer);
    $stmt->execute();
    $stmt->bind_result($incomingStatus);
    $stmt->fetch();
    $stmt->close();
  }
  if ($requestStatus === '' && $outgoingStatus === 'pending') {
    $requestStatus = 'Request sent';
  } elseif ($requestStatus === '' && $incomingStatus === 'pending') {
    $requestStatus = 'Pending';
  }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Profile of <?= htmlspecialchars($username); ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2><?= htmlspecialchars($username); ?><?php if ($vipActive) echo ' <span class="vip-badge">VIP</span>'; ?></h2>
  <?php if ($viewer !== $target): ?>
    <?php if ($requestStatus !== ''): ?>
      <p><?= htmlspecialchars($requestStatus); ?></p>
    <?php elseif ($outgoingStatus !== 'pending' && $incomingStatus !== 'pending' && !$isFriend): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <button type="submit" name="add_friend">Add Friend</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($isPrivate && !$isFriend): ?>
    <p>This profile is private.</p>
  <?php else: ?>
    <?php if ($avatar): ?>
      <img src="<?= htmlspecialchars($avatar); ?>" alt="Avatar" width="100">
    <?php endif; ?>
    <?php if ($accountType === 'business'): ?>
      <h3><?= htmlspecialchars($companyName); ?></h3>
      <?php if ($companyLogo): ?>
        <img src="<?= htmlspecialchars($companyLogo); ?>" alt="Logo" width="100">
      <?php endif; ?>
      <?php if ($companyWebsite): ?>
        <p><a href="<?= htmlspecialchars($companyWebsite); ?>" target="_blank">Visit Website</a></p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
