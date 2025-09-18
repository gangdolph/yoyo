<?php
require_once __DIR__ . '/../includes/auth.php';
require '../includes/orders.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: ../dashboard.php');
    exit;
}

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($orderId <= 0) {
    http_response_code(404);
    $order = null;
} else {
    $order = fetch_order_detail_for_admin($conn, $orderId, (int) $_SESSION['user_id']);
    if (!$order) {
        http_response_code(404);
    }
}

$amountDisplay = '—';
$paymentStatus = 'pending';
$shippingStatus = 'pending';
$badgeClass = 'badge-community';
$badgeLabel = 'Community Listing';
if ($order) {
    $amount = $order['payment']['amount'] ?? null;
    if ($amount !== null) {
        $amountDisplay = '$' . number_format(((int) $amount) / 100, 2);
    }
    $paymentStatus = $order['payment']['status'] ?? 'pending';
    $shippingStatus = $order['shipping_status'] ?? 'pending';
    $isOfficial = $order['product']['is_official'] ?? false;
    $badgeClass = $isOfficial ? 'badge-official' : 'badge-community';
    $badgeLabel = $isOfficial ? 'Official SkuzE' : 'Community Listing';
}
?>
<?php require '../includes/layout.php'; ?>
  <title>Admin · Order <?= $order ? '#' . htmlspecialchars((string) $order['id'], ENT_QUOTES, 'UTF-8') : '' ?></title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <div class="page-container order-detail">
    <h2>Order Review</h2>
    <p><a class="btn" href="orders.php">← Back to Orders</a></p>
    <?php if (!$order): ?>
      <p class="notice">Order not found.</p>
    <?php else: ?>
      <section class="order-summary">
        <h3>Summary</h3>
        <ul>
          <li><strong>Order ID:</strong> #<?= (int) $order['id'] ?></li>
          <li><strong>Placed:</strong> <?= htmlspecialchars($order['placed_at'], ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Direction:</strong> <?= htmlspecialchars(ucfirst($order['direction']), ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Buyer:</strong> <?= htmlspecialchars($order['buyer']['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Seller:</strong> <?= htmlspecialchars($order['listing']['owner_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Listing:</strong> <a href="../listing.php?id=<?= (int) $order['listing']['id'] ?>"><?= htmlspecialchars($order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
          <li><strong>Inventory Remaining:</strong> <?= (int) $order['product']['quantity'] ?></li>
          <li><strong>Reorder Threshold:</strong> <?= (int) $order['product']['reorder_threshold'] ?></li>
        </ul>
      </section>
      <section class="order-product">
        <h3>Product</h3>
        <p><span class="badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
        <ul>
          <li><strong>Title:</strong> <?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>SKU:</strong> <?= htmlspecialchars($order['product']['sku'], ENT_QUOTES, 'UTF-8') ?></li>
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
  <?php include '../includes/footer.php'; ?>
</body>
</html>
