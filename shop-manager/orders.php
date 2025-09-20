<?php
$ordersActive = $activeTab === 'orders';
$ordersPanelId = 'shop-manager-panel-orders';
$ordersTabId = 'shop-manager-tab-orders';
?>
<section
  id="<?= htmlspecialchars($ordersPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $ordersActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($ordersTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="orders"
  data-repository="orders"
  <?= $ordersActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Orders</h3>
      <p class="manager-panel__description">Monitor purchase flow across marketplace, service, and trade channels.</p>
    </div>
  </header>

  <?php if ($orders): ?>
  <div class="table-wrapper">
    <table class="store-table" data-manager-controller="orders">
      <thead>
        <tr>
          <th scope="col">Order</th>
          <th scope="col">Product</th>
          <th scope="col">Direction</th>
          <th scope="col">Payment</th>
          <th scope="col">Shipping</th>
          <th scope="col">Counterparty</th>
          <th scope="col">Placed</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php
            $badges = [];
            if (!empty($order['listing']['is_official']) || !empty($order['product']['is_skuze_official']) || !empty($order['is_official_order'])) {
                $badges[] = ['class' => 'badge-official', 'label' => 'SkuzE Official'];
            }
            if (!empty($order['product']['is_skuze_product'])) {
                $badges[] = ['class' => 'badge-product', 'label' => 'SkuzE Product'];
            }
            if (!$badges) {
                $badges[] = ['class' => 'badge-community', 'label' => 'Community'];
            }
            $amount = $order['payment']['amount'] ?? null;
            $amountDisplay = $amount !== null ? '$' . number_format(((int) $amount) / 100, 2) : '—';
            $paymentStatus = $order['payment']['status'] ?? 'pending';
            $shippingStatus = $order['shipping_status'] ?? 'pending';
            $counterparty = $order['direction'] === 'sell'
                ? ($order['buyer']['username'] ?? 'Buyer')
                : ($order['listing']['owner_username'] ?? 'Seller');
          ?>
          <tr data-order-id="<?= (int) $order['id']; ?>">
            <th scope="row">#<?= (int) $order['id']; ?></th>
            <td>
              <?php foreach ($badges as $badge): ?>
                <span class="badge <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endforeach; ?>
              <?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td><?= htmlspecialchars(ucfirst((string) $order['direction']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <?= htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'); ?><br>
              <small>Status: <?= htmlspecialchars(ucfirst((string) $paymentStatus), ENT_QUOTES, 'UTF-8'); ?></small>
            </td>
            <td class="store-orders__shipping" data-order-field="shipping">
              <span class="store-orders__shipping-status" data-field="status"><?= htmlspecialchars(ucfirst((string) $shippingStatus), ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="store-orders__tracking" data-field="tracking">
                <?php if (!empty($order['tracking_number'])): ?>
                  <br><small>Tracking: <?= htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
              </span>
            </td>
            <td><?= htmlspecialchars($counterparty ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($order['placed_at'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="notice">No orders are available for review yet.</p>
  <?php endif; ?>
</section>
