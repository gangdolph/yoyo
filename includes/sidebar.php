<?php
// Update: Surface policies and wallet access directly within the marketplace sidebar.
if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/../config.php';
}
$walletEnabled = !empty($config['SHOW_WALLET']);
?>
<button class="side-nav-toggle">☰</button>
<nav class="side-nav" aria-label="Primary navigation">
  <div class="side-nav-group" aria-labelledby="side-nav-marketplace">
    <h3 id="side-nav-marketplace" class="side-nav-title">Marketplace</h3>
    <ul aria-labelledby="side-nav-marketplace">
      <li><a href="services.php">Services</a></li>
      <li><a href="buy.php">Buy</a></li>
      <li><a href="sell.php">Sell</a></li>
      <li><a href="trade.php">Trade Offers</a></li>
      <li><a href="trade-listings.php">Trade Listings</a></li>
      <li><a href="trade-listing.php">Create Listing</a></li>
      <li><a href="promoted.php">Promoted Shops</a></li>
      <li><a href="shop-manager/index.php">Shop Manager</a></li>
      <?php if ($walletEnabled): ?>
        <li><a href="wallet.php">Wallet (Store Credit)</a></li>
      <?php endif; ?>
      <li><a href="member.php">Member Plans</a></li>
    </ul>
  </div>
  <div class="side-nav-group" aria-labelledby="side-nav-resources">
    <h3 id="side-nav-resources" class="side-nav-title">Resources</h3>
    <ul aria-labelledby="side-nav-resources">
      <li><a href="toolbox.php">Toolbox</a></li>
      <li><a href="forum/">Forum</a></li>
      <li><a href="terms.php">Terms</a></li>
      <li><a href="privacy.php">Privacy</a></li>
      <li><a href="support.php">Support</a></li>
      <li><a href="policies/">Policies</a></li>
    </ul>
  </div>
</nav>
