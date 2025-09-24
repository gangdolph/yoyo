<?php
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/orders.php';
require '../includes/csrf.php';

ensure_admin('../dashboard.php');

$filter = $_GET['official'] ?? 'all';
$officialOnly = null;
if ($filter === 'official') {
    $officialOnly = true;
} elseif ($filter === 'community') {
    $officialOnly = false;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$orders = fetch_orders_for_admin($conn, $officialOnly, $userId);
$statusOptions = order_fulfillment_status_options();
$flash = $_SESSION['order_admin_flash'] ?? null;
if ($flash) {
    unset($_SESSION['order_admin_flash']);
}
?>
<?php require '../includes/layout.php'; ?>
  <title>Admin - Orders</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <div class="page-container">
    <h1>Orders</h1>
    <?php if ($flash): ?>
      <div class="alert <?= htmlspecialchars($flash['type'] ?? 'success', ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <form method="get" class="filter-form">
      <label for="official">Inventory:</label>
      <select id="official" name="official" onchange="this.form.submit()">
        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
        <option value="official" <?= $filter === 'official' ? 'selected' : '' ?>>Official only</option>
        <option value="community" <?= $filter === 'community' ? 'selected' : '' ?>>Community only</option>
      </select>
      <noscript><button type="submit">Apply</button></noscript>
    </form>
    <?php if ($orders): ?>
    <table class="orders-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Direction</th>
          <th>Product</th>
          <th>Badges</th>
          <th>Inventory</th>
          <th>Buyer</th>
          <th>Seller</th>
          <th>Payment</th>
          <th>Shipping</th>
          <th>Placed</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $order): ?>
        <?php
          $badges = [];
          if (!empty($order['listing']['is_official']) || !empty($order['product']['is_skuze_official']) || !empty($order['is_official_order'])) {
              $badges[] = ['class' => 'badge-official', 'label' => 'SkuzE Official'];
          }
          if (!empty($order['product']['is_skuze_product'])) {
              $badges[] = ['class' => 'badge-product', 'label' => 'SkuzE Product'];
          }
          if (!$badges) {
              $badges[] = ['class' => 'badge-community', 'label' => 'Community'];
          }
          $amount = $order['payment']['amount'];
          $amountDisplay = $amount !== null ? '$' . number_format(((int) $amount) / 100, 2) : '—';
          $paymentStatus = $order['payment']['status'] ?? 'pending';
          $shippingStatus = $order['shipping_status'] ?? 'pending';
          $statusLabel = $statusOptions[$shippingStatus] ?? ucfirst($shippingStatus);
          $statusBadge = 'badge-status-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($shippingStatus));
        ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td class="order-direction order-direction-<?= htmlspecialchars($order['direction'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($order['direction']), ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <strong><?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></strong><br>
            <small><?= htmlspecialchars($order['product']['sku'], ENT_QUOTES, 'UTF-8') ?></small>
          </td>
          <td>
            <?php foreach ($badges as $badge): ?>
              <span class="badge <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
          </td>
          <td><?= (int) ($order['product']['stock'] ?? 0) ?></td>
          <td><?= htmlspecialchars($order['buyer']['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($order['listing']['owner_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?= htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8') ?><br>
            <small>Status: <?= htmlspecialchars(ucfirst($paymentStatus ?? 'pending'), ENT_QUOTES, 'UTF-8') ?></small>
          </td>
          <td>
            <span class="badge <?= htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!empty($order['tracking_number'])): ?>
              <br><small>Tracking: <?= htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($order['placed_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="order-actions">
            <div class="quick-actions">
              <?php if (!in_array($shippingStatus, ['shipped', 'delivered'], true)): ?>
                <form method="post" action="order-update.php">
                  <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                  <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                  <input type="hidden" name="context" value="list">
                  <input type="hidden" name="status" value="shipped">
                  <button type="submit" class="btn btn-compact">Mark shipped</button>
                </form>
              <?php endif; ?>
              <?php if ($shippingStatus !== 'delivered'): ?>
                <form method="post" action="order-update.php">
                  <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                  <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                  <input type="hidden" name="context" value="list">
                  <input type="hidden" name="status" value="delivered">
                  <button type="submit" class="btn btn-compact">Mark delivered</button>
                </form>
              <?php endif; ?>
              <?php if ($shippingStatus !== 'cancelled'): ?>
                <form method="post" action="order-update.php" onsubmit="return confirm('Cancel this order and restock one unit?');">
                  <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                  <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                  <input type="hidden" name="context" value="list">
                  <input type="hidden" name="status" value="cancelled">
                  <input type="hidden" name="auto_restock" value="1">
                  <button type="submit" class="btn btn-compact btn-secondary">Cancel &amp; restock</button>
                </form>
              <?php endif; ?>
              <a class="btn btn-compact" href="order.php?id=<?= (int) $order['id'] ?>">Inspect</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p>No orders found for the selected filter.</p>
    <?php endif; ?>
  </div>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
