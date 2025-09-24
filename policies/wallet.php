<?php
// Added wallet policy explainer for balance/hold messaging reuse.
if (!defined('HEADER_SKIP_AUTH')) {
    define('HEADER_SKIP_AUTH', true);
}
$cfg = require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/layout.php';
?>
  <title>Wallet Balances &amp; Holds</title>
</head><body><?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container policies-container">
  <h1>Wallet Balances &amp; Holds</h1>
  <ul>
    <li>Use wallet funds for purchases, bids, and escrow.</li>
    <li>Minimum withdrawal: $<?php echo number_format(((int)($cfg['WALLET_WITHDRAW_MIN_CENTS'] ?? 100)) / 100, 2); ?>.</li>
    <li>Temporary holds (e.g., bid deposits) auto-release within <?php echo (int)($cfg['WALLET_HOLD_HOURS'] ?? 24); ?>h unless converted to payment.</li>
  </ul>
  <p>We show every fee/hold before you confirm and on receipts.</p>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
