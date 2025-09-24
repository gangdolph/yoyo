<?php
// Added fee breakdown reference page linked from checkout transparency callouts.
if (!defined('HEADER_SKIP_AUTH')) {
    define('HEADER_SKIP_AUTH', true);
}
$cfg = require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/layout.php';
?>
  <title>Fees &amp; Payouts</title>
</head><body><?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container policies-container">
  <h1>Fees &amp; Payouts</h1>
  <p><strong>Low-fee pledge:</strong> We show all fees up front, before you confirm. Receipts include a line-item fee breakdown.</p>
  <h2>Marketplace fees</h2>
  <ul>
    <li>Platform fee: <?php echo (float)($cfg['FEES_PERCENT'] ?? 2.0); ?>% + <?php echo number_format((int)($cfg['FEES_FIXED_CENTS'] ?? 0) / 100, 2); ?> per order (if applicable).</li>
    <li>Payment processor fees: shown at checkout and on your receipt (varies by method).</li>
    <li>No listing fee for standard listings. Featured/promoted placements are optional and clearly labeled.</li>
  </ul>
  <h2>Examples</h2>
  <p>$100.00 item → platform fee ≈ $<?php echo number_format(((float)($cfg['FEES_PERCENT'] ?? 2.0) / 100) * 100 + ((int)($cfg['FEES_FIXED_CENTS'] ?? 0) / 100), 2); ?> (plus processor fee, if any).</p>
  <h2>Payouts</h2>
  <ul>
    <li>Payout timing depends on payment capture/clearance. You’ll see an ETA in your Order details.</li>
    <li>Refunds reverse fees where applicable; details appear on the refund receipt.</li>
  </ul>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
