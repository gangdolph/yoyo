<?php
/*
 * Discovery note: Admin dashboard organizes listings, trades, and system utilities but lacked status visibility.
 * Change: Extended navigation so the experimental manager workspace only appears when the rollout flag is enabled.
 * Change: Linked the wallet manager so finance tooling is one click away.
 */
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/db.php';
require '../includes/user.php';
$config = require __DIR__ . '/../config.php';

ensure_admin('../dashboard.php');

$managerEnabled = !empty($config['SHOP_MANAGER_V1_ENABLED']);

$stmt = $conn->query("SELECT r.id, u.id AS user_id, u.username, r.category, r.issue, r.created_at, r.status
                      FROM service_requests r
                      JOIN users u ON r.user_id = u.id
                      WHERE r.type <> 'trade'
                      ORDER BY r.created_at DESC");
$requests = $stmt->fetch_all(MYSQLI_ASSOC);
?>
<?php require '../includes/layout.php'; ?>
  <title>Admin Panel</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Admin Panel</h2>
  <div class="nav-sections">
    <div class="nav-section">
      <h3>Listings</h3>
      <ul class="nav-links">
        <li><a class="btn" href="listings.php">Review Listings</a></li>
        <?php if ($managerEnabled): ?>
          <li><a class="btn" href="manager.php">Shop Manager V1</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="nav-section">
      <h3>Trades</h3>
      <ul class="nav-links">
        <li><a class="btn" href="trade-requests.php">Review Trade Requests</a></li>
      </ul>
    </div>
    <div class="nav-section">
      <h3>System</h3>
      <ul class="nav-links">
        <li><a class="btn" href="discount-codes.php">Manage Discount Codes</a></li>
        <li><a class="btn" href="users.php">Manage Users</a></li>
        <li><a class="btn" href="theme.php">Vaporwave Theme Settings</a></li>
        <li><a class="btn" href="toolbox.php">Manage Toolbox</a></li>
        <li><a class="btn" href="service-taxonomy.php">Manage Brands &amp; Models</a></li>
        <li><a class="btn" href="wallet.php">Wallet Manager</a></li>
        <li><a class="btn" href="support.php">Support Tickets</a></li>
        <li><a class="btn" href="health.php">Square Health</a></li>
        <li><a class="btn" href="square-connection.php">Square Connection</a></li>
      </ul>
    </div>
  </div>
  <p class="back-link"><a class="btn" href="../dashboard.php">Back to Dashboard</a></p>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>User</th>
        <th>Category</th>
        <th>Issue</th>
        <th>Status</th>
        <th>Date</th>
        <th>View</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $req): ?>
        <tr>
          <td><?= $req['id'] ?></td>
          <td><?= username_with_avatar($conn, $req['user_id'], $req['username']) ?></td>
          <td><?= htmlspecialchars($req['category']) ?></td>
          <td><?= htmlspecialchars($req['issue']) ?></td>
          <td><?= htmlspecialchars($req['status'] ?? 'New') ?></td>
          <td><?= $req['created_at'] ?></td>
          <td><a href="view.php?id=<?= $req['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
