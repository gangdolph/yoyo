<?php
// Update: Added auction rule callout leveraging config-driven transparency defaults.
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/tags.php';
require 'includes/listing-query.php';

$marketConfig = require __DIR__ . '/config.php';
$softCloseSeconds = (int)($marketConfig['AUCTION_SOFT_CLOSE_SECS'] ?? 120);
$incrementRules = $marketConfig['AUCTION_MIN_INCREMENT_TABLE'] ?? [];
$incrementSummary = [];
if (is_array($incrementRules)) {
    foreach ($incrementRules as $range => $increment) {
        $incrementSummary[] = sprintf('%s → $%s', $range, number_format((float)$increment, 2));
    }
}
$incrementText = $incrementSummary ? implode(', ', $incrementSummary) : 'See policy for current increments';

$filters = sanitize_buy_filters($_GET);
$search = $filters['search'];
$category = $filters['category'];
$subcategory = $filters['subcategory'];
$condition = $filters['condition'];
$sort = $filters['sort'];
$limit = $filters['limit'];
$page = $filters['page'];
$tagFilters = $filters['tags'];
$limitOptions = buy_limit_options();

$queryResult = run_buy_listing_query($conn, $filters);
$listings = $queryResult['items'];
$total = $queryResult['total'];
$totalPages = $queryResult['total_pages'];
$filters = $queryResult['filters'];
$page = $filters['page'];
$limit = $filters['limit'];

$availableTags = load_available_tags($conn, $tagFilters);

$categoryOptions = listing_filter_categories('buy');
$subcategoryOptions = listing_filter_subcategories('buy', $category);
$conditionOptions = listing_filter_conditions('buy', $category);
$sortOptions = buy_sort_options();

$baseQuery = [
    'search' => $search !== '' ? $search : null,
    'category' => $category !== '' ? $category : null,
    'subcategory' => $subcategory !== '' ? $subcategory : null,
    'condition' => $condition !== '' ? $condition : null,
    'sort' => $sort !== '' ? $sort : null,
    'limit' => $limit,
    'tags' => !empty($tagFilters) ? $tagFilters : null,
];

$baseQuery = array_filter($baseQuery, static function ($value) {
    if (is_array($value)) {
        return !empty($value);
    }

    return $value !== null;
});
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Buy from SkuzE</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/js/filters.js" defer></script>
  <script src="assets/buy.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Available Listings</h2>
  <aside class="policy-callout auction-policy-callout" aria-live="polite">
    <h3>Bidding Rules</h3>
    <p>Auctions use a <?= htmlspecialchars((string)$softCloseSeconds); ?>s soft close to deter sniping. Minimum increments: <?= htmlspecialchars($incrementText); ?>.</p>
    <p><a href="/policies/auctions.php">Full auctions policy</a></p>
  </aside>
  <div class="content">
    <form method="get" class="filter-bar" data-filter-form data-filter-context="buy" data-filter-endpoint="api/listings.php" data-filter-results="#buy-results" data-filter-status="#buy-filters-status">
      <div class="filter-bar__row">
      <div class="filter-field">
        <label for="buy-search">Search</label>
        <input type="search" id="buy-search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search listings" autocomplete="off" data-filter-input>
      </div>
        <div class="filter-field">
          <label for="buy-category">Category</label>
          <select name="category" id="buy-category" data-filter-dimension="category">
            <?php foreach ($categoryOptions as $value => $meta): ?>
              <option value="<?= htmlspecialchars($value); ?>" <?= $category === $value ? 'selected' : ''; ?>><?= htmlspecialchars($meta['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="buy-subcategory">Subcategory</label>
          <select name="subcategory" id="buy-subcategory" data-filter-dimension="subcategory" <?= ($category === '' || count($subcategoryOptions) <= 1) ? 'disabled' : ''; ?>>
            <?php foreach ($subcategoryOptions ?: ['' => ['label' => 'Select a category first']] as $value => $meta): ?>
              <option value="<?= htmlspecialchars($value); ?>" <?= $subcategory === $value ? 'selected' : ''; ?>><?= htmlspecialchars($meta['label'] ?? $meta); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="buy-condition">Condition</label>
          <select name="condition" id="buy-condition" data-filter-dimension="condition">
            <?php foreach ($conditionOptions as $value => $label): ?>
              <option value="<?= htmlspecialchars($value); ?>" <?= $condition === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="buy-sort">Sort</label>
          <select name="sort" id="buy-sort" data-filter-dimension="sort">
            <?php foreach ($sortOptions as $value => $label): ?>
              <option value="<?= htmlspecialchars($value); ?>" <?= $sort === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="buy-limit">Per page</label>
          <select name="limit" id="buy-limit" data-filter-dimension="limit">
            <?php foreach ($limitOptions as $option): ?>
              <option value="<?= $option; ?>" <?= $limit === $option ? 'selected' : ''; ?>><?= $option; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if ($availableTags): ?>
        <fieldset class="tag-filter" data-tag-filter>
          <legend>Tags</legend>
          <input type="search" class="tag-filter-search-input" placeholder="Search tags" aria-label="Search tags" autocomplete="off" data-tag-search list="buy-tag-datalist">
          <datalist id="buy-tag-datalist">
            <?php foreach ($availableTags as $tagOption): ?>
              <option value="<?= htmlspecialchars($tagOption); ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="tag-filter-options" data-tag-options>
            <?php foreach ($availableTags as $tag): ?>
              <label class="tag-filter-option" data-tag="<?= htmlspecialchars($tag); ?>">
                <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag); ?>" <?= in_array($tag, $tagFilters, true) ? 'checked' : ''; ?>>
                <span><?= htmlspecialchars($tag); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="tag-filter-empty" hidden>No tags match your search.</p>
        </fieldset>
      <?php endif; ?>
      <div class="filter-actions">
        <input type="hidden" name="context" value="buy" data-filter-context-value>
        <input type="hidden" name="page" value="<?= $page; ?>" data-filter-page>
        <button type="submit" class="btn">Apply filters</button>
        <span class="filter-status" id="buy-filters-status" role="status" aria-live="polite"></span>
      </div>
    </form>
    <section class="listing-results" id="buy-results" data-filter-results>
      <?php
        $resultCount = count($listings);
        $totalPages = $totalPages ?? 0;
        include __DIR__ . '/includes/partials/buy-results.php';
      ?>
    </section>
  </div>
  <div id="cart-toast" class="toast" role="status" aria-live="polite"></div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
