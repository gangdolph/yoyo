<?php
$inventoryActive = $activeTab === 'inventory';
$inventoryPanelId = 'shop-manager-panel-inventory';
$inventoryTabId = 'shop-manager-tab-inventory';
?>
<section
  id="<?= htmlspecialchars($inventoryPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $inventoryActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($inventoryTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="inventory"
  data-repository="inventory"
  <?= $inventoryActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Inventory</h3>
      <p class="manager-panel__description">Review SKUs, adjust stock counts, and manage reorder thresholds.</p>
    </div>
  </header>

  <?php if ($storeInventory): ?>
  <div class="table-wrapper">
    <table class="store-table" data-manager-controller="inventory">
      <thead>
        <tr>
          <th scope="col">SKU</th>
          <th scope="col">Product</th>
          <th scope="col">Stock</th>
          <th scope="col">Reorder point</th>
          <th scope="col" class="store-table__actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($storeInventory as $item): ?>
        <tr class="store-inventory" data-sku="<?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8'); ?>">
          <th scope="row"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8'); ?></th>
          <td>
            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($item['is_skuze_official'])): ?>
              <span class="badge badge-official">SkuzE Official</span>
            <?php endif; ?>
            <?php if (!empty($item['is_skuze_product'])): ?>
              <span class="badge badge-product">SkuzE Product</span>
            <?php endif; ?>
          </td>
          <td class="store-inventory__quantity" data-field="stock"><?= (int) $item['stock']; ?></td>
          <td class="store-inventory__threshold" data-field="reorder_threshold"><?= (int) $item['reorder_threshold']; ?></td>
          <td class="store-inventory__actions">
            <button type="button" class="store-inventory__edit" data-action="edit">Adjust stock</button>
            <form class="store-inventory__form" method="post" action="/account/store_inventory_update.php" hidden>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="sku" value="<?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8'); ?>">
              <div class="store-inventory__field">
                <label>Adjust stock
                  <input type="number" name="stock_delta" value="0" step="1">
                </label>
              </div>
              <div class="store-inventory__field">
                <label>Reorder threshold
                  <input type="number" name="reorder_threshold" value="<?= (int) $item['reorder_threshold']; ?>" min="0" step="1">
                </label>
              </div>
              <div class="store-inventory__buttons">
                <button type="submit" class="btn">Save</button>
                <button type="button" class="btn btn-secondary" data-action="cancel">Cancel</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="notice">No inventory items are associated with your shop yet.</p>
  <?php endif; ?>
</section>
