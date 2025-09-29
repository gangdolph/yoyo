<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/user.php';
require 'includes/csrf.php';
require 'includes/listing-query.php';

$user_id = $_SESSION['user_id'] ?? null;

$filters = sanitize_trade_filters($_GET, $conn);
$search = $filters['search'];
$category = $filters['category'];
$subcategory = $filters['subcategory'];
$condition = $filters['condition'];
$tradeType = $filters['trade_type'];
$brandId = isset($filters['brand_id']) ? (int)$filters['brand_id'] : 0;
$modelId = isset($filters['model_id']) ? (int)$filters['model_id'] : 0;
$sort = $filters['sort'];
$limit = $filters['limit'];
$page = $filters['page'];

$queryResult = run_trade_listing_query($conn, $filters);
$listings = $queryResult['items'];
$total = $queryResult['total'];
$totalPages = $queryResult['total_pages'];
$filters = $queryResult['filters'];
$page = $filters['page'];
$limit = $filters['limit'];
$brandId = isset($filters['brand_id']) ? (int)$filters['brand_id'] : 0;
$modelId = isset($filters['model_id']) ? (int)$filters['model_id'] : 0;

$categoryOptions = listing_filter_categories('trade');
$subcategoryOptions = listing_filter_subcategories('trade', $category);
$conditionOptions = listing_filter_conditions('trade', $category);
$formatOptions = listing_filter_format_options('trade');
$brandOptions = listing_brand_options($conn);
$modelIndex = listing_model_index($conn);
$sortOptions = trade_sort_options();
$limitOptions = trade_limit_options();

$baseQuery = [
    'search' => $search !== '' ? $search : null,
    'category' => $category !== '' ? $category : null,
    'subcategory' => $subcategory !== '' ? $subcategory : null,
    'condition' => $condition !== '' ? $condition : null,
    'trade_type' => $tradeType !== '' ? $tradeType : null,
    'brand_id' => $brandId > 0 ? $brandId : null,
    'model_id' => $modelId > 0 ? $modelId : null,
    'sort' => $sort !== '' ? $sort : null,
    'limit' => $limit,
];

$baseQuery = array_filter($baseQuery, static function ($value) {
    return $value !== null;
});
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Trade Listings</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/js/filters.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Trade Listings</h2>
  <p>
    <a href="trade.php">Trade Offers</a> |
    <a href="trade-listing.php">Create Listing</a>
  </p>
  <form method="get" class="filter-bar" data-filter-form data-filter-context="trade" data-filter-endpoint="api/listings.php" data-filter-results="#trade-results" data-filter-status="#trade-filters-status">
    <div class="filter-bar__row">
      <div class="filter-field">
        <label for="trade-search">Search</label>
        <input type="search" id="trade-search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search trades" autocomplete="off" data-filter-input>
      </div>
      <div class="filter-field">
        <label for="trade-category">Category</label>
        <select name="category" id="trade-category" data-filter-dimension="category">
          <?php foreach ($categoryOptions as $value => $meta): ?>
            <option value="<?= htmlspecialchars($value); ?>" <?= $category === $value ? 'selected' : ''; ?>><?= htmlspecialchars($meta['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-subcategory">Subcategory</label>
        <select name="subcategory" id="trade-subcategory" data-filter-dimension="subcategory" <?= ($category === '' || count($subcategoryOptions) <= 1) ? 'disabled' : ''; ?>>
          <?php foreach ($subcategoryOptions ?: ['' => ['label' => 'Select a category first']] as $value => $meta): ?>
            <option value="<?= htmlspecialchars($value); ?>" <?= $subcategory === $value ? 'selected' : ''; ?>><?= htmlspecialchars($meta['label'] ?? $meta); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-condition">Status</label>
        <select name="condition" id="trade-condition" data-filter-dimension="condition">
          <?php foreach ($conditionOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value); ?>" <?= $condition === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-brand">Brand</label>
        <select name="brand_id" id="trade-brand" data-filter-dimension="brand_id">
          <option value="">Any brand</option>
          <?php foreach ($brandOptions as $id => $name): ?>
            <option value="<?= $id; ?>" <?= $brandId === (int)$id ? 'selected' : ''; ?>><?= htmlspecialchars($name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-model">Model</label>
        <select name="model_id" id="trade-model" data-filter-dimension="model_id" <?= empty($modelIndex) ? 'disabled' : ''; ?>>
          <option value="">Any model</option>
          <?php foreach ($modelIndex as $model): ?>
            <?php
              $brandLabel = $brandOptions[$model['brand_id']] ?? ('Brand ' . $model['brand_id']);
              $modelLabel = $brandLabel . ' – ' . $model['name'];
            ?>
            <option value="<?= $model['id']; ?>" data-brand-id="<?= $model['brand_id']; ?>" <?= $modelId === (int)$model['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($modelLabel); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-format">Format</label>
        <select name="trade_type" id="trade-format" data-filter-dimension="trade_type">
          <?php foreach ($formatOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value); ?>" <?= $tradeType === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-sort">Sort</label>
        <select name="sort" id="trade-sort" data-filter-dimension="sort">
          <?php foreach ($sortOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value); ?>" <?= $sort === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="trade-limit">Per page</label>
        <select name="limit" id="trade-limit" data-filter-dimension="limit">
          <?php foreach ($limitOptions as $option): ?>
            <option value="<?= $option; ?>" <?= $limit === $option ? 'selected' : ''; ?>><?= $option; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filter-actions">
      <input type="hidden" name="context" value="trade" data-filter-context-value>
      <input type="hidden" name="page" value="<?= $page; ?>" data-filter-page>
      <button type="submit" class="btn">Apply filters</button>
      <span class="filter-status" id="trade-filters-status" role="status" aria-live="polite"></span>
    </div>
  </form>
  <section class="listing-results" id="trade-results" data-filter-results>
    <?php
      $resultCount = count($listings);
      include __DIR__ . '/includes/partials/trade-results.php';
    ?>
  </section>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
