<?php
require __DIR__ . '/_debug_bootstrap.php';
require 'includes/auth.php';
?>
<?php require 'includes/layout.php'; ?>
  <title>Payment Success</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Payment Successful</h2>
  <p>Your payment was processed successfully.</p>
  <p><a href="dashboard.php">Return to dashboard</a></p>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
