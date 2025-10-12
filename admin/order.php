<?php
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require '../includes/orders.php';
require '../includes/csrf.php';

ensure_admin('../dashboard.php');

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
$badges = [];
$statusOptions = order_fulfillment_status_options();
$statusLabel = $statusOptions[$shippingStatus] ?? ucfirst($shippingStatus);
$flash = $_SESSION['order_admin_flash'] ?? null;
if ($flash) {
    unset($_SESSION['order_admin_flash']);
}
if ($order) {
    $amount = $order['payment']['amount'] ?? null;
    if ($amount !== null) {
        $amountDisplay = '$' . number_format(((int) $amount) / 100, 2);
    }
    $paymentStatus = $order['payment']['status'] ?? 'pending';
    $shippingStatus = $order['shipping_status'] ?? 'pending';
    $statusLabel = $statusOptions[$shippingStatus] ?? ucfirst($shippingStatus);
    if (!empty($order['listing']['is_official']) || !empty($order['product']['is_skuze_official']) || !empty($order['is_official_order'])) {
        $badges[] = ['class' => 'badge-official', 'label' => 'SkuzE Official'];
    }
    if (!empty($order['product']['is_skuze_product'])) {
        $badges[] = ['class' => 'badge-product', 'label' => 'SkuzE Product'];
    }
    if (!$badges) {
        $badges[] = ['class' => 'badge-community', 'label' => 'Community Listing'];
    }
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
    <?php if ($flash): ?>
      <div class="alert <?= htmlspecialchars($flash['type'] ?? 'success', ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
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
          <li><strong>Listing:</strong> <a href="../listing.php?listing_id=<?= (int) $order['listing']['id'] ?>"><?= htmlspecialchars($order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
          <li><strong>Inventory Remaining:</strong> <?= (int) ($order['product']['stock'] ?? 0) ?></li>
          <li><strong>Reorder Threshold:</strong> <?= (int) $order['product']['reorder_threshold'] ?></li>
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
          <li><strong>Shipping status:</strong> <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></li>
          <?php if (!empty($order['tracking_number'])): ?>
          <li><strong>Tracking number:</strong> <?= htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8') ?></li>
          <?php endif; ?>
          <?php if (!empty($order['notes'])): ?>
          <li><strong>Notes:</strong> <?= nl2br(htmlspecialchars($order['notes'], ENT_QUOTES, 'UTF-8')) ?></li>
          <?php endif; ?>
        </ul>
      </section>
      <section class="order-admin-actions">
        <h3>Update Fulfillment</h3>
        <form method="post" action="order-update.php" class="order-admin-form">
          <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
          <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
          <input type="hidden" name="context" value="detail">
          <div class="form-row">
            <label for="status">Fulfillment status</label>
            <select id="status" name="status" required>
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $shippingStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label for="tracking_number">Tracking number</label>
            <input type="text" id="tracking_number" name="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="off">
            <small class="help-text">Leave blank to clear the tracking number.</small>
          </div>
          <div class="form-row">
            <label for="inventory_delta">Inventory adjustment</label>
            <input type="number" id="inventory_delta" name="inventory_delta" value="0" step="1">
            <small class="help-text">Positive numbers restock units, negative numbers deduct from stock.</small>
          </div>
          <div class="form-row checkbox">
            <label>
              <input type="checkbox" name="auto_restock" value="1">
              Auto-restock one unit when cancelling this order.
            </label>
          </div>
          <div class="form-row">
            <button type="submit" class="btn">Save changes</button>
          </div>
        </form>
      </section>
    <?php endif; ?>
  </div>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
