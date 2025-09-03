<?php
require 'includes/auth.php';
require 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Handle deletion
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $stmt = $conn->prepare("DELETE FROM listings WHERE id = ? AND owner_id = ?");
  if ($stmt) {
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $stmt->close();
  }
  header('Location: my-listings.php');
  exit;
}

$stmt = $conn->prepare("SELECT id, title, price, category, image, status, created_at FROM listings WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php require 'includes/layout.php'; ?>
  <title>My Listings</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>My Listings</h2>
  <p><a href="sell.php">Create a new listing</a></p>
  <table>
    <tr><th>Title</th><th>Price</th><th>Category</th><th>Image</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($listings as $l): ?>
      <tr>
        <td><?= htmlspecialchars($l['title']) ?></td>
        <td><?= htmlspecialchars($l['price']) ?></td>
        <td><?= htmlspecialchars($l['category']) ?></td>
        <td><?php if ($l['image']): ?><img class="thumb-square" style="--thumb-size:60px;" src="uploads/<?= htmlspecialchars($l['image']) ?>" alt=""><?php endif; ?></td>
        <td><?= htmlspecialchars($l['status']) ?></td>
        <td><a href="?delete=<?= $l['id'] ?>" onclick="return confirm('Delete listing?');">Delete</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
