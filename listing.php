<?php
require 'includes/db.php';

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
if (!$listing_id) {
    header('Location: buy.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, title, description, price, category, image FROM listings WHERE id = ? LIMIT 1');
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
        <img src="uploads/<?= htmlspecialchars($listing['image']); ?>" alt="<?= htmlspecialchars($listing['title']); ?>">
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
      <a class="btn" href="checkout.php?listing_id=<?= $listing['id']; ?>">Proceed to Checkout</a>
      <div class="related-items">
        <h3>Related Items</h3>
        <p>
          <a href="search.php?category=<?= urlencode($listing['category']); ?>">More in this category</a>
        </p>
      </div>
    </section>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
