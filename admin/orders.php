<?php
require '../includes/auth.php';
require '../includes/orders.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: ../dashboard.php');
    exit;
}

$filter = $_GET['official'] ?? 'all';
$officialOnly = null;
if ($filter === 'official') {
    $officialOnly = true;
} elseif ($filter === 'community') {
    $officialOnly = false;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$orders = fetch_orders_for_admin($conn, $officialOnly, $userId);
?>
<?php require '../includes/layout.php'; ?>
  <title>Admin - Orders</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <div class="page-container">
    <h1>Orders</h1>
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
          <th>Official</th>
          <th>Inventory</th>
          <th>Buyer</th>
          <th>Seller</th>
          <th>Payment</th>
          <th>Shipping</th>
          <th>Placed</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $order): ?>
        <?php
          $isOfficial = $order['product']['is_official'];
          $badgeClass = $isOfficial ? 'badge-official' : 'badge-community';
          $badgeLabel = $isOfficial ? 'Official' : 'Community';
          $amount = $order['payment']['amount'];
          $amountDisplay = $amount !== null ? '$' . number_format(((int) $amount) / 100, 2) : '—';
          $paymentStatus = $order['payment']['status'] ?? 'pending';
          $shippingStatus = $order['shipping_status'] ?? 'pending';
        ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td class="order-direction order-direction-<?= htmlspecialchars($order['direction'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($order['direction']), ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <strong><?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8') ?></strong><br>
            <small><?= htmlspecialchars($order['product']['sku'], ENT_QUOTES, 'UTF-8') ?></small>
          </td>
          <td><span class="badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= (int) $order['product']['quantity'] ?></td>
          <td><?= htmlspecialchars($order['buyer']['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($order['listing']['owner_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?= htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8') ?><br>
            <small>Status: <?= htmlspecialchars(ucfirst($paymentStatus ?? 'pending'), ENT_QUOTES, 'UTF-8') ?></small>
          </td>
          <td>
            <?= htmlspecialchars(ucfirst($shippingStatus), ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($order['tracking_number'])): ?>
              <br><small>Tracking: <?= htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($order['placed_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><a href="order.php?id=<?= (int) $order['id'] ?>">Inspect</a></td>
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
