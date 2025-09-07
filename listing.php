<?php
session_start();
require 'includes/db.php';
require 'includes/csrf.php';

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
if (!$listing_id) {
    header('Location: buy.php');
    exit;
}

$stmt = $conn->prepare('SELECT l.id, l.product_sku, p.title, p.description, p.price, l.category, l.image FROM listings l JOIN products p ON l.product_sku = p.sku WHERE l.id = ? LIMIT 1');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();

if (!$listing) {
    http_response_code(404);
    echo 'Listing not found';
    exit;
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($listing['title']); ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="content listing-detail">
    <div class="listing-image">
      <?php if (!empty($listing['image'])): ?>
        <img class="thumb-square" src="uploads/<?= htmlspecialchars($listing['image']); ?>" alt="<?= htmlspecialchars($listing['title']); ?>">
      <?php endif; ?>
    </div>
    <section class="listing-info">
      <h2><?= htmlspecialchars($listing['title']); ?></h2>
      <p class="description">
        <?= nl2br(htmlspecialchars($listing['description'])); ?>
      </p>
      <p class="price">$<?= htmlspecialchars($listing['price']); ?></p>
    </section>
    <section class="listing-cta">
      <a class="btn" href="shipping.php?listing_id=<?= $listing['id']; ?>">Proceed to Checkout</a>
      <div class="related-items">
        <h3>Related Items</h3>
        <p>
          <a href="search.php?category=<?= urlencode($listing['category']); ?>">More in this category</a>
        </p>
      </div>
      <?php if (!empty($_SESSION['is_admin'])): ?>
        <form method="post" action="listing-delete.php" onsubmit="return confirm('Delete listing?');">
          <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
          <input type="hidden" name="id" value="<?= $listing['id']; ?>">
          <input type="hidden" name="redirect" value="buy.php">
          <button type="submit" class="btn">Delete Listing</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
