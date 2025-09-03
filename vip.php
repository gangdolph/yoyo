<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$message = '';
$upgrade_notice = !empty($_GET['upgrade']) ? 'You must upgrade to VIP to create listings.' : '';

// Fetch current VIP status
$vip = 0;
$vip_expires = null;
if ($stmt = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id=?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($vip, $vip_expires);
    $stmt->fetch();
    $stmt->close();
}
$vip_active = $vip && (!$vip_expires || strtotime($vip_expires) > time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
    } else {
        $duration = $_POST['duration'] === 'year' ? 'year' : 'month';
        $interval = $duration === 'year' ? '+1 year' : '+1 month';
        $baseTime = $vip_active && $vip_expires && strtotime($vip_expires) > time() ? strtotime($vip_expires) : time();
        $expires = date('Y-m-d H:i:s', strtotime($interval, $baseTime));
        if ($stmt = $conn->prepare('UPDATE users SET vip_status=1, vip_expires_at=? WHERE id=?')) {
            $stmt->bind_param('si', $expires, $user_id);
            $stmt->execute();
            $stmt->close();
            $vip = 1;
            $vip_expires = $expires;
            $vip_active = true;
            $message = 'VIP activated!';
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>VIP Membership</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>VIP Membership</h2>
    <?php if ($upgrade_notice): ?>
      <p class="error"><?= htmlspecialchars($upgrade_notice) ?></p>
    <?php endif; ?>
    <?php if ($message): ?>
      <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if ($vip_active): ?>
      <p>Your VIP membership is active until <?= htmlspecialchars($vip_expires) ?>.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="duration" value="month">
        <button type="submit">Renew VIP (1 Month)</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="duration" value="year">
        <button type="submit">Renew VIP (1 Year)</button>
      </form>
    <?php else: ?>
      <p>Activate VIP to skip approvals and enjoy perks.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="duration" value="month">
        <button type="submit">Purchase VIP (1 Month)</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="duration" value="year">
        <button type="submit">Purchase VIP (1 Year)</button>
      </form>
    <?php endif; ?>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
