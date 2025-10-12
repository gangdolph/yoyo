<?php
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/repositories/ChangeRequestsService.php';
require_once __DIR__ . '/../includes/repositories/ListingsRepo.php';

ensure_admin('../dashboard.php');

// Handle approve/reject/close/delist actions
$error = '';
$changeRequests = new ChangeRequestsService($conn);
$listingsRepo = new ListingsRepo($conn, $changeRequests);
$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      if (isset($_POST['approve'])) {
        $listingsRepo->updateStatus($id, $adminId, 'approved', true);
      } elseif (isset($_POST['reject'])) {
        $listingsRepo->updateStatus($id, $adminId, 'delisted', true);
        $changeRequests->rejectOpenRequests($id, $adminId, 'Rejected by administrator');
      } elseif (isset($_POST['close'])) {
        $listingsRepo->updateStatus($id, $adminId, 'closed', true);
      } elseif (isset($_POST['delist'])) {
        $listingsRepo->updateStatus($id, $adminId, 'delisted', true);
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
          <?php elseif (in_array($l['status'], ['approved', 'live'], true)): ?>
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
