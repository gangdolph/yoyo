<?php
// Added transparency hub overview so public visitors can review core policies.
if (!defined('HEADER_SKIP_AUTH')) {
    define('HEADER_SKIP_AUTH', true);
}
require __DIR__ . '/../includes/layout.php';
?>
  <title>Policies & Transparency</title>
  <link rel="stylesheet" href="/assets/style.css">
</head><body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container policies-container">
  <h1>Policies & Transparency</h1>
  <p>We keep things simple: low fees, clear rules, and plain language. No gotchas.</p>
  <ul class="policies-links">
    <li><a href="/policies/fees.php">Fees &amp; Payouts</a></li>
    <li><a href="/policies/auctions.php">Auctions &amp; Bidding</a></li>
    <li><a href="/policies/trades.php">Trades &amp; Escrow</a></li>
    <li><a href="/policies/wallet.php">Wallet Balances &amp; Holds</a></li>
    <li><a href="/policies/shipping.php">Shipping, Returns &amp; Refunds</a></li>
    <li><a href="/policies/disputes.php">Disputes &amp; Enforcement</a></li>
  </ul>
  <p class="policies-updated">Last updated: <?php echo date('Y-m-d'); ?>. Not legal advice; contact support for clarifications.</p>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body></html>
