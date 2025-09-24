<?php
require_once __DIR__ . '/includes/require-auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/config.php';
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    require_auth();
}

$upgradeNotice = !empty($_GET['upgrade']) ? 'You must become a member to access seller tools.' : '';

$memberStatus = 0;
$memberExpires = null;
if ($stmt = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id = ?')) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $stmt->bind_result($memberStatus, $memberExpires);
        $stmt->fetch();
    }
    $stmt->close();
}

$isMember = false;
if ($memberStatus) {
    $isMember = true;
    if ($memberExpires) {
        $expiresTs = strtotime((string) $memberExpires);
        if ($expiresTs !== false && $expiresTs <= time()) {
            $isMember = false;
        }
    }
}

$plans = [
    'month' => [
        'label' => '1 Month',
        'amount_cents' => 2499,
        'interval' => 'P1M',
    ],
    'year' => [
        'label' => '1 Year',
        'amount_cents' => 24999,
        'interval' => 'P1Y',
    ],
];

$squareConfig = require __DIR__ . '/includes/square-config.php';
$applicationId = $squareConfig['application_id'];
$locationId = $squareConfig['location_id'];
$environment = $squareConfig['environment'];
$squareJs = $environment === 'production'
    ? 'https://web.squarecdn.com/v1/square.js'
    : 'https://sandbox.web.squarecdn.com/v1/square.js';

try {
    $purchaseToken = bin2hex(random_bytes(16));
} catch (Throwable $tokenError) {
    $purchaseToken = hash('sha256', microtime(true) . '|' . $userId);
}
$_SESSION['member_purchase_token'] = $purchaseToken;

$success = !empty($_GET['success']);
$errorCode = isset($_GET['error']) ? (string) $_GET['error'] : '';
$errorMessage = '';
if ($errorCode !== '') {
    $messages = [
        'invalid' => 'We could not process that request. Please verify the details and try again.',
        'amount' => 'Selected membership plan was invalid. Please choose a plan and try again.',
        'payment' => 'Payment could not be completed. No charges were made.',
    ];
    $errorMessage = $messages[$errorCode] ?? 'Something went wrong. Please try again.';
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Member Plans</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="<?= htmlspecialchars($squareJs); ?>"></script>
  <script src="assets/member.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container member-page">
    <h2>Member Plans</h2>
    <?php if ($upgradeNotice): ?>
      <p class="error"><?= htmlspecialchars($upgradeNotice); ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
      <p class="notice">Membership updated successfully.</p>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
      <p class="error"><?= htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>
    <?php if ($isMember): ?>
      <p>Your membership is active<?php if ($memberExpires): ?> until <?= htmlspecialchars($memberExpires); ?><?php endif; ?>.</p>
    <?php elseif ($memberStatus): ?>
      <p>Your membership expired<?php if ($memberExpires): ?> on <?= htmlspecialchars($memberExpires); ?><?php endif; ?>.</p>
    <?php else: ?>
      <p>Become a Member to unlock instant listing approvals, wallet perks, and zero withdrawal fees.</p>
    <?php endif; ?>
    <section class="member-plans">
      <h3>Choose Your Plan</h3>
      <form id="member-purchase-form" method="post" action="member_purchase_process.php">
        <label for="membership-plan">Membership Term</label>
        <select id="membership-plan" name="plan" required>
          <option value="">Select a plan</option>
          <?php foreach ($plans as $planKey => $plan): ?>
            <option value="<?= htmlspecialchars($planKey); ?>" data-amount="<?= (int) $plan['amount_cents']; ?>">
              <?= htmlspecialchars($plan['label']); ?> — $<?= htmlspecialchars(number_format($plan['amount_cents'] / 100, 2)); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div id="member-card-container" data-app-id="<?= htmlspecialchars($applicationId); ?>" data-location-id="<?= htmlspecialchars($locationId); ?>"></div>
        <input type="hidden" name="token" id="member-token">
        <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken); ?>">
        <input type="hidden" name="currency" value="<?= htmlspecialchars(strtoupper((string) ($config['CURRENCY'] ?? 'USD'))); ?>">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <button type="submit" class="btn">Purchase Membership</button>
      </form>
      <p class="member-note">Members enjoy free wallet withdrawals and priority support.</p>
    </section>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
