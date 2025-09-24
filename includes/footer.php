<?php
// Update: Footer now highlights transparency content and wallet access when enabled.
if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/../config.php';
}
$walletEnabled = !empty($config['SHOW_WALLET']);
?>
<footer>
  <div class="footer-nav">
    <nav class="footer-column">
      <h4>Marketplace</h4>
      <ul>
        <li><a href="/buy.php">Buy</a></li>
        <li><a href="/sell.php">Sell</a></li>
        <li><a href="/services.php">Services</a></li>
        <li><a href="/search.php">Search</a></li>
      </ul>
    </nav>
    <nav class="footer-column">
      <h4>Account</h4>
      <ul>
        <li><a href="/dashboard.php">Dashboard</a></li>
        <li><a href="/shop-manager/index.php">Shop Manager</a></li>
        <?php if ($walletEnabled): ?>
          <li><a href="/wallet.php">Wallet</a></li>
        <?php endif; ?>
        <li><a href="/messages.php">Messages</a></li>
        <li><a href="/support.php">Support</a></li>
        <li><a href="/logout.php">Logout</a></li>
      </ul>
    </nav>
    <nav class="footer-column">
      <h4>About</h4>
      <ul>
        <li><a href="/about.php">About Us</a></li>
        <li><a href="/help.php">Help</a></li>
        <li><a href="/support.php">Contact Support</a></li>
        <li><a href="/vip.php">VIP Plans</a></li>
        <li><a href="/terms.php">Terms</a></li>
        <li><a href="/privacy.php">Privacy</a></li>
        <li><a href="/policies/">Policies</a></li>
      </ul>
    </nav>
  </div>
  <p class="footer-copy">&copy; <?= date('Y') ?> SkuzE. All rights reserved.</p>
</footer>
<script src="assets/sidebar.js"></script>
