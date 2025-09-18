<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/notifications.php';
require 'includes/orders.php';

$id = $_SESSION['user_id'];
$username = '';
$vip = 0;
$vip_expires = null;
$stmt = $conn->prepare("SELECT username, vip_status, vip_expires_at FROM users WHERE id = ?");
if ($stmt === false) {
  error_log('Prepare failed: ' . $conn->error);
} else {
  $stmt->bind_param("i", $id);
  if (!$stmt->execute()) {
    error_log('Execute failed: ' . $stmt->error);
  } else {
    $stmt->bind_result($username, $vip, $vip_expires);
    $stmt->fetch();
    $stmt->close();
  }
}
$vip_active = $vip && (!$vip_expires || strtotime($vip_expires) > time());
$unread_messages = count_unread_messages($conn, $id);
$unread_notifications = count_unread_notifications($conn, $id);

$my_products = [];
if ($stmt = $conn->prepare('SELECT sku, title, quantity, reorder_threshold FROM products WHERE owner_id = ?')) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $my_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}
$low_stock = array_filter($my_products, function($p) {
  return $p['reorder_threshold'] > 0 && $p['quantity'] <= $p['reorder_threshold'];
});
$orders = fetch_orders_for_user($conn, (int) $id);
?>
<?php require 'includes/layout.php'; ?>
  <title>Dashboard</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Welcome, <?= htmlspecialchars($username) ?></h2>
    <?php if ($vip_active): ?>
      <?php $expiresTs = strtotime($vip_expires); $days = floor(($expiresTs - time())/86400); ?>
      <?php if ($days <= 7): ?>
        <p class="notice">Your VIP membership expires on <?= htmlspecialchars($vip_expires) ?>. <a href="vip.php">Manage VIP</a>.</p>
      <?php endif; ?>
    <?php elseif ($vip): ?>
      <p class="notice">Your VIP membership expired on <?= htmlspecialchars($vip_expires) ?>. <a href="vip.php">Manage VIP</a>.</p>
    <?php endif; ?>
    <div class="nav-sections">
      <div class="nav-section">
        <h3>Service Requests</h3>
        <ul class="nav-links">
          <li><a class="btn" role="button" href="services.php">Start a Service Request</a></li>
          <li><a class="btn" role="button" href="my-requests.php">View My Service Requests</a></li>
          <li><a class="btn" role="button" href="my-listings.php">Manage My Listings</a></li>
        </ul>
      </div>
      <div class="nav-section">
        <h3>Communications</h3>
        <ul class="nav-links">
          <li><a class="btn" role="button" href="notifications.php">Notifications<?php if (!empty($unread_notifications)): ?> <span class="badge"><?= $unread_notifications ?></span><?php endif; ?></a></li>
          <li><a class="btn" role="button" href="messages.php">Messages<?php if (!empty($unread_messages)): ?> <span class="badge"><?= $unread_messages ?></span><?php endif; ?></a></li>
          <li><a class="btn" role="button" href="support.php">Support</a></li>
        </ul>
      </div>
      <div class="nav-section">
        <h3>Account</h3>
        <ul class="nav-links">
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <li><a class="btn" role="button" href="/admin/index.php">Admin Panel</a></li>
          <?php endif; ?>
          <li><a class="btn" role="button" href="profile.php">Edit Profile</a></li>
          <li><a class="btn" role="button" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
    <div class="nav-section">
      <h3>Inventory</h3>
      <?php if ($low_stock): ?>
      <p class="notice">Low stock: <?php $skus = array_map(function($p){return htmlspecialchars($p['sku']);}, $low_stock); echo implode(', ', $skus); ?></p>
      <?php endif; ?>
      <?php if ($my_products): ?>
      <table>
        <tr><th>SKU</th><th>Title</th><th>Qty</th><th>Threshold</th></tr>
        <?php foreach ($my_products as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['sku']) ?></td>
          <td><?= htmlspecialchars($p['title']) ?></td>
          <td><?= (int)$p['quantity'] ?></td>
          <td><?= (int)$p['reorder_threshold'] ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
      <p>No products.</p>
      <?php endif; ?>
    </div>
    <div class="nav-section">
      <h3>Orders</h3>
      <?php if ($orders): ?>
      <table class="orders-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Direction</th>
            <th>Product</th>
            <th>Inventory</th>
            <th>Official</th>
            <th>Payment</th>
            <th>Shipping</th>
            <th>Counterparty</th>
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
            $counterparty = $order['direction'] === 'buy'
              ? ($order['listing']['owner_username'] ?? 'Seller')
              : ($order['buyer']['username'] ?? 'Buyer');
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
            <td><?= (int) $order['product']['quantity'] ?></td>
            <td><span class="badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
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
            <td><?= htmlspecialchars($counterparty ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($order['placed_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><a href="order.php?id=<?= (int) $order['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p>No orders yet.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
