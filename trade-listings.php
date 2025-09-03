<?php
session_start();
require 'includes/db.php';
require 'includes/user.php';

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_GET['delete']) && $user_id) {
    $id = intval($_GET['delete']);
    if ($stmt = $conn->prepare('DELETE FROM trade_listings WHERE id = ? AND owner_id = ?')) {
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: trade-listings.php');
    exit;
}

$sql = 'SELECT tl.id, tl.have_item, tl.want_item, tl.status, tl.owner_id, u.username,
        (SELECT COUNT(*) FROM trade_offers o WHERE o.listing_id = tl.id AND o.status = "pending") AS pending
        FROM trade_listings tl JOIN users u ON tl.owner_id = u.id ORDER BY tl.created_at DESC';
$listings = [];
if ($result = $conn->query($sql)) {
    $listings = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Trade Listings</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Trade Listings</h2>
  <p><a href="trade-listing.php">Create new trade listing</a></p>
  <table>
    <tr><th>Have</th><th>Want</th><th>Status</th><th>Owner</th><th>Actions</th></tr>
    <?php foreach ($listings as $l): ?>
      <tr>
        <td><?= htmlspecialchars($l['have_item']) ?></td>
        <td><?= htmlspecialchars($l['want_item']) ?></td>
        <td><?= htmlspecialchars($l['status']) ?></td>
        <td><?= username_with_avatar($conn, $l['owner_id'], $l['username']) ?></td>
        <td>
          <?php if ($l['owner_id'] == $user_id): ?>
            <a href="trade-listing.php?edit=<?= $l['id'] ?>">Edit</a>
            <a href="trade-listings.php?delete=<?= $l['id'] ?>" onclick="return confirm('Delete listing?');">Delete</a>
            <a href="trade.php?listing=<?= $l['id'] ?>">Offers (<?= $l['pending'] ?>)</a>
          <?php elseif ($l['status'] === 'open' && $user_id): ?>
            <a href="trade-offer.php?id=<?= $l['id'] ?>">Make Offer</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
