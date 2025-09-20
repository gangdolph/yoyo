<?php
/**
 * @var array $listings
 * @var array $baseQuery
 * @var int   $page
 * @var int   $totalPages
 * @var int   $total
 * @var int   $limit
 * @var int   $resultCount
 */
?>
<div class="listing-toolbar">
  <div class="view-toggle" role="group" aria-label="View options">
    <button type="button" class="view-grid active" aria-label="Grid view">▥</button>
    <button type="button" class="view-list" aria-label="List view">≡</button>
  </div>
  <p class="listing-summary" aria-live="polite">
    <?php if ($total > 0): ?>
      <?php
        $rangeStart = (($page - 1) * $limit) + 1;
        $rangeEnd = $rangeStart + $resultCount - 1;
        if ($rangeEnd > $total) {
            $rangeEnd = $total;
        }
      ?>
      Showing <?= $rangeStart; ?>–<?= $rangeEnd; ?> of <?= $total; ?> listings
    <?php else: ?>
      No listings match your filters yet.
    <?php endif; ?>
  </p>
</div>
<?php if ($listings): ?>
  <div class="product-grid" id="product-container">
    <?php foreach ($listings as $l): ?>
      <?php $link = "listing.php?listing_id={$l['id']}"; ?>
      <div class="product-card">
        <a href="<?= $link ?>" class="listing-link">
          <?php if (!empty($l['image'])): ?>
            <img class="thumb-square" src="uploads/<?= htmlspecialchars($l['image']); ?>" alt="">
          <?php endif; ?>
          <h3><?= htmlspecialchars($l['title']); ?></h3>
        </a>
        <?php
          $badges = [];
          if (!empty($l['is_official_listing']) || !empty($l['is_skuze_official'])) {
            $badges[] = ['class' => 'badge-official', 'label' => 'SkuzE Official'];
          }
          if (!empty($l['is_skuze_product'])) {
            $badges[] = ['class' => 'badge-product', 'label' => 'SkuzE Product'];
          }
        ?>
        <?php if ($badges): ?>
          <div class="listing-badges">
            <?php foreach ($badges as $badge): ?>
              <span class="badge <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php
          $features = array_slice(array_filter(array_map('trim', explode("\n", $l['description']))), 0, 3);
          if ($features):
        ?>
          <ul class="product-features">
            <?php foreach ($features as $f): ?>
              <li><?= htmlspecialchars($f); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php $cardTags = tags_from_storage($l['tags']); ?>
        <?php if ($cardTags): ?>
          <ul class="tag-badge-list">
            <?php foreach ($cardTags as $tag): ?>
              <li class="tag-chip tag-chip-static">#<?= htmlspecialchars($tag); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($l['sale_price'] !== null): ?>
          <p class="price">
            <span class="original">$<?= htmlspecialchars($l['price']); ?></span>
            <span class="sale">$<?= htmlspecialchars($l['sale_price']); ?></span>
          </p>
        <?php else: ?>
          <p class="price">$<?= htmlspecialchars($l['price']); ?></p>
        <?php endif; ?>
        <div class="rating">★★★★★</div>
        <button class="add-to-cart" data-id="<?= $l['id']; ?>">Add to Cart</button>
        <?php if (is_admin()): ?>
          <form method="post" action="listing-delete.php" onsubmit="return confirm('Delete listing?');">
            <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
            <input type="hidden" name="id" value="<?= $l['id']; ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            <button type="submit" class="btn">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="no-results">No listings found. <a href="buy-step.php">Request a device</a></p>
<?php endif; ?>
<?php if (!empty($totalPages) && $totalPages > 1): ?>
  <nav class="pagination" aria-label="Marketplace pagination">
    <?php if ($page > 1): ?>
      <?php $prevQuery = $baseQuery; $prevQuery['page'] = $page - 1; ?>
      <a href="?<?= http_build_query($prevQuery); ?>" rel="prev">&laquo; Prev</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <?php $nextQuery = $baseQuery; $nextQuery['page'] = $page + 1; ?>
      <a href="?<?= http_build_query($nextQuery); ?>" rel="next">Next &raquo;</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
