<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = store_session_is_admin();
$isOfficial = store_user_is_official($conn, $userId);
$scope = store_resolve_scope($_GET['scope'] ?? STORE_SCOPE_MINE, $isOfficial, $isAdmin);
$scopeOptions = store_scope_options($isOfficial, $isAdmin);

$inventory = store_fetch_inventory($conn, $userId, $scope);
$orders = store_fetch_orders($conn, $userId, $scope, $isAdmin, $isOfficial);
$shippingOrders = store_manageable_shipping_orders($orders, $userId, $isAdmin, $isOfficial);
$statusOptions = order_fulfillment_status_options();
$csrfToken = generate_token();
$showOwnerColumn = $scope !== STORE_SCOPE_MINE;
?>
<?php require __DIR__ . '/../includes/layout.php'; ?>
  <title>Store Manager</title>
  <link rel="stylesheet" href="/assets/style.css">
  <script type="module" src="/assets/store.js" defer></script>
</head>
<body>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="page-container store" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="store__header">
      <div>
        <h2>Store Manager</h2>
        <p class="store__subtitle">Review your inventory, manage orders, and keep shipping details up to date.</p>
      </div>
      <?php if (count($scopeOptions) > 1): ?>
      <form class="store__scope" method="get">
        <label for="store-scope">Viewing</label>
        <select id="store-scope" name="scope" onchange="this.form.submit()">
          <?php foreach ($scopeOptions as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?= $scope === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
    </header>

    <div class="store__tabs" role="tablist" aria-label="Store sections">
      <button type="button" role="tab" aria-selected="true" aria-controls="store-panel-inventory" id="store-tab-inventory" class="store__tab is-active">Inventory</button>
      <button type="button" role="tab" aria-selected="false" aria-controls="store-panel-orders" id="store-tab-orders" class="store__tab">Orders</button>
      <button type="button" role="tab" aria-selected="false" aria-controls="store-panel-shipping" id="store-tab-shipping" class="store__tab">Shipping</button>
    </div>

    <div class="store__alert" role="status" aria-live="polite"></div>

    <section id="store-panel-inventory" class="store__panel is-active" role="tabpanel" aria-labelledby="store-tab-inventory">
      <?php if ($inventory): ?>
      <div class="table-wrapper">
        <table class="store-table">
          <thead>
            <tr>
              <th scope="col">SKU</th>
              <th scope="col">Product</th>
              <th scope="col">Stock</th>
              <th scope="col">Reorder point</th>
              <?php if ($showOwnerColumn): ?>
              <th scope="col">Owner</th>
              <?php endif; ?>
              <th scope="col" class="store-table__actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inventory as $item): ?>
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
              <?php if ($showOwnerColumn): ?>
              <td><?= htmlspecialchars($item['owner_username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
              <?php endif; ?>
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
      <p class="notice">No inventory entries found for this scope.</p>
      <?php endif; ?>
    </section>

    <section id="store-panel-orders" class="store__panel" role="tabpanel" aria-labelledby="store-tab-orders" hidden>
      <?php if ($orders): ?>
      <div class="table-wrapper">
        <table class="store-table">
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
      <p class="notice">No orders available for this scope.</p>
      <?php endif; ?>
    </section>

    <section id="store-panel-shipping" class="store__panel" role="tabpanel" aria-labelledby="store-tab-shipping" hidden>
      <?php if ($shippingOrders): ?>
      <div class="table-wrapper">
        <table class="store-table">
          <thead>
            <tr>
              <th scope="col">Order</th>
              <th scope="col">Product</th>
              <th scope="col">Status</th>
              <th scope="col">Tracking</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($shippingOrders as $order): ?>
            <?php
              $statusValue = strtolower((string) ($order['shipping_status'] ?? 'pending'));
              $trackingValue = $order['tracking_number'] ?? '';
              $manage = store_user_can_manage_order($order, $userId, $isAdmin, $isOfficial);
            ?>
            <tr class="store-shipping" data-order-id="<?= (int) $order['id']; ?>">
              <th scope="row">#<?= (int) $order['id']; ?></th>
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
                <?php if ($manage): ?>
                <form class="store-shipping__status-form" method="post" action="/account/order_update_status.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                  <label class="sr-only" for="order-status-<?= (int) $order['id']; ?>">Fulfillment status</label>
                  <select id="order-status-<?= (int) $order['id']; ?>" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?= $statusValue === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-small">Update</button>
                </form>
                <?php else: ?>
                  <?= htmlspecialchars(ucfirst($statusValue), ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
              </td>
              <td class="store-shipping__tracking" data-field="tracking">
                <?php if ($manage): ?>
                <form class="store-shipping__tracking-form" method="post" action="/account/order_add_tracking.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                  <label class="sr-only" for="order-tracking-<?= (int) $order['id']; ?>">Tracking number</label>
                  <div class="store-shipping__tracking-inputs">
                    <input id="order-tracking-<?= (int) $order['id']; ?>" type="text" name="tracking_number" value="<?= htmlspecialchars($trackingValue, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" placeholder="Add tracking">
                    <button type="submit" class="btn btn-small">Save</button>
                  </div>
                </form>
                <?php elseif ($trackingValue): ?>
                  <?= htmlspecialchars($trackingValue, ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                  —
                <?php endif; ?>
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
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
