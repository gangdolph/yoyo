<?php
// Added bidding rules reference page to back auction UI hints.
if (!defined('HEADER_SKIP_AUTH')) {
    define('HEADER_SKIP_AUTH', true);
}
$cfg = require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/layout.php';
?>
  <title>Auctions &amp; Bidding Rules</title>
</head><body><?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container policies-container">
  <h1>Auctions &amp; Bidding</h1>
  <h2>How bidding works</h2>
  <ul>
    <li>Proxy bidding: enter your max; we auto-bid the minimum needed to lead.</li>
    <li>Reserve price (if set): the lot must reach a hidden minimum to sell (we disclose whether reserve is met).</li>
    <li>Minimum increments: shown on the bid form for each price range.</li>
    <li>Soft close: bids in the last seconds extend the auction by <?php echo (int)($cfg['AUCTION_SOFT_CLOSE_SECS'] ?? 120); ?>s to discourage sniping.</li>
  </ul>
  <h2>Eligibility &amp; deposits</h2>
  <p>High-value lots may require a wallet/card hold. You’ll see this requirement before placing a bid.</p>
  <h2>Fair play</h2>
  <ul>
    <li>No shill bidding. No collusion. We may void bids and take action on suspicious activity.</li>
    <li>Bid retractions are limited; abuse may lead to restrictions.</li>
  </ul>
  <h2>Fees</h2>
  <p>Same <a href="/policies/fees.php">low-fee policy</a>. Any buyer premiums or surcharges are disclosed up front (we try to avoid them).</p>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
