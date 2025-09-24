<?php
// Added trade policy explainer so swap flows reference a consistent rulebook.
if (!defined('HEADER_SKIP_AUTH')) {
    define('HEADER_SKIP_AUTH', true);
}
require __DIR__ . '/../includes/layout.php';
?>
  <title>Trades &amp; Escrow</title>
</head><body><?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container policies-container">
  <h1>Trades &amp; Escrow</h1>
  <h2>Inventory-backed swaps</h2>
  <p>Trades can involve items, cash top-ups, or both. Our system checks inventory and logs adjustments.</p>
  <h2>Escrow (optional)</h2>
  <ul>
    <li>Funds/items are held until both sides confirm receipt.</li>
    <li>Disputes pause the release and follow our <a href="/policies/disputes.php">dispute process</a>.</li>
  </ul>
  <h2>Transparency</h2>
  <ul>
    <li>Any fees are shown before you submit an offer.</li>
    <li>Change requests to live listings require approval; audit trails are kept.</li>
  </ul>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
