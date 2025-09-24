<?php
/*
 * Change: Added a Products panel so the unified Shop Manager lists catalogue entries alongside merged Store Manager data.
 */
$productsActive = $activeTab === 'products';
$productsPanelId = 'shop-manager-panel-products';
$productsTabId = 'shop-manager-tab-products';
$productsScope = $managerScope ?? STORE_SCOPE_MINE;
$productsShowOwner = $showOwnerColumn ?? false;
?>
<section
  id="<?= htmlspecialchars($productsPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $productsActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($productsTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="products"
  data-repository="products"
  <?= $productsActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Products</h3>
      <p class="manager-panel__description">Review catalogue entries, confirm pricing, and jump to inventory adjustments.</p>
    </div>
  </header>

  <?php if (!empty($products)): ?>
  <div class="table-wrapper">
    <table class="store-table">
      <thead>
        <tr>
          <th scope="col">SKU</th>
          <th scope="col">Product</th>
          <th scope="col">Price</th>
          <th scope="col">Stock</th>
          <th scope="col">Quantity</th>
          <th scope="col">Reorder point</th>
          <?php if ($productsShowOwner): ?>
          <th scope="col">Owner</th>
          <?php endif; ?>
          <th scope="col" class="store-table__actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $product): ?>
          <?php
            $sku = (string) $product['sku'];
            $price = number_format((float) $product['price'], 2);
            $quantity = $product['quantity'];
            $inventoryUrl = '/shop-manager/index.php?tab=inventory&scope=' . rawurlencode($productsScope) . '&sku=' . rawurlencode($sku);
          ?>
          <tr>
            <th scope="row"><?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></th>
            <td>
              <?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>
              <?php if (!empty($product['is_skuze_official'])): ?>
                <span class="badge badge-official">SkuzE Official</span>
              <?php endif; ?>
              <?php if (!empty($product['is_skuze_product'])): ?>
                <span class="badge badge-product">SkuzE Product</span>
              <?php endif; ?>
            </td>
            <td>$<?= htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= (int) $product['stock']; ?></td>
            <td><?= $quantity !== null ? (int) $quantity : '—'; ?></td>
            <td><?= (int) $product['reorder_threshold']; ?></td>
            <?php if ($productsShowOwner): ?>
            <td><?= htmlspecialchars($product['owner_username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
            <?php endif; ?>
            <td class="store-table__actions">
              <a class="btn btn-small" href="<?= htmlspecialchars($inventoryUrl, ENT_QUOTES, 'UTF-8'); ?>">Adjust inventory</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="notice">No products are available for this scope.</p>
  <?php endif; ?>
</section>
