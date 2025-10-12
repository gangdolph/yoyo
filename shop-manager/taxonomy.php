<?php
$taxonomyActive = $activeTab === 'taxonomy';
$taxonomyPanelId = 'shop-manager-panel-taxonomy';
$taxonomyTabId = 'shop-manager-tab-taxonomy';
$brands = $taxonomyBrandList ?? [];
$models = $taxonomyModelList ?? [];
$selectedBrand = $selectedBrandId ?? null;
$selectedBrandRow = null;
foreach ($brands as $brand) {
    if ((int) $brand['id'] === (int) $selectedBrand) {
        $selectedBrandRow = $brand;
        break;
    }
}
?>
<section
  id="<?= htmlspecialchars($taxonomyPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $taxonomyActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($taxonomyTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="taxonomy"
  <?= $taxonomyActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Taxonomy Manager</h3>
      <p class="manager-panel__description">Create and maintain device brands and models used by products and listings.</p>
    </div>
  </header>

  <?php if (!feature_taxonomy_enabled()): ?>
    <p class="notice">Device taxonomy is disabled. Enable FEATURE_TAXONOMY in your configuration.</p>
    <?php return; ?>
  <?php endif; ?>

  <div class="taxonomy-layout">
    <div class="taxonomy-column">
      <h4>Brands</h4>
      <?php if (empty($brands)): ?>
        <p class="notice">No brands have been defined.</p>
      <?php else: ?>
        <ul class="taxonomy-list">
          <?php foreach ($brands as $brand): ?>
            <li class="taxonomy-list__item<?= $selectedBrand === (int) $brand['id'] ? ' is-active' : ''; ?>">
              <a href="<?= htmlspecialchars('/shop-manager/index.php?tab=taxonomy&brand_id=' . (int) $brand['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars((string) $brand['name'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <span class="taxonomy-list__meta">P<?= (int) ($brand['product_count'] ?? 0); ?>/L<?= (int) ($brand['listing_count'] ?? 0); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form class="manager-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="tab" value="taxonomy">
        <input type="hidden" name="manager_action" value="taxonomy_create_brand">
        <label for="taxonomy-brand-name">Brand name</label>
        <input id="taxonomy-brand-name" name="brand_name" type="text" required>
        <label for="taxonomy-brand-slug">Slug</label>
        <input id="taxonomy-brand-slug" name="brand_slug" type="text" placeholder="example-brand" required>
        <button type="submit" class="btn">Add brand</button>
      </form>

      <?php if ($selectedBrand !== null): ?>
        <form class="manager-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="tab" value="taxonomy">
          <input type="hidden" name="manager_action" value="taxonomy_update_brand">
          <input type="hidden" name="brand_id" value="<?= (int) $selectedBrand; ?>">
          <label for="taxonomy-brand-update-name">Update name</label>
          <input id="taxonomy-brand-update-name" name="brand_name" type="text" value="<?= htmlspecialchars((string) ($selectedBrandRow['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
          <label for="taxonomy-brand-update-slug">Update slug</label>
          <input id="taxonomy-brand-update-slug" name="brand_slug" type="text" value="<?= htmlspecialchars((string) ($selectedBrandRow['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
          <div class="manager-form__actions">
            <button type="submit" class="btn">Update brand</button>
          </div>
        </form>
        <form class="manager-form" method="post" onsubmit="return confirm('Delete this brand? Products and listings must be updated first.');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="tab" value="taxonomy">
          <input type="hidden" name="manager_action" value="taxonomy_delete_brand">
          <input type="hidden" name="brand_id" value="<?= (int) $selectedBrand; ?>">
          <button type="submit" class="btn btn-danger">Delete brand</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="taxonomy-column">
      <h4>Models</h4>
      <?php if ($selectedBrand === null): ?>
        <p class="notice">Select a brand to view associated models.</p>
      <?php elseif (empty($models)): ?>
        <p class="notice">No models exist for this brand.</p>
      <?php else: ?>
        <ul class="taxonomy-list taxonomy-list--compact">
          <?php foreach ($models as $model): ?>
            <li class="taxonomy-list__item">
              <?= htmlspecialchars((string) $model['name'], ENT_QUOTES, 'UTF-8'); ?>
              <span class="taxonomy-list__meta">P<?= (int) ($model['product_count'] ?? 0); ?>/L<?= (int) ($model['listing_count'] ?? 0); ?></span>
              <form class="inline-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="taxonomy">
                <input type="hidden" name="manager_action" value="taxonomy_update_model">
                <input type="hidden" name="model_id" value="<?= (int) $model['id']; ?>">
                <input type="hidden" name="brand_id" value="<?= (int) $selectedBrand; ?>">
                <input name="model_name" type="text" value="<?= htmlspecialchars((string) $model['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <input name="model_slug" type="text" value="<?= htmlspecialchars((string) $model['slug'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <button type="submit" class="btn btn-small">Save</button>
              </form>
              <form class="inline-form" method="post" onsubmit="return confirm('Delete this model?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="tab" value="taxonomy">
                <input type="hidden" name="manager_action" value="taxonomy_delete_model">
                <input type="hidden" name="model_id" value="<?= (int) $model['id']; ?>">
                <button type="submit" class="btn btn-small btn-danger">Delete</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($selectedBrand !== null): ?>
        <form class="manager-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="tab" value="taxonomy">
          <input type="hidden" name="manager_action" value="taxonomy_create_model">
          <input type="hidden" name="brand_id" value="<?= (int) $selectedBrand; ?>">
          <label for="taxonomy-model-name">Model name</label>
          <input id="taxonomy-model-name" name="model_name" type="text" required>
          <label for="taxonomy-model-slug">Slug</label>
          <input id="taxonomy-model-slug" name="model_slug" type="text" placeholder="example-model" required>
          <button type="submit" class="btn">Add model</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
