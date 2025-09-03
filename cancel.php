<?php
require __DIR__ . '/_debug_bootstrap.php';
require 'includes/auth.php';
?>
<?php require 'includes/layout.php'; ?>
  <title>Payment Canceled</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Payment Canceled</h2>
  <p>Your payment was canceled. No charges were made.</p>
  <p><a href="dashboard.php">Return to dashboard</a></p>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
