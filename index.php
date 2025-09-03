<?php
require __DIR__ . '/_debug_bootstrap.php';
session_start();
?>
<?php require 'includes/layout.php'; ?>
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
|____/|_|\\__,_/___|_____|
</pre>
      </div>
      <div class="hero-content">
        <h2>Repair. Modding. Modern Support.</h2>
        <p>Whether you're fixing, upgrading, or building — SkuzE has you covered.</p>
        <div class="cta-buttons">
          <div class="cta-row full">
            <a href="services.php" class="btn-cta">Services</a>
          </div>
          <div class="cta-row">
            <a href="buy.php" class="btn-cta">Buy</a>
            <a href="sell.php" class="btn-cta">Sell</a>
            <a href="trade.php" class="btn-cta">Trade</a>
          </div>
        </div>
      </div>
    </div>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
