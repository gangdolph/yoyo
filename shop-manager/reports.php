<?php
/*
 * Change: Surface a lightweight reporting summary within Shop Manager now that Store Manager links redirect here.
 */
$reportsActive = $activeTab === 'reports';
$reportsPanelId = 'shop-manager-panel-reports';
$reportsTabId = 'shop-manager-tab-reports';
$scopeLabel = (string) ($reports['scope_label'] ?? 'My inventory');
$totalProducts = (int) ($reports['total_products'] ?? 0);
$officialProducts = (int) ($reports['official_products'] ?? 0);
$totalListings = (int) ($reports['total_listings'] ?? 0);
$pendingListings = (int) ($reports['pending_listings'] ?? 0);
$openOrders = (int) ($reports['open_orders'] ?? 0);
$lowStock = (int) ($reports['low_stock'] ?? 0);
$syncEnabled = !empty($reports['sync_enabled']);
$syncDirection = strtoupper((string) ($reports['sync_direction'] ?? 'PULL'));
?>
<section
  id="<?= htmlspecialchars($reportsPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $reportsActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($reportsTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="reports"
  <?= $reportsActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Reports</h3>
      <p class="manager-panel__description">Track catalogue depth, review queue volume, and fulfillment workload for <?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?>.</p>
    </div>
  </header>

  <div class="manager-reports">
    <dl class="manager-reports__grid">
      <div class="manager-reports__item">
        <dt>Total products</dt>
        <dd><?= $totalProducts; ?></dd>
      </div>
      <div class="manager-reports__item">
        <dt>Official catalogue</dt>
        <dd><?= $officialProducts; ?></dd>
      </div>
      <div class="manager-reports__item">
        <dt>Total listings</dt>
        <dd><?= $totalListings; ?></dd>
      </div>
      <div class="manager-reports__item">
        <dt>Listings pending review</dt>
        <dd><?= $pendingListings; ?></dd>
      </div>
      <div class="manager-reports__item">
        <dt>Open orders</dt>
        <dd><?= $openOrders; ?></dd>
      </div>
      <div class="manager-reports__item">
        <dt>Low stock SKUs</dt>
        <dd><?= $lowStock; ?></dd>
      </div>
    </dl>
  </div>

  <div class="notice notice--info">
    <?php if ($syncEnabled): ?>
      <p>Square sync is enabled (direction <?= htmlspecialchars($syncDirection, ENT_QUOTES, 'UTF-8'); ?>). Use the Sync tab to run manual pulls when needed.</p>
    <?php else: ?>
      <p>Square sync is currently disabled. Configure credentials to enable manual catalog reconciliation.</p>
    <?php endif; ?>
  </div>
</section>
