<?php
$dashboardActive = $activeTab === 'dashboard';
$dashboardPanelId = 'shop-manager-panel-dashboard';
$dashboardTabId = 'shop-manager-tab-dashboard';
$metrics = $reports ?? [];
?>
<section
  id="<?= htmlspecialchars($dashboardPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $dashboardActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($dashboardTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="dashboard"
  <?= $dashboardActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Dashboard</h3>
      <p class="manager-panel__description">Snapshot of commerce activity across listings, orders, and wallets.</p>
    </div>
  </header>

  <div class="dashboard-metrics">
    <div class="metric-card">
      <span class="metric-card__label">Products</span>
      <strong class="metric-card__value"><?= (int) ($metrics['total_products'] ?? 0); ?></strong>
      <small class="metric-card__hint">Official: <?= (int) ($metrics['official_products'] ?? 0); ?></small>
    </div>
    <div class="metric-card">
      <span class="metric-card__label">Listings</span>
      <strong class="metric-card__value"><?= (int) ($metrics['total_listings'] ?? 0); ?></strong>
      <small class="metric-card__hint">Pending: <?= (int) ($metrics['pending_listings'] ?? 0); ?></small>
    </div>
    <div class="metric-card">
      <span class="metric-card__label">Orders in Flight</span>
      <strong class="metric-card__value"><?= (int) ($metrics['open_orders'] ?? 0); ?></strong>
      <small class="metric-card__hint">Scope: <?= htmlspecialchars((string) ($metrics['scope_label'] ?? 'Mine'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <div class="metric-card">
      <span class="metric-card__label">Low Stock Alerts</span>
      <strong class="metric-card__value"><?= (int) ($metrics['low_stock'] ?? 0); ?></strong>
      <small class="metric-card__hint">Inventory at or below reorder thresholds.</small>
    </div>
    <div class="metric-card">
      <span class="metric-card__label">Withdrawal Queue</span>
      <strong class="metric-card__value"><?= (int) ($metrics['pending_withdrawals'] ?? 0); ?></strong>
      <small class="metric-card__hint">Pending wallet payouts.</small>
    </div>
    <div class="metric-card">
      <span class="metric-card__label">Square Sync</span>
      <strong class="metric-card__value"><?= !empty($metrics['sync_enabled']) ? 'ON' : 'OFF'; ?></strong>
      <small class="metric-card__hint">Direction: <?= htmlspecialchars(strtoupper((string) ($metrics['sync_direction'] ?? 'pull')),
        ENT_QUOTES,
        'UTF-8'); ?></small>
    </div>
  </div>

  <div class="dashboard-links">
    <a class="btn" href="/shop-manager/index.php?tab=listings">Manage Listings</a>
    <a class="btn" href="/shop-manager/index.php?tab=inventory">Review Inventory</a>
    <?php if (feature_wallets_enabled()): ?>
      <a class="btn" href="/shop-manager/index.php?tab=wallets">Wallet Queue</a>
    <?php endif; ?>
    <?php if (feature_taxonomy_enabled()): ?>
      <a class="btn" href="/shop-manager/index.php?tab=taxonomy">Taxonomy Manager</a>
    <?php endif; ?>
  </div>
</section>
