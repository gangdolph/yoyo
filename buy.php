<?php
session_start();
require 'includes/db.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? '';
$limitParam = (int)($_GET['limit'] ?? 25);
$limitOptions = [25, 50, 100];
$limit = in_array($limitParam, $limitOptions) ? $limitParam : 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = "WHERE status='approved'";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (title LIKE ? OR description LIKE ? )";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($category !== '') {
    $where .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

$countSql = "SELECT COUNT(*) FROM listings $where";
$stmt = $conn->prepare($countSql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = (int)ceil($total / $limit);

$orderBy = 'title ASC';
if ($sort === 'price') {
    $orderBy = 'price ASC';
} elseif ($sort === 'latest') {
    $orderBy = 'created_at DESC';
}

$sql = "SELECT id, title, description, price, category, image FROM listings $where ORDER BY $orderBy LIMIT ? OFFSET ?";
$paramsLimit = $params;
$typesLimit = $types . 'ii';
$paramsLimit[] = $limit;
$paramsLimit[] = $offset;
$stmt = $conn->prepare($sql);
$stmt->bind_param($typesLimit, ...$paramsLimit);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Buy from SkuzE</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/buy.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Available Listings</h2>
  <div class="content">
    <aside class="filters">
      <form method="get">
        <input type="text" name="search" placeholder="Search" value="<?= htmlspecialchars($search) ?>">
        <select name="category">
          <option value="">All Categories</option>
          <option value="phone" <?= $category==='phone'?'selected':'' ?>>Phone</option>
          <option value="console" <?= $category==='console'?'selected':'' ?>>Game Console</option>
          <option value="pc" <?= $category==='pc'?'selected':'' ?>>PC</option>
          <option value="other" <?= $category==='other'?'selected':'' ?>>Other</option>
        </select>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="limit" value="<?= $limit ?>">
        <button type="submit">Filter</button>
      </form>
    </aside>
    <section class="listing-results">
      <div class="listing-toolbar">
        <div class="view-toggle">
          <button type="button" class="view-grid active" aria-label="Grid view">▥</button>
          <button type="button" class="view-list" aria-label="List view">≡</button>
        </div>
        <form method="get">
          <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
          <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
          <label>Sort by:
            <select name="sort" onchange="this.form.submit()">
              <option value="" <?= $sort===''?'selected':'' ?>>Default</option>
              <option value="price" <?= $sort==='price'?'selected':'' ?>>Price</option>
              <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Latest</option>
            </select>
          </label>
          <label>Show:
            <select name="limit" onchange="this.form.submit()">
              <option value="25" <?= $limit===25?'selected':'' ?>>25</option>
              <option value="50" <?= $limit===50?'selected':'' ?>>50</option>
              <option value="100" <?= $limit===100?'selected':'' ?>>100</option>
            </select>
          </label>
        </form>
      </div>
      <?php if ($listings): ?>
        <div class="product-grid" id="product-container">
        <?php foreach ($listings as $l): ?>
          <div class="product-card">
            <?php $link = "listing.php?listing_id={$l['id']}"; ?>
            <a href="<?= $link ?>" class="listing-link">
              <?php if ($l['image']): ?>
                <img class="thumb-square" src="uploads/<?= htmlspecialchars($l['image']) ?>" alt="">
              <?php endif; ?>
              <h3><?= htmlspecialchars($l['title']) ?></h3>
            </a>
            <?php
              $features = array_slice(array_filter(array_map('trim', explode("\n", $l['description']))), 0, 3);
              if ($features):
            ?>
              <ul class="product-features">
                <?php foreach ($features as $f): ?>
                  <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <p class="price">$<?= htmlspecialchars($l['price']) ?></p>
            <div class="rating">★★★★★</div>
            <button class="add-to-cart" data-id="<?= $l['id'] ?>">Add to Cart</button>
          </div>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p>No listings found. <a href="buy-step.php">Request a device</a></p>
      <?php endif; ?>
    </section>
  </div>
  <?php if ($totalPages > 1): ?>
    <nav class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
  <div id="cart-toast" class="toast" role="status" aria-live="polite"></div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
