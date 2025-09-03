<?php
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/user.php';
require '../includes/csrf.php';

if (!$_SESSION['is_admin']) {
  header('Location: ../dashboard.php');
  exit;
}

// Handle approve/reject/close/delist actions
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      if (isset($_POST['approve'])) {
        $stmt = $conn->prepare("UPDATE listings SET status='approved' WHERE id=?");
      } elseif (isset($_POST['reject'])) {
        $stmt = $conn->prepare("UPDATE listings SET status='rejected' WHERE id=?");
      } elseif (isset($_POST['close'])) {
        $stmt = $conn->prepare("UPDATE listings SET status='closed' WHERE id=?");
      } elseif (isset($_POST['delist'])) {
        $stmt = $conn->prepare("UPDATE listings SET status='delisted' WHERE id=?");
      }
      if (isset($stmt)) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
      }
      header('Location: listings.php');
      exit;
    }
  }
}

$result = $conn->query("SELECT l.id, l.title, l.price, l.status, u.id AS user_id, u.username FROM listings l JOIN users u ON l.owner_id = u.id ORDER BY l.created_at DESC");
$listings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<?php require '../includes/layout.php'; ?>
  <title>Review Listings</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Listings Review</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
  <p><a class="btn" href="index.php">Back to Admin Panel</a></p>
  <table>
    <tr><th>ID</th><th>User</th><th>Title</th><th>Price</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($listings as $l): ?>
      <tr>
        <td><?= $l['id'] ?></td>
        <td><?= username_with_avatar($conn, $l['user_id'], $l['username']) ?></td>
        <td><?= htmlspecialchars($l['title']) ?></td>
        <td><?= htmlspecialchars($l['price']) ?></td>
        <td><?= htmlspecialchars($l['status']) ?></td>
        <td>
          <?php if ($l['status'] === 'pending'): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" name="approve">Approve</button>
            </form>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" name="reject">Reject</button>
            </form>
          <?php elseif ($l['status'] === 'approved'): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Close listing?');">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" name="close">Close</button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delist listing?');">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" name="delist">Delist</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
