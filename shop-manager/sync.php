<?php
/*
 * Discovery note: The manager workspace surfaced sync status text but offered no manual trigger.
 * Change: Added a Sync panel with a guarded "Sync Now" action for admins to call Square pull jobs.
 */

$syncActive = $activeTab === 'sync';
$syncPanelId = 'shop-manager-panel-sync';
$syncTabId = 'shop-manager-tab-sync';
$syncDirectionLabel = strtoupper($squareSyncDirection ?? 'pull');
?>
<section
  id="<?= htmlspecialchars($syncPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $syncActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($syncTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="sync"
  <?= $syncActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Square Sync</h3>
      <p class="manager-panel__description">
        Pull the latest Square catalog and inventory counts to keep listings aligned.
      </p>
    </div>
  </header>

  <?php if (!$squareSyncAvailable): ?>
    <div class="notice notice--info">
      <p>Square sync is currently disabled. Enable the feature flag in configuration to activate manual sync.</p>
    </div>
  <?php else: ?>
    <form method="post" class="manager-sync" data-manager-form>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="tab" value="sync">
      <input type="hidden" name="manager_action" value="square_sync_now">

      <p class="field-hint">
        Current sync direction: <strong><?= htmlspecialchars($syncDirectionLabel, ENT_QUOTES, 'UTF-8'); ?></strong>.
        Manual runs will pull catalog data and inventory counts; they will not delete any Square objects.
      </p>

      <button type="submit" class="btn btn--primary">Sync Now</button>
    </form>
  <?php endif; ?>
</section>
