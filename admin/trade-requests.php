<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/db.php';
require '../includes/user.php';

ensure_admin('../dashboard.php');

$stmt = $conn->query("SELECT r.id, u.id AS user_id, u.username, r.make, r.model, r.device_type, r.created_at, r.status
                      FROM service_requests r
                      JOIN users u ON r.user_id = u.id
                      WHERE r.type = 'trade'
                      ORDER BY r.created_at DESC");
$requests = $stmt->fetch_all(MYSQLI_ASSOC);
?>
<?php require '../includes/layout.php'; ?>
  <title>Trade Requests</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Trade Requests</h2>
  <p><a class="btn" href="index.php">Back to Admin Panel</a></p>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>User</th>
        <th>Current Device</th>
        <th>Desired Device</th>
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
          <td><?= htmlspecialchars($req['make']) . ' ' . htmlspecialchars($req['model']) ?></td>
          <td><?= htmlspecialchars($req['device_type']) ?></td>
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
