<?php
require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
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
            ◢████████████████████████████████◣
        ◢◤╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱◥◣
      ◢◤  ██████╗   ██╗  ██╗  ██╗   ██╗  ███████╗  ███████╗  ◥◣
     ╱╲   ██╔══██╗  ██║ ██╔╝  ██║   ██║  ╚══███╔╝  ██╔════╝   ╱╲
    ╱  ╲  ╚█████╔╝  █████╔╝   ██║   ██║    ███╔╝   █████╗     ╱  ╲
    ╲  ╱   ╚═══██╗  ██╔═██╗   ██║   ██║   ███╔╝    ██╔══╝     ╲  ╱
     ╲╱   ██████╔╝  ██║  ██╗  ╚██████╔╝  ███████╗  ███████╗    ╲╱
      ◥◣  ╚═════╝   ╚═╝  ╚═╝   ╚═════╝   ╚══════╝  ╚══════╝   ◢◤
        ◥◣╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱╲╱◢◤
            ◥███████████████████████████████◤
        </pre>
      </div>
      <div class="hero-content">
        <p class="tagline">Fix, buy, sell, or trade your electronics in one place.</p>
        <div class="portal-links">
          <a class="cta-glass" href="/services.php">Services</a>
          <a class="cta-glass" href="/buy.php">Buy</a>
          <a class="cta-glass" href="/sell.php">Sell</a>
          <a class="cta-glass" href="/trade.php">Trade</a>
        </div>
      </div>
    </div>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
