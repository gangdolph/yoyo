<?php
// Added shipping and refund policy detail for checkout and listing references.
if (!defined('HEADER_SKIP_AUTH')) {
    define('HEADER_SKIP_AUTH', true);
}
require __DIR__ . '/../includes/layout.php';
?>
  <title>Shipping, Returns &amp; Refunds</title>
</head><body><?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container policies-container">
  <h1>Shipping, Returns &amp; Refunds</h1>
  <h2>Shipping</h2>
  <ul>
    <li>Seller shipping profiles and costs are shown at checkout.</li>
    <li>Tracking is required for shipped orders.</li>
  </ul>
  <h2>Returns &amp; refunds</h2>
  <ul>
    <li>Return eligibility is shown on each listing.</li>
    <li>Refunds (full or partial) are itemized and reflected in your receipt and payout.</li>
  </ul>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
