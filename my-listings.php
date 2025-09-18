<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/tags.php';

$user_id = $_SESSION['user_id'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request token.';
  } else {
    $listing_id = (int)($_POST['listing_id'] ?? 0);
    $tags_input = trim($_POST['tags'] ?? '');
    $tags = tags_from_input($tags_input);
    $tags_storage = tags_to_storage($tags);
    if ($listing_id > 0) {
      if ($stmt = $conn->prepare("UPDATE listings SET tags=? WHERE id=? AND owner_id=?")) {
        $stmt->bind_param('sii', $tags_storage, $listing_id, $user_id);
        if ($stmt->execute()) {
          if ($stmt->affected_rows > 0) {
            $message = 'Tags updated.';
          } else {
            $message = 'No changes were made.';
          }
        } else {
          $error = 'Failed to update tags.';
        }
        $stmt->close();
      } else {
        $error = 'Failed to prepare tag update.';
      }
    }
  }
}

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

$stmt = $conn->prepare("SELECT id, title, price, category, tags, image, status, pickup_only, created_at FROM listings WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php require 'includes/layout.php'; ?>
  <title>My Listings</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/tags.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>My Listings</h2>
  <?php if ($message): ?>
    <p class="notice"><?= htmlspecialchars($message); ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error); ?></p>
  <?php endif; ?>
  <p><a href="sell.php">Create a new listing</a></p>
  <table class="table-listings">
    <tr><th>Title</th><th>Price</th><th>Category</th><th>Tags</th><th>Image</th><th>Status</th><th>Pickup Only</th><th>Actions</th></tr>
    <?php foreach ($listings as $l): ?>
      <?php $tagValues = tags_from_storage($l['tags']); ?>
      <tr>
        <td><?= htmlspecialchars($l['title']) ?></td>
        <td><?= htmlspecialchars($l['price']) ?></td>
        <td><?= htmlspecialchars($l['category']) ?></td>
        <td class="listing-tags">
          <div class="tag-badges">
            <?php if ($tagValues): ?>
              <?php foreach ($tagValues as $tag): ?>
                <span class="tag-chip tag-chip-static"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="tag-empty">No tags</span>
            <?php endif; ?>
          </div>
          <form method="post" class="tag-edit-form">
            <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
            <input type="hidden" name="listing_id" value="<?= $l['id']; ?>">
            <div class="tag-input" data-tag-editor>
              <div class="tag-list" data-tag-list></div>
              <input type="text" data-tag-source placeholder="Add tag">
              <input type="hidden" name="tags" value="<?= htmlspecialchars(tags_to_input_value($tagValues)); ?>" data-tag-store>
            </div>
            <button type="submit" class="btn-secondary">Save Tags</button>
          </form>
        </td>
        <td><?php if ($l['image']): ?><img class="thumb-square" style="--thumb-size:60px;" src="uploads/<?= htmlspecialchars($l['image']) ?>" alt=""><?php endif; ?></td>
        <td><?= htmlspecialchars($l['status']) ?></td>
        <td><?= $l['pickup_only'] ? 'Yes' : 'No' ?></td>
        <td><a href="?delete=<?= $l['id'] ?>" onclick="return confirm('Delete listing?');">Delete</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
