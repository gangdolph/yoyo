<?php
require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/require-auth.php';
?>
<?php require 'includes/layout.php'; ?>
  <title>Payment Success</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Payment Successful</h2>
    <?php if (!empty($_GET['wallet'])): ?>
      <p>Your wallet payment has been authorized and funds are now held in escrow until the order is fulfilled.</p>
    <?php else: ?>
      <p>Your payment was processed successfully.</p>
    <?php endif; ?>
    <p><a href="dashboard.php">Return to dashboard</a></p>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
