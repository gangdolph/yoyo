<?php
declare(strict_types=1);

require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/require-auth.php';
?>
<?php require 'includes/layout.php'; ?>
  <title>Payment Canceled</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>

  <?php
  // Optional friendly messaging based on reason
  $reason = $_GET['reason'] ?? '';
  $map = [
    'missing_token'     => 'We could not read your payment token. Please try again.',
    'missing_listing'   => 'We could not find your item.',
    'listing_not_found' => 'That item is no longer available.',
    'invalid_amount'    => 'Order total could not be calculated.',
    'config_error'      => 'Payment configuration issue.',
    'gateway_error'     => 'Temporary gateway issue.',
    'unauthorized'      => 'Payment could not be authorized.',
    'location_mismatch' => 'Payment location did not match.',
    'card_declined'     => 'Your card was declined.',
    'payment_failed'    => 'Payment failed.',
  ];
  $msg = $map[$reason] ?? 'Your payment was canceled. No charges were made.';
  ?>

  <div class="page-container">
    <h2>Payment Canceled</h2>
    <p><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="dashboard.php">Return to dashboard</a></p>
  </div>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
