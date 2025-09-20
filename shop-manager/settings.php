<?php
$settingsActive = $activeTab === 'settings';
$settingsPanelId = 'shop-manager-panel-settings';
$settingsTabId = 'shop-manager-tab-settings';
?>
<section
  id="<?= htmlspecialchars($settingsPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $settingsActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($settingsTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="settings"
  data-repository="settings"
  <?= $settingsActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Settings</h3>
      <p class="manager-panel__description">Control default fulfillment preferences and connect external catalogues.</p>
    </div>
  </header>

  <form class="manager-settings" method="post" data-manager-controller="settings" data-manager-form>
    <fieldset>
      <legend>Fulfillment defaults</legend>
      <label class="manager-toggle">
        <input type="checkbox" name="auto_accept_orders" value="1">
        <span>Automatically accept paid orders</span>
      </label>
      <label class="manager-toggle">
        <input type="checkbox" name="notify_on_inventory_low" value="1" checked>
        <span>Notify me when stock drops below threshold</span>
      </label>
    </fieldset>

    <fieldset>
      <legend>Integrations</legend>
      <p class="field-hint">Square catalog sync will be available once the repository services are provisioned.</p>
      <label class="manager-toggle manager-toggle--disabled">
        <input type="checkbox" name="square_sync" value="1" disabled>
        <span>Enable Square catalog sync</span>
      </label>
    </fieldset>

    <p class="notice">Settings are stored via the forthcoming shop manager services. Changes made here will synchronise once the backend is activated.</p>
    <button type="submit" class="btn" disabled>Save preferences</button>
  </form>
</section>
