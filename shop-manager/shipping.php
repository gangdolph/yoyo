<?php
$shippingActive = $activeTab === 'shipping';
$shippingPanelId = 'shop-manager-panel-shipping';
$shippingTabId = 'shop-manager-tab-shipping';
?>
<section
  id="<?= htmlspecialchars($shippingPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $shippingActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($shippingTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="shipping"
  data-repository="shipping"
  <?= $shippingActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Shipping</h3>
      <p class="manager-panel__description">Update fulfillment statuses and capture tracking numbers as orders move.</p>
    </div>
  </header>

  <?php if ($shipping): ?>
  <div class="table-wrapper">
    <table class="store-table" data-manager-controller="shipping">
      <thead>
        <tr>
          <th scope="col">Order</th>
          <th scope="col">Product</th>
          <th scope="col">Status</th>
          <th scope="col">Tracking</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($shipping as $order): ?>
          <?php
            $statusValue = strtolower((string) ($order['shipping_status'] ?? 'pending'));
            $trackingValue = $order['tracking_number'] ?? '';
            $orderId = (int) $order['id'];
          ?>
          <tr class="store-shipping" data-order-id="<?= $orderId; ?>">
            <th scope="row">#<?= $orderId; ?></th>
            <td>
              <?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8'); ?>
              <?php if (!empty($order['product']['is_skuze_product'])): ?>
                <span class="badge badge-product">SkuzE Product</span>
              <?php endif; ?>
              <?php if (!empty($order['listing']['is_official']) || !empty($order['product']['is_skuze_official']) || !empty($order['is_official_order'])): ?>
                <span class="badge badge-official">SkuzE Official</span>
              <?php endif; ?>
            </td>
            <td class="store-shipping__status">
              <form class="store-shipping__status-form" method="post" action="/account/order_update_status.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="order_id" value="<?= $orderId; ?>">
                <label class="sr-only" for="order-status-<?= $orderId; ?>">Fulfillment status</label>
                <select id="order-status-<?= $orderId; ?>" name="status">
                  <?php foreach ($fulfillmentStatusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?= $statusValue === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-small">Update</button>
              </form>
            </td>
            <td class="store-shipping__tracking" data-field="tracking">
              <form class="store-shipping__tracking-form" method="post" action="/account/order_add_tracking.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="order_id" value="<?= $orderId; ?>">
                <label class="sr-only" for="order-tracking-<?= $orderId; ?>">Tracking number</label>
                <div class="store-shipping__tracking-inputs">
                  <input id="order-tracking-<?= $orderId; ?>" type="text" name="tracking_number" value="<?= htmlspecialchars($trackingValue, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" placeholder="Add tracking">
                  <button type="submit" class="btn btn-small">Save</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="notice">No shipments currently require action.</p>
  <?php endif; ?>
</section>
