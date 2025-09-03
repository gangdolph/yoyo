<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/notifications.php';

$id = $_SESSION['user_id'];
$username = '';
$vip = 0;
$vip_expires = null;
$stmt = $conn->prepare("SELECT username, vip_status, vip_expires_at FROM users WHERE id = ?");
if ($stmt === false) {
  error_log('Prepare failed: ' . $conn->error);
} else {
  $stmt->bind_param("i", $id);
  if (!$stmt->execute()) {
    error_log('Execute failed: ' . $stmt->error);
  } else {
    $stmt->bind_result($username, $vip, $vip_expires);
    $stmt->fetch();
    $stmt->close();
  }
}
$vip_active = $vip && (!$vip_expires || strtotime($vip_expires) > time());
$unread_messages = count_unread_messages($conn, $id);
$unread_notifications = count_unread_notifications($conn, $id);
?>
<?php require 'includes/layout.php'; ?>
  <title>Dashboard</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Welcome, <?= htmlspecialchars($username) ?></h2>
    <?php if ($vip_active): ?>
      <?php $expiresTs = strtotime($vip_expires); $days = floor(($expiresTs - time())/86400); ?>
      <?php if ($days <= 7): ?>
        <p class="notice">Your VIP membership expires on <?= htmlspecialchars($vip_expires) ?>. <a href="vip.php">Manage VIP</a>.</p>
      <?php endif; ?>
    <?php elseif ($vip): ?>
      <p class="notice">Your VIP membership expired on <?= htmlspecialchars($vip_expires) ?>. <a href="vip.php">Manage VIP</a>.</p>
    <?php endif; ?>
    <div class="nav-sections">
      <div class="nav-section">
        <h3>Service Requests</h3>
        <ul class="nav-links">
          <li><a class="btn" role="button" href="services.php">Start a Service Request</a></li>
          <li><a class="btn" role="button" href="my-requests.php">View My Service Requests</a></li>
          <li><a class="btn" role="button" href="my-listings.php">Manage My Listings</a></li>
        </ul>
      </div>
      <div class="nav-section">
        <h3>Communications</h3>
        <ul class="nav-links">
          <li><a class="btn" role="button" href="notifications.php">Notifications<?php if (!empty($unread_notifications)): ?> <span class="badge"><?= $unread_notifications ?></span><?php endif; ?></a></li>
          <li><a class="btn" role="button" href="messages.php">Messages<?php if (!empty($unread_messages)): ?> <span class="badge"><?= $unread_messages ?></span><?php endif; ?></a></li>
        </ul>
      </div>
      <div class="nav-section">
        <h3>Account</h3>
        <ul class="nav-links">
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <li><a class="btn" role="button" href="/admin/index.php">Admin Panel</a></li>
          <?php endif; ?>
          <li><a class="btn" role="button" href="profile.php">Edit Profile</a></li>
          <li><a class="btn" role="button" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
