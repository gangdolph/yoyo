<?php
$listingsActive = $activeTab === 'listings';
$listingsPanelId = 'shop-manager-panel-listings';
$listingsTabId = 'shop-manager-tab-listings';
$listingsStatus = $listingsFilterState['status'] ?? '';
$listingsSearch = $listingsFilterState['search'] ?? '';
$listingsPerPage = (int) ($listingsFilterState['per_page'] ?? 10);
$listingsPage = (int) ($listingsFilterState['page'] ?? 1);
$listingsPageCount = (int) ($listingsPagination['pages'] ?? 1);
$listingsTotal = (int) ($listingsPagination['total'] ?? count($listingsItems));
$squareSyncAvailable = !empty($squareSyncAvailable);
$paginationQuery = [
    'tab' => 'listings',
    'status' => $listingsStatus,
    'search' => $listingsSearch,
    'per_page' => $listingsPerPage,
];
?>
<section
  id="<?= htmlspecialchars($listingsPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $listingsActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($listingsTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="listings"
  data-repository="listings"
  <?= $listingsActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Listings</h3>
      <p class="manager-panel__description">Filter by status, adjust tags, and move listings through their lifecycle.</p>
    </div>
    <div class="manager-panel__toolbar">
      <a class="btn" href="/sell.php">Create listing</a>
      <form class="manager-panel__filters" method="get" action="/shop-manager/index.php" data-manager-filters="listings">
        <input type="hidden" name="tab" value="listings">
        <label class="manager-filter">
          <span class="manager-filter__label">Search</span>
          <input type="search" name="search" value="<?= htmlspecialchars($listingsSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search listings">
        </label>
        <label class="manager-filter">
          <span class="manager-filter__label">Status</span>
          <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($listingsStatuses as $status): ?>
              <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= $status === $listingsStatus ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="manager-filter">
          <span class="manager-filter__label">Per page</span>
          <select name="per_page">
            <?php foreach ([10, 25, 50] as $option): ?>
              <option value="<?= $option; ?>" <?= $option === $listingsPerPage ? 'selected' : ''; ?>><?= $option; ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn btn-small">Apply</button>
      </form>
    </div>
  </header>

  <?php if ($listingsItems): ?>
  <div class="table-wrapper">
    <table class="store-table manager-table" data-manager-controller="listings">
      <thead>
        <tr>
          <th scope="col">Listing</th>
          <th scope="col">Status</th>
          <th scope="col">Quantity</th>
          <th scope="col">Reserved</th>
          <th scope="col">Tags</th>
          <th scope="col">Updated</th>
          <th scope="col" class="store-table__actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($listingsItems as $item): ?>
          <?php
            $listingId = (int) $item['id'];
            $tagInput = tags_to_input_value($item['tags']);
            $filtersState = [
              'status' => $listingsStatus,
              'search' => $listingsSearch,
              'page' => $listingsPage,
              'per_page' => $listingsPerPage,
            ];
          ?>
          <tr data-listing-id="<?= $listingId; ?>">
            <th scope="row">
              <strong><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
              <small>
                <?= htmlspecialchars($item['category'] ?: 'Uncategorised', ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($item['pickup_only']): ?> · Pickup only<?php endif; ?>
              </small><br>
              <small>Created <?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
            </th>
            <td>
              <form method="post" class="manager-status-form" data-manager-action="status">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="listings">
                <input type="hidden" name="manager_action" value="update_listing_status">
                <input type="hidden" name="listing_id" value="<?= $listingId; ?>">
                <?php foreach ($filtersState as $key => $value): ?>
                  <input type="hidden" name="filters[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
                <label class="sr-only" for="listing-status-<?= $listingId; ?>">Listing status</label>
                <select id="listing-status-<?= $listingId; ?>" name="status">
                  <?php foreach ($listingsStatuses as $status): ?>
                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= $status === $item['status'] ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-small">Update</button>
              </form>
            </td>
            <td data-field="quantity"><?= $item['quantity'] !== null ? (int) $item['quantity'] : '—'; ?></td>
            <td data-field="reserved"><?= $item['reserved_qty'] !== null ? (int) $item['reserved_qty'] : '—'; ?></td>
            <td class="manager-tags">
              <div class="tag-badges">
                <?php if ($item['tags']): ?>
                  <?php foreach ($item['tags'] as $tag): ?>
                    <span class="tag-chip tag-chip-static"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="tag-empty">No tags</span>
                <?php endif; ?>
              </div>
              <form method="post" class="manager-tags-form" data-manager-action="tags">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="listings">
                <input type="hidden" name="manager_action" value="update_listing_tags">
                <input type="hidden" name="listing_id" value="<?= $listingId; ?>">
                <?php foreach ($filtersState as $key => $value): ?>
                  <input type="hidden" name="filters[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
                <div class="tag-input" data-tag-editor>
                  <div class="tag-list" data-tag-list></div>
                  <input type="text" data-tag-source placeholder="Add tag">
                  <input type="hidden" name="tags" value="<?= htmlspecialchars($tagInput, ENT_QUOTES, 'UTF-8'); ?>" data-tag-store>
                </div>
                <div class="manager-tags__actions">
                  <button type="submit" class="btn btn-small">Save</button>
                </div>
              </form>
            </td>
            <td><?= htmlspecialchars((string) ($item['updated_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="store-table__actions">
              <?php if (!empty($squareSyncAvailable)): ?>
              <form method="post" class="manager-sync-form" data-manager-action="sync">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="listings">
                <input type="hidden" name="manager_action" value="sync_listing">
                <input type="hidden" name="listing_id" value="<?= $listingId; ?>">
                <?php foreach ($filtersState as $key => $value): ?>
                  <input type="hidden" name="filters[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-secondary btn-small">Sync to Square</button>
              </form>
              <?php if (!empty($item['square_sync_status'])): ?>
                <small class="manager-sync-status">Square: <?= htmlspecialchars((string) $item['square_sync_status'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
              <?php endif; ?>
              <form method="post" class="manager-delete-form" data-manager-action="delete" onsubmit="return confirm('Delete this listing?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="listings">
                <input type="hidden" name="manager_action" value="delete_listing">
                <input type="hidden" name="listing_id" value="<?= $listingId; ?>">
                <?php foreach ($filtersState as $key => $value): ?>
                  <input type="hidden" name="filters[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-secondary btn-small">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($listingsPageCount > 1): ?>
    <nav class="manager-pagination" aria-label="Listing pagination">
      <ul>
        <?php for ($page = 1; $page <= $listingsPageCount; $page++): ?>
          <?php
            $query = $paginationQuery;
            $query['page'] = $page;
            $url = '/shop-manager/index.php?' . http_build_query(array_filter($query, static function ($value) {
                return $value !== '' && $value !== null;
            }));
          ?>
          <li class="<?= $page === $listingsPage ? 'is-active' : ''; ?>">
            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" aria-current="<?= $page === $listingsPage ? 'page' : 'false'; ?>">
              <?= $page; ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
      <p class="manager-pagination__summary">Showing page <?= $listingsPage; ?> of <?= $listingsPageCount; ?> (<?= $listingsTotal; ?> total listings)</p>
    </nav>
  <?php endif; ?>
  <?php else: ?>
    <p class="notice">No listings match the selected filters.</p>
  <?php endif; ?>
</section>
