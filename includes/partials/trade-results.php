<?php
/**
 * @var array $listings
 * @var array $baseQuery
 * @var int   $page
 * @var int   $limit
 * @var int   $totalPages
 * @var int   $total
 * @var int   $resultCount
 * @var int|null $user_id
 * @var mysqli $conn
 */
?>
<div class="listing-toolbar">
  <p class="listing-summary" aria-live="polite">
    <?php if ($total > 0): ?>
      <?php
        $rangeStart = (($page - 1) * $limit) + 1;
        $rangeEnd = $rangeStart + $resultCount - 1;
        if ($rangeEnd > $total) {
            $rangeEnd = $total;
        }
      ?>
      Showing <?= $rangeStart; ?>–<?= $rangeEnd; ?> of <?= $total; ?> trade listings
    <?php else: ?>
      No trade listings match your filters.
    <?php endif; ?>
  </p>
</div>
<?php if ($listings): ?>
  <div class="table-responsive">
    <table class="trade-table">
      <thead>
        <tr>
          <th scope="col">Have</th>
          <th scope="col">Want</th>
          <th scope="col">Type</th>
          <th scope="col">Description</th>
          <th scope="col">Image</th>
          <th scope="col">Status</th>
          <th scope="col">Owner</th>
          <th scope="col">Offers</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($listings as $l): ?>
          <tr>
            <td><?= htmlspecialchars($l['have_item']); ?></td>
            <td><?= htmlspecialchars($l['want_item']); ?></td>
            <td><?= htmlspecialchars($l['trade_type']); ?></td>
            <td><?= htmlspecialchars($l['description']); ?></td>
            <td>
              <?php if (!empty($l['image'])): ?>
                <img src="uploads/<?= htmlspecialchars($l['image']); ?>" alt="" style="max-width:100px">
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($l['status']); ?></td>
            <td><?= username_with_avatar($conn, (int)$l['owner_id'], $l['username']); ?></td>
            <td>
              <?php if ((int)$l['owner_id'] === (int)$user_id): ?>
                <a href="trade.php?listing=<?= $l['id']; ?>">Offers <span class="badge"><?= $l['offers']; ?></span></a>
              <?php else: ?>
                <span class="badge"><?= $l['offers']; ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)$l['owner_id'] === (int)$user_id || is_admin()): ?>
                <a href="trade-listing.php?edit=<?= $l['id']; ?>">Edit</a>
                <form method="post" action="trade-listing-delete.php" class="inline-form" onsubmit="return confirm('Delete listing?');">
                  <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                  <input type="hidden" name="id" value="<?= $l['id']; ?>">
                  <input type="hidden" name="redirect" value="trade-listings.php">
                  <button type="submit">Delete</button>
                </form>
              <?php elseif ($l['status'] === 'open' && $user_id): ?>
                <a href="trade-offer.php?id=<?= $l['id']; ?>">Make Offer</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="no-results">No trade listings available right now.</p>
<?php endif; ?>
<?php if (!empty($totalPages) && $totalPages > 1): ?>
  <nav class="pagination" aria-label="Trade listings pagination">
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
