<?php
require __DIR__ . '/_debug_bootstrap.php';
session_start();
?>
<?php require 'includes/layout.php'; ?>
<?php require 'includes/components.php'; ?>
  <meta charset="UTF-8">
  <title>SkuzE | Electronics Repair & Modding</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>

    <div class="hero">
      <div class="hero-ascii">
        <pre>
 ____  _              _____
/ ___|| | ___   _ ___| ____|
\___ \| |/ / | | |_  /  _|
 ___) |   <| |_| |/ /| |___
|____/|_|\__,_/___|_____|
        </pre>
      </div>
      <div class="hero-content">
        <p class="tagline">Fix, buy, sell, or trade your electronics in one place.</p>
        <div class="portal-links">
          <div class="cta3d" data-label="Services" data-url="/services.php"></div>
          <div class="cta3d" data-label="Buy" data-url="/buy.php"></div>
          <div class="cta3d" data-label="Trade" data-url="/trade.php"></div>
        </div>
      </div>
    </div>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
