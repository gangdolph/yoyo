<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/csrf.php';
require 'includes/notifications.php';
require 'includes/components.php';

$target = (int)($_GET['id'] ?? 0);
if (!$target) {
  echo 'No user specified.';
  exit;
}

$stmt = $conn->prepare("SELECT u.username, p.avatar_path, p.is_private, p.bio, p.show_bio, p.show_friends, p.show_listings, u.account_type, u.company_name, u.company_website, u.company_logo, u.vip_status, u.vip_expires_at FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->bind_param('i', $target);
$stmt->execute();
$stmt->bind_result($username, $avatar, $isPrivate, $bio, $showBio, $showFriends, $showListings, $accountType, $companyName, $companyWebsite, $logoName, $vipStatus, $vipExpires);
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

$bio = $bio ?? '';
$showBio = isset($showBio) ? (int)$showBio : 1;
$showFriends = isset($showFriends) ? (int)$showFriends : 1;
$showListings = isset($showListings) ? (int)$showListings : 1;

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
      create_notification($conn, $target, 'friend_request', ($_SESSION['username'] ?? 'Someone') . ' sent you a friend request.');
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
  <?php if ($viewer === $target): ?>
    <p><?= render_button('edit-profile.php', 'Edit Profile'); ?></p>
  <?php endif; ?>
  <?php if ($viewer !== $target): ?>
    <?php if ($requestStatus !== ''): ?>
      <p><?= htmlspecialchars($requestStatus); ?></p>
    <?php elseif ($outgoingStatus === 'pending'): ?>
      <button type="button" class="btn" disabled>Request Sent</button>
    <?php elseif ($incomingStatus === 'pending'): ?>
      <p>Pending</p>
    <?php elseif (!$isFriend): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <button type="submit" class="btn" name="add_friend">Add Friend</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($isPrivate && !$isFriend): ?>
    <p>This profile is private.</p>
  <?php else: ?>
    <?php if ($avatar): ?>
      <div class="card"><img src="<?= htmlspecialchars($avatar); ?>" alt="Avatar" width="100"></div>
    <?php endif; ?>
    <?php if (($isFriend || $viewer === $target || $showBio) && $bio): ?>
      <?= render_card('Bio', '<p>'.nl2br(htmlspecialchars($bio)).'</p>'); ?>
    <?php endif; ?>
    <?php if ($accountType === 'business'): ?>
      <?php ob_start(); ?>
        <?php if ($companyLogo): ?><img src="<?= htmlspecialchars($companyLogo); ?>" alt="Logo" width="100"><?php endif; ?>
        <?php if ($companyWebsite): ?><p><a href="<?= htmlspecialchars($companyWebsite); ?>" target="_blank">Visit Website</a></p><?php endif; ?>
      <?php $companyBody = ob_get_clean(); ?>
      <?= render_card($companyName, $companyBody); ?>
    <?php endif; ?>
    <?php if ($isFriend || $viewer === $target || $showFriends): ?>
      <?php
      $friends = [];
      $fsql = "SELECT u.id, u.username FROM friends f JOIN users u ON u.id = f.friend_id WHERE f.user_id = ? AND f.status='accepted' UNION SELECT u.id, u.username FROM friends f JOIN users u ON u.id = f.user_id WHERE f.friend_id = ? AND f.status='accepted' LIMIT 5";
      if ($fstmt = $conn->prepare($fsql)) {
        $fstmt->bind_param('ii', $target, $target);
        $fstmt->execute();
        $fstmt->bind_result($fid, $fname);
        while ($fstmt->fetch()) {
          $friends[] = ['id' => $fid, 'username' => $fname];
        }
        $fstmt->close();
      }
      if ($friends):
        ob_start();
        echo '<ul>';
        foreach ($friends as $fr) {
          echo '<li><a href="view-profile.php?id=' . $fr['id'] . '">' . htmlspecialchars($fr['username']) . '</a></li>';
        }
        echo '</ul>';
        $friendsHtml = ob_get_clean();
        echo render_card('Friends', $friendsHtml);
      endif;
      ?>
    <?php endif; ?>
    <?php if ($isFriend || $viewer === $target || $showListings): ?>
      <?php
      $listings = [];
      if ($lst = $conn->prepare('SELECT id, have_item, want_item FROM trade_listings WHERE owner_id = ? ORDER BY created_at DESC LIMIT 5')) {
        $lst->bind_param('i', $target);
        $lst->execute();
        $lst->bind_result($lid, $have, $want);
        while ($lst->fetch()) {
          $listings[] = ['id' => $lid, 'have' => $have, 'want' => $want];
        }
        $lst->close();
      }
      if ($listings):
        ob_start();
        echo '<ul>';
        foreach ($listings as $li) {
          echo '<li><a href="trade-listing.php?id=' . $li['id'] . '">' . htmlspecialchars($li['have']) . ' for ' . htmlspecialchars($li['want']) . '</a></li>';
        }
        echo '</ul>';
        $listHtml = ob_get_clean();
        echo render_card('Recent Listings', $listHtml);
      endif;
      ?>
    <?php endif; ?>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
