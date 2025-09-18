<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/db.php';

$id = $_SESSION['user_id'];
$requests = [];
$stmt = $conn->prepare("SELECT id, category, make, model, status, created_at FROM service_requests WHERE user_id = ? AND type <> 'trade' ORDER BY created_at DESC");
if ($stmt === false) {
  error_log('Prepare failed: ' . $conn->error);
} else {
  $stmt->bind_param("i", $id);
  if (!$stmt->execute()) {
    error_log('Execute failed: ' . $stmt->error);
  } else {
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <title>My Service Requests</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>My Requests</h2>
  <p><a href="dashboard.php">← Back to Dashboard</a></p>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Category</th>
        <th>Make/Model</th>
        <th>Status</th>
        <th>Submitted</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['category']) ?></td>
        <td><?= htmlspecialchars($r['make']) . ' / ' . htmlspecialchars($r['model']) ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><?= $r['created_at'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
