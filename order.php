<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/orders.php';

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($orderId <= 0) {
    http_response_code(404);
    $order = null;
} else {
    $order = fetch_order_detail_for_user($conn, $orderId, (int) $_SESSION['user_id']);
    if (!$order) {
        http_response_code(404);
    }
}

$amountDisplay = '—';
$paymentStatus = 'pending';
$shippingStatus = 'pending';
$badgeClass = 'badge-community';
$badgeLabel = 'Community Listing';
$counterparty = null;
if ($order) {
    $amount = $order['payment']['amount'] ?? null;
    if ($amount !== null) {
        $amountDisplay = '$' . number_format(((int) $amount) / 100, 2);
    }
    $paymentStatus = $order['payment']['status'] ?? 'pending';
    $shippingStatus = $order['shipping_status'] ?? 'pending';
$badges = [];
$isOfficialListing = !empty($order['listing']['is_official']) || !empty($order['product']['is_skuze_official']) || !empty($order['is_official_order']);
if ($isOfficialListing) {
    $badges[] = ['class' => 'badge-official', 'label' => 'SkuzE Official'];
}
if (!empty($order['product']['is_skuze_product'])) {
    $badges[] = ['class' => 'badge-product', 'label' => 'SkuzE Product'];
}
if (!$badges) {
    $badges[] = ['class' => 'badge-community', 'label' => 'Community Listing'];
}
    $counterparty = $order['direction'] === 'buy'
        ? ($order['listing']['owner_username'] ?? 'Seller')
        : ($order['buyer']['username'] ?? 'Buyer');
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Order <?= $order ? '#' . htmlspecialchars((string) $order['id'], ENT_QUOTES, 'UTF-8') : '' ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container order-detail">
    <h2>Order Details</h2>
    <?php if (!$order): ?>
      <p class="notice">We couldn’t find that order or you do not have permission to view it.</p>
      <p><a class="btn" href="dashboard.php">Return to dashboard</a></p>
    <?php else: ?>
      <p><a class="btn" href="dashboard.php">← Back to dashboard</a></p>
      <section class="order-summary">
        <h3>Summary</h3>
        <ul>
          <li><strong>Order ID:</strong> #<?= (int) $order['id'] ?></li>
          <li><strong>Placed:</strong> <?= htmlspecialchars($order['placed_at'], ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Direction:</strong> <?= htmlspecialchars(ucfirst($order['direction']), ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Counterparty:</strong> <?= htmlspecialchars($counterparty ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Listing:</strong> <a href="listing.php?listing_id=<?= (int) $order['listing']['id'] ?>"><?= htmlspecialchars($order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
          <li><strong>Status:</strong> <?= htmlspecialchars(ucfirst($shippingStatus), ENT_QUOTES, 'UTF-8') ?></li>
        </ul>
      </section>
      <section class="order-product">
        <h3>Product</h3>
        <p>
          <?php foreach ($badges as $badge): ?>
            <span class="badge <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endforeach; ?>
        </p>
        <ul>
          <li><strong>Title:</strong> <?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>SKU:</strong> <?= htmlspecialchars($order['product']['sku'], ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Inventory remaining:</strong> <?= (int) ($order['product']['stock'] ?? 0) ?></li>
          <li><strong>Reorder threshold:</strong> <?= (int) $order['product']['reorder_threshold'] ?></li>
        </ul>
      </section>
      <section class="order-payment">
        <h3>Payment</h3>
        <ul>
          <li><strong>Amount:</strong> <?= htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Status:</strong> <?= htmlspecialchars(ucfirst($paymentStatus ?? 'pending'), ENT_QUOTES, 'UTF-8') ?></li>
          <?php if (!empty($order['payment']['reference'])): ?>
          <li><strong>Reference:</strong> <?= htmlspecialchars($order['payment']['reference'], ENT_QUOTES, 'UTF-8') ?></li>
          <?php endif; ?>
          <?php if (!empty($order['payment']['created_at'])): ?>
          <li><strong>Captured:</strong> <?= htmlspecialchars($order['payment']['created_at'], ENT_QUOTES, 'UTF-8') ?></li>
          <?php endif; ?>
        </ul>
      </section>
      <section class="order-shipping">
        <h3>Fulfillment</h3>
        <ul>
          <li><strong>Delivery method:</strong> <?= htmlspecialchars($order['delivery_method'] ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Shipping status:</strong> <?= htmlspecialchars(ucfirst($shippingStatus), ENT_QUOTES, 'UTF-8') ?></li>
          <?php if (!empty($order['tracking_number'])): ?>
          <li><strong>Tracking number:</strong> <?= htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8') ?></li>
          <?php endif; ?>
          <?php if (!empty($order['notes'])): ?>
          <li><strong>Notes:</strong> <?= nl2br(htmlspecialchars($order['notes'], ENT_QUOTES, 'UTF-8')) ?></li>
          <?php endif; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
