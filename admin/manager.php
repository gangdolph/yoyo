<?php
/*
 * Discovery note: Admin tooling was fragmented across products, listings, and orders without a single control surface.
 * Change: Added a feature-gated Shop Manager V1 workspace with tabbed workflows for catalog, listings, inventory, orders, and reporting.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/repositories/ChangeRequestsService.php';
require_once __DIR__ . '/../includes/repositories/ListingsRepo.php';
require_once __DIR__ . '/../includes/repositories/InventoryService.php';
require_once __DIR__ . '/../includes/repositories/OrdersService.php';
require_once __DIR__ . '/../includes/listing-query.php';

$config = require __DIR__ . '/../config.php';

if (empty($config['SHOP_MANAGER_V1_ENABLED'])) {
    http_response_code(404);
    echo 'The Shop Manager preview is not currently enabled.';
    exit;
}

ensure_admin_or_official('../dashboard.php');

/** @var mysqli|null $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = require __DIR__ . '/../includes/db.php';
}

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = authz_has_role('admin');
$isOfficial = authz_has_role('skuze_official');

$changeRequests = new ChangeRequestsService($conn);
$listingsRepo = new ListingsRepo($conn, $changeRequests);
$inventoryService = new InventoryService($conn);
$ordersService = new OrdersService($conn);

$tabs = [
    'products' => 'Products',
    'listings' => 'Listings',
    'inventory' => 'Inventory',
    'orders' => 'Orders',
    'shipping' => 'Shipping',
    'sync' => 'Sync',
    'reports' => 'Reports',
];

if (!function_exists('admin_manager_resolve_tab')) {
    function admin_manager_resolve_tab(string $requested, array $tabs): string
    {
        $requested = strtolower(trim($requested));
        if ($requested !== '' && array_key_exists($requested, $tabs)) {
            return $requested;
        }

        return 'products';
    }
}

if (!function_exists('admin_manager_flash')) {
    function admin_manager_flash(string $type, string $message): void
    {
        if (!isset($_SESSION['admin_manager_flash']) || !is_array($_SESSION['admin_manager_flash'])) {
            $_SESSION['admin_manager_flash'] = [];
        }

        $_SESSION['admin_manager_flash'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('admin_manager_consume_flash')) {
    /**
     * @return array<int, array{type: string, message: string}>
     */
    function admin_manager_consume_flash(): array
    {
        if (!isset($_SESSION['admin_manager_flash']) || !is_array($_SESSION['admin_manager_flash'])) {
            return [];
        }

        $messages = array_values(array_filter(array_map(
            static function ($entry): ?array {
                if (!is_array($entry) || !isset($entry['type'], $entry['message'])) {
                    return null;
                }

                return [
                    'type' => (string) $entry['type'],
                    'message' => (string) $entry['message'],
                ];
            },
            $_SESSION['admin_manager_flash']
        )));

        unset($_SESSION['admin_manager_flash']);

        return $messages;
    }
}

if (!function_exists('admin_manager_next_status')) {
    function admin_manager_next_status(string $status): ?string
    {
        $status = strtolower(trim($status));
        if ($status === 'pending') {
            $status = 'new';
        }

        $map = [
            'new' => 'paid',
            'paid' => 'packing',
            'packing' => 'shipped',
            'shipped' => 'delivered',
            'delivered' => 'completed',
        ];

        return $map[$status] ?? null;
    }
}

$requestedTab = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['tab'] ?? 'products')
    : (string) ($_GET['tab'] ?? 'products');
$activeTab = admin_manager_resolve_tab($requestedTab, $tabs);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectTab = admin_manager_resolve_tab((string) ($_POST['tab'] ?? $activeTab), $tabs);
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!validate_token($token)) {
        admin_manager_flash('error', 'Invalid request token.');
    } else {
        $action = (string) ($_POST['manager_action'] ?? '');
        try {
            switch ($action) {
                case 'update_product':
                    $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
                    $title = trim((string) ($_POST['title'] ?? ''));
                    $priceInput = trim((string) ($_POST['price'] ?? '0'));
                    $stock = max(0, (int) ($_POST['stock'] ?? 0));
                    $officialFlag = !empty($_POST['is_skuze_official']) ? 1 : 0;
                    $productFlag = !empty($_POST['is_skuze_product']) ? 1 : 0;

                    if ($sku === '' || $title === '') {
                        throw new RuntimeException('SKU and title are required.');
                    }

                    if ($priceInput === '') {
                        $priceInput = '0';
                    }

                    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $priceInput)) {
                        throw new RuntimeException('Prices must be numeric with up to two decimals.');
                    }

                    $price = number_format((float) $priceInput, 2, '.', '');

                    $conn->begin_transaction();
                    $stmt = $conn->prepare(
                        'UPDATE products SET title = ?, price = ?, stock = ?, '
                        . 'quantity = CASE WHEN quantity IS NULL THEN NULL ELSE ? END, '
                        . 'is_skuze_official = ?, is_skuze_product = ?, is_official = ? '
                        . 'WHERE sku = ?'
                    );
                    if ($stmt === false) {
                        throw new RuntimeException('Unable to prepare product update.');
                    }

                    $quantityMirror = $stock;
                    $stmt->bind_param(
                        'sdiiiiis',
                        $title,
                        $price,
                        $stock,
                        $quantityMirror,
                        $officialFlag,
                        $productFlag,
                        $officialFlag,
                        $sku
                    );
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Failed to update product: ' . $stmt->error);
                    }
                    $stmt->close();

                    $listingFlag = ($officialFlag || $productFlag) ? 1 : 0;
                    $link = $conn->prepare('UPDATE listings SET is_official_listing = ? WHERE product_sku = ?');
                    if ($link !== false) {
                        $link->bind_param('is', $listingFlag, $sku);
                        $link->execute();
                        $link->close();
                    }

                    $conn->commit();
                    admin_manager_flash('success', 'Product ' . $sku . ' updated.');
                    break;

                case 'update_listing_details':
                    $listingId = (int) ($_POST['listing_id'] ?? 0);
                    $title = trim((string) ($_POST['title'] ?? ''));
                    $priceInput = trim((string) ($_POST['price'] ?? ''));
                    $quantityInput = trim((string) ($_POST['quantity'] ?? ''));
                    $brandInput = trim((string) ($_POST['brand_id'] ?? ''));
                    $modelInput = trim((string) ($_POST['model_id'] ?? ''));

                    if ($listingId <= 0) {
                        throw new RuntimeException('Invalid listing specified.');
                    }

                    if ($priceInput !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $priceInput)) {
                        throw new RuntimeException('Listing price must be numeric with up to two decimals.');
                    }

                    if ($quantityInput !== '' && !preg_match('/^\d+$/', $quantityInput)) {
                        throw new RuntimeException('Quantity must be a whole number.');
                    }

                    $payload = [
                        'title' => $title,
                        'price' => $priceInput,
                        'quantity' => $quantityInput,
                        'brand_id' => $brandInput,
                        'model_id' => $modelInput,
                    ];

                    $result = $listingsRepo->updateDetails(
                        $listingId,
                        $viewerId,
                        $payload,
                        $isAdmin,
                        $isOfficial
                    );

                    if (!empty($result['requires_review'])) {
                        admin_manager_flash('success', 'Change request submitted for moderation.');
                    } elseif (!empty($result['changed'])) {
                        admin_manager_flash('success', 'Listing updated.');
                    } else {
                        admin_manager_flash('info', 'No changes detected for the listing.');
                    }
                    break;

                case 'approve_listing_change':
                    $requestId = (int) ($_POST['request_id'] ?? 0);
                    if ($requestId <= 0) {
                        throw new RuntimeException('Missing change request id.');
                    }
                    $listingsRepo->approveChangeRequest($requestId, $viewerId);
                    admin_manager_flash('success', 'Change request approved and applied.');
                    break;

                case 'reject_listing_change':
                    $requestId = (int) ($_POST['request_id'] ?? 0);
                    if ($requestId <= 0) {
                        throw new RuntimeException('Missing change request id.');
                    }
                    $listingId = (int) ($_POST['listing_id'] ?? 0);
                    if ($listingId > 0) {
                        $changeRequests->markSingleRequest($requestId, 'rejected', $viewerId, null, 'Rejected in manager workspace');
                    } else {
                        $changeRequests->markSingleRequest($requestId, 'rejected', $viewerId);
                    }
                    admin_manager_flash('success', 'Change request rejected.');
                    break;

                case 'adjust_stock':
                    $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
                    $deltaInput = trim((string) ($_POST['delta'] ?? '0'));
                    $thresholdInput = trim((string) ($_POST['reorder_threshold'] ?? ''));

                    if ($sku === '') {
                        throw new RuntimeException('A SKU is required for stock adjustments.');
                    }

                    if (!preg_match('/^-?\d+$/', $deltaInput)) {
                        throw new RuntimeException('Stock adjustments must be whole numbers.');
                    }
                    $delta = (int) $deltaInput;

                    $threshold = null;
                    if ($thresholdInput !== '') {
                        if (!preg_match('/^\d+$/', $thresholdInput)) {
                            throw new RuntimeException('Reorder threshold must be zero or a positive whole number.');
                        }
                        $threshold = (int) $thresholdInput;
                    }

                    $state = $inventoryService->adjustProductStock(
                        $sku,
                        $delta,
                        $viewerId,
                        $threshold,
                        $isAdmin,
                        $isOfficial
                    );

                    admin_manager_flash(
                        'success',
                        sprintf(
                            'Stock for %s adjusted by %d. New stock: %d.',
                            $state['sku'],
                            $delta,
                            $state['stock']
                        )
                    );
                    break;

                case 'advance_order':
                    $orderId = (int) ($_POST['order_id'] ?? 0);
                    $targetStatus = (string) ($_POST['status'] ?? '');
                    if ($orderId <= 0 || $targetStatus === '') {
                        throw new RuntimeException('Invalid order advance request.');
                    }
                    $ordersService->updateStatus($orderId, $targetStatus, $viewerId, $isAdmin, $isOfficial);
                    admin_manager_flash('success', 'Order #' . $orderId . ' moved to ' . ucfirst($targetStatus) . '.');
                    break;

                case 'update_tracking':
                    $orderId = (int) ($_POST['order_id'] ?? 0);
                    $tracking = trim((string) ($_POST['tracking_number'] ?? ''));
                    if ($orderId <= 0) {
                        throw new RuntimeException('Invalid order for tracking update.');
                    }
                    $ordersService->updateTracking($orderId, $tracking === '' ? null : $tracking, $viewerId, $isAdmin, $isOfficial);
                    admin_manager_flash('success', 'Tracking updated for order #' . $orderId . '.');
                    break;

                default:
                    admin_manager_flash('error', 'Unsupported action requested.');
                    break;
            }
        } catch (Throwable $e) {
            admin_manager_flash('error', $e->getMessage());
        }
    }

    $query = ['tab' => $redirectTab];
    header('Location: manager.php?' . http_build_query($query));
    exit;
}

$csrfToken = generate_token();
$flashMessages = admin_manager_consume_flash();

$products = [];
if ($result = $conn->query(
    'SELECT sku, title, price, stock, quantity, reorder_threshold, '
    . 'is_skuze_official, is_skuze_product, is_official '
    . 'FROM products ORDER BY created_at DESC LIMIT 100'
)) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->close();
}

$listings = [];
if ($result = $conn->query(
    "SELECT l.id, l.title, l.price, l.quantity, l.status, l.is_official_listing, l.brand_id, l.model_id, "
    . "sb.name AS brand_name, sm.name AS model_name, "
    . "u.username AS owner_username, "
    . "(SELECT COUNT(*) FROM listing_change_requests r WHERE r.listing_id = l.id AND r.status = 'pending') AS pending_requests "
    . "FROM listings l "
    . "JOIN users u ON l.owner_id = u.id "
    . "LEFT JOIN service_brands sb ON sb.id = l.brand_id "
    . "LEFT JOIN service_models sm ON sm.id = l.model_id "
    . "WHERE l.status IN ('draft','pending','approved','live') "
    . "ORDER BY l.updated_at DESC LIMIT 100"
)) {
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
    $result->close();
}

$serviceBrandOptions = listing_brand_options($conn);
$serviceModelIndex = listing_model_index($conn);

$pendingChanges = [];
if ($result = $conn->query(
    "SELECT r.id, r.listing_id, r.change_summary, r.payload, r.created_at, "
    . "u.username AS requester_username, l.title AS listing_title, l.status AS listing_status "
    . "FROM listing_change_requests r "
    . "JOIN listings l ON r.listing_id = l.id "
    . "JOIN users u ON r.requester_id = u.id "
    . "WHERE r.status = 'pending' ORDER BY r.created_at DESC LIMIT 50"
)) {
    while ($row = $result->fetch_assoc()) {
        $payload = $row['payload'];
        $decoded = null;
        if ($payload !== null && $payload !== '') {
            $decoded = json_decode((string) $payload, true);
            if (!is_array($decoded)) {
                $decoded = null;
            }
        }
        $row['payload'] = $decoded;
        $pendingChanges[] = $row;
    }
    $result->close();
}

$inventoryLedger = [];
if ($result = $conn->query(
    'SELECT it.product_sku, it.owner_id, it.transaction_type, it.quantity_change, '
    . 'it.quantity_before, it.quantity_after, it.reference_type, it.reference_id, '
    . 'it.metadata, it.created_at, u.username AS owner_username '
    . 'FROM inventory_transactions it '
    . 'LEFT JOIN users u ON it.owner_id = u.id '
    . 'ORDER BY it.created_at DESC LIMIT 50'
)) {
    while ($row = $result->fetch_assoc()) {
        $metadata = $row['metadata'];
        $decoded = null;
        if ($metadata !== null && $metadata !== '') {
            $decoded = json_decode((string) $metadata, true);
            if (!is_array($decoded)) {
                $decoded = null;
            }
        }
        $row['metadata'] = $decoded;
        $inventoryLedger[] = $row;
    }
    $result->close();
}

$orders = array_slice(fetch_orders_for_admin($conn, null, $viewerId), 0, 25);

$shippingQueue = array_values(array_filter($orders, static function (array $order): bool {
    $status = strtolower((string) ($order['shipping_status'] ?? ''));
    return in_array($status, ['paid', 'packing', 'shipped'], true);
}));

$syncState = [
    'enabled' => !empty($config['SQUARE_SYNC_ENABLED']),
    'direction' => $config['SQUARE_SYNC_DIRECTION'] ?? 'pull',
    'orders_flag' => !empty($config['USE_SQUARE_ORDERS']),
];

$reports = [
    'total_products' => 0,
    'official_products' => 0,
    'pending_listing_requests' => count($pendingChanges),
    'open_orders' => count(array_filter($orders, static function (array $order): bool {
        $status = strtolower((string) ($order['shipping_status'] ?? ''));
        return !in_array($status, ['completed', 'cancelled'], true);
    })),
];

if ($result = $conn->query('SELECT COUNT(*) AS total, SUM(CASE WHEN is_skuze_official = 1 OR is_official = 1 THEN 1 ELSE 0 END) AS official FROM products')) {
    if ($row = $result->fetch_assoc()) {
        $reports['total_products'] = (int) ($row['total'] ?? 0);
        $reports['official_products'] = (int) ($row['official'] ?? 0);
    }
    $result->close();
}

?>
<?php require __DIR__ . '/../includes/layout.php'; ?>
  <title>Shop Manager V1</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="page-container store">
    <header class="store__header">
      <div>
        <h1>Shop Manager V1</h1>
        <p class="store__subtitle">Preview workspace for catalog, listings, inventory, and fulfillment oversight.</p>
      </div>
    </header>

    <nav class="store__tabs" role="tablist" aria-label="Manager sections">
      <?php foreach ($tabs as $key => $label): ?>
        <?php $isActive = $key === $activeTab; ?>
        <button
          type="button"
          class="store__tab<?= $isActive ? ' is-active' : ''; ?>"
          role="tab"
          aria-selected="<?= $isActive ? 'true' : 'false'; ?>"
          aria-controls="manager-panel-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
          onclick="window.location.href='manager.php?tab=<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>'"
        >
          <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </button>
      <?php endforeach; ?>
    </nav>

    <?php if ($flashMessages): ?>
      <div class="store__alert" role="status">
        <?php foreach ($flashMessages as $message): ?>
          <div class="alert <?= htmlspecialchars($message['type'], ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <section
      id="manager-panel-products"
      class="store__panel<?= $activeTab === 'products' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-products"
      <?= $activeTab === 'products' ? '' : 'hidden'; ?>
    >
      <h2>Products</h2>
      <p>Review catalog entries, adjust pricing, and toggle official product flags.</p>
      <?php if ($products): ?>
        <div class="table-wrapper">
          <table class="store-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Title</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Flags</th>
                <th class="store-table__actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
                <tr>
                  <th scope="row"><?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?></th>
                  <td>
                    <?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($product['is_skuze_official']) || !empty($product['is_official'])): ?>
                      <span class="badge badge-official">Official</span>
                    <?php endif; ?>
                    <?php if (!empty($product['is_skuze_product'])): ?>
                      <span class="badge badge-product">Product</span>
                    <?php endif; ?>
                  </td>
                  <td>$<?= htmlspecialchars(number_format((float) $product['price'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= (int) $product['stock']; ?></td>
                  <td>
                    Official: <?= !empty($product['is_skuze_official']) || !empty($product['is_official']) ? 'Yes' : 'No'; ?><br>
                    Product: <?= !empty($product['is_skuze_product']) ? 'Yes' : 'No'; ?>
                  </td>
                  <td>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="manager_action" value="update_product">
                      <input type="hidden" name="tab" value="products">
                      <input type="hidden" name="sku" value="<?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?>">
                      <div class="form-grid">
                        <label>Title
                          <input type="text" name="title" value="<?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>Price
                          <input type="text" name="price" value="<?= htmlspecialchars(number_format((float) $product['price'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>Stock
                          <input type="number" name="stock" value="<?= (int) $product['stock']; ?>" min="0">
                        </label>
                        <label class="checkbox">
                          <input type="checkbox" name="is_skuze_official" value="1" <?= !empty($product['is_skuze_official']) || !empty($product['is_official']) ? 'checked' : ''; ?>>
                          SkuzE Official
                        </label>
                        <label class="checkbox">
                          <input type="checkbox" name="is_skuze_product" value="1" <?= !empty($product['is_skuze_product']) ? 'checked' : ''; ?>>
                          SkuzE Product
                        </label>
                      </div>
                      <button type="submit" class="btn">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No products found.</p>
      <?php endif; ?>
    </section>

    <section
      id="manager-panel-listings"
      class="store__panel<?= $activeTab === 'listings' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-listings"
      <?= $activeTab === 'listings' ? '' : 'hidden'; ?>
    >
      <h2>Listings</h2>
      <p>Monitor listing state changes and review seller-submitted updates.</p>
      <?php if ($listings): ?>
        <div class="table-wrapper">
          <table class="store-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Quantity</th>
                <th>Owner</th>
                <th class="store-table__actions">Edit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($listings as $listing): ?>
                <tr>
                  <th scope="row">#<?= (int) $listing['id']; ?></th>
                  <td>
                    <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($listing['is_official_listing'])): ?>
                      <span class="badge badge-official">Official</span>
                    <?php endif; ?>
                    <?php if ((int) $listing['pending_requests'] > 0): ?>
                      <div class="badge badge-warning">Pending approval</div>
                    <?php endif; ?>
                    <?php
                      $listingBrandId = isset($listing['brand_id']) ? (int) $listing['brand_id'] : 0;
                      $listingModelId = isset($listing['model_id']) ? (int) $listing['model_id'] : 0;
                      $listingBrandName = $listing['brand_name'] ?? ($listingBrandId > 0 ? ($serviceBrandOptions[$listingBrandId] ?? null) : null);
                      $listingModelName = $listing['model_name'] ?? ($listingModelId > 0 && isset($serviceModelIndex[$listingModelId]) ? $serviceModelIndex[$listingModelId]['name'] : null);
                    ?>
                    <?php if ($listingBrandName || $listingModelName): ?>
                      <small>
                        <?php if ($listingBrandName): ?>
                          <?= htmlspecialchars($listingBrandName, ENT_QUOTES, 'UTF-8'); ?>
                          <?php if ($listingModelName): ?>
                            &middot; <?= htmlspecialchars($listingModelName, ENT_QUOTES, 'UTF-8'); ?>
                          <?php endif; ?>
                        <?php else: ?>
                          <?= htmlspecialchars($listingModelName, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                      </small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(ucfirst((string) $listing['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= $listing['quantity'] !== null ? (int) $listing['quantity'] : '—'; ?></td>
                  <td><?= htmlspecialchars((string) $listing['owner_username'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="manager_action" value="update_listing_details">
                      <input type="hidden" name="tab" value="listings">
                      <input type="hidden" name="listing_id" value="<?= (int) $listing['id']; ?>">
                      <div class="form-grid">
                        <label>Title
                          <input type="text" name="title" value="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>Brand
                          <select name="brand_id">
                            <option value="">Select brand</option>
                            <?php foreach ($serviceBrandOptions as $brandId => $brandName): ?>
                              <option value="<?= $brandId; ?>" <?= $listingBrandId === (int)$brandId ? 'selected' : ''; ?>><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <label>Price
                          <input type="text" name="price" value="<?= htmlspecialchars(number_format((float) $listing['price'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>Quantity
                          <input type="number" name="quantity" value="<?= $listing['quantity'] !== null ? (int) $listing['quantity'] : 0; ?>" min="0">
                        </label>
                        <label>Model
                          <select name="model_id" <?= empty($serviceModelIndex) ? 'disabled' : ''; ?>>
                            <option value="">Select model</option>
                            <?php foreach ($serviceModelIndex as $model): ?>
                              <?php
                                $brandLabel = $serviceBrandOptions[$model['brand_id']] ?? ('Brand ' . $model['brand_id']);
                                $modelLabel = $brandLabel . ' – ' . $model['name'];
                              ?>
                              <option value="<?= $model['id']; ?>" data-brand-id="<?= $model['brand_id']; ?>" <?= $listingModelId === (int)$model['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($modelLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                      </div>
                      <button type="submit" class="btn">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No listings available for review.</p>
      <?php endif; ?>

      <h3>Pending change requests</h3>
      <?php if ($pendingChanges): ?>
        <div class="table-wrapper">
          <table class="store-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Listing</th>
                <th>Requested by</th>
                <th>Submitted</th>
                <th>Payload</th>
                <th class="store-table__actions">Moderation</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingChanges as $request): ?>
                <tr>
                  <th scope="row">#<?= (int) $request['id']; ?></th>
                  <td>
                    <?= htmlspecialchars($request['listing_title'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <small>Status: <?= htmlspecialchars($request['listing_status'], ENT_QUOTES, 'UTF-8'); ?></small>
                  </td>
                  <td><?= htmlspecialchars($request['requester_username'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($request['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if (is_array($request['payload'])): ?>
                      <ul>
                        <?php foreach ($request['payload'] as $field => $value): ?>
                          <li><strong><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>:</strong> <?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <?= htmlspecialchars($request['change_summary'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" class="inline-form" style="margin-bottom:0.5rem;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="manager_action" value="approve_listing_change">
                      <input type="hidden" name="tab" value="listings">
                      <input type="hidden" name="request_id" value="<?= (int) $request['id']; ?>">
                      <button type="submit" class="btn">Approve</button>
                    </form>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="manager_action" value="reject_listing_change">
                      <input type="hidden" name="tab" value="listings">
                      <input type="hidden" name="request_id" value="<?= (int) $request['id']; ?>">
                      <input type="hidden" name="listing_id" value="<?= (int) $request['listing_id']; ?>">
                      <button type="submit" class="btn btn-secondary">Reject</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No pending change requests.</p>
      <?php endif; ?>
    </section>

    <section
      id="manager-panel-inventory"
      class="store__panel<?= $activeTab === 'inventory' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-inventory"
      <?= $activeTab === 'inventory' ? '' : 'hidden'; ?>
    >
      <h2>Inventory</h2>
      <p>Ledger of recent stock adjustments with manual adjustment controls.</p>

      <form method="post" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="manager_action" value="adjust_stock">
        <input type="hidden" name="tab" value="inventory">
        <div class="form-grid">
          <label>SKU
            <input type="text" name="sku" required>
          </label>
          <label>Adjust by
            <input type="number" name="delta" value="0" step="1">
          </label>
          <label>Reorder threshold
            <input type="number" name="reorder_threshold" min="0" step="1">
          </label>
        </div>
        <button type="submit" class="btn">Apply adjustment</button>
      </form>

      <?php if ($inventoryLedger): ?>
        <div class="table-wrapper" style="margin-top:1rem;">
          <table class="store-table">
            <thead>
              <tr>
                <th>Created</th>
                <th>SKU</th>
                <th>Owner</th>
                <th>Type</th>
                <th>Delta</th>
                <th>Before → After</th>
                <th>Reference</th>
                <th>Metadata</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inventoryLedger as $entry): ?>
                <tr>
                  <td><?= htmlspecialchars($entry['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars((string) $entry['product_sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($entry['owner_username'] ?? ('#' . $entry['owner_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($entry['transaction_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= (int) $entry['quantity_change']; ?></td>
                  <td><?= $entry['quantity_before'] ?? '—'; ?> → <?= $entry['quantity_after'] ?? '—'; ?></td>
                  <td>
                    <?php if (!empty($entry['reference_type'])): ?>
                      <?= htmlspecialchars($entry['reference_type'], ENT_QUOTES, 'UTF-8'); ?> #<?= htmlspecialchars((string) $entry['reference_id'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (is_array($entry['metadata'])): ?>
                      <code><?= htmlspecialchars(json_encode($entry['metadata']), ENT_QUOTES, 'UTF-8'); ?></code>
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
        <p>No inventory transactions recorded yet.</p>
      <?php endif; ?>
    </section>

    <section
      id="manager-panel-orders"
      class="store__panel<?= $activeTab === 'orders' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-orders"
      <?= $activeTab === 'orders' ? '' : 'hidden'; ?>
    >
      <h2>Orders</h2>
      <p>Promote orders through the lifecycle from new to completed. Refund support is coming soon.</p>
      <?php if ($orders): ?>
        <div class="table-wrapper">
          <table class="store-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Buyer</th>
                <th>Seller</th>
                <th class="store-table__actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <?php
                  $status = strtolower((string) ($order['shipping_status'] ?? 'new'));
                  if ($status === 'pending') {
                      $status = 'new';
                  }
                  $nextStatus = admin_manager_next_status($status);
                  $paymentStatus = strtolower((string) ($order['payment']['status'] ?? 'pending'));
                ?>
                <tr>
                  <th scope="row">#<?= (int) $order['id']; ?></th>
                  <td><?= htmlspecialchars($order['product']['title'] ?: $order['listing']['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars(ucfirst($paymentStatus), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($order['buyer']['username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($order['listing']['owner_username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ($nextStatus): ?>
                      <form method="post" class="inline-form" style="margin-bottom:0.5rem;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="manager_action" value="advance_order">
                        <input type="hidden" name="tab" value="orders">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn">Advance to <?= htmlspecialchars(ucfirst($nextStatus), ENT_QUOTES, 'UTF-8'); ?></button>
                      </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" disabled title="Refund tooling is coming soon">Refund (coming soon)</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No orders found.</p>
      <?php endif; ?>
    </section>

    <section
      id="manager-panel-shipping"
      class="store__panel<?= $activeTab === 'shipping' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-shipping"
      <?= $activeTab === 'shipping' ? '' : 'hidden'; ?>
    >
      <h2>Shipping</h2>
      <p>Track fulfillments currently in motion and update tracking numbers as shipments go out.</p>
      <?php if ($shippingQueue): ?>
        <div class="table-wrapper">
          <table class="store-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Listing</th>
                <th>Status</th>
                <th>Tracking</th>
                <th class="store-table__actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shippingQueue as $order): ?>
                <?php $status = strtolower((string) ($order['shipping_status'] ?? 'new')); ?>
                <tr>
                  <th scope="row">#<?= (int) $order['id']; ?></th>
                  <td><?= htmlspecialchars($order['listing']['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars($order['tracking_number'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="manager_action" value="update_tracking">
                      <input type="hidden" name="tab" value="shipping">
                      <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                      <label class="sr-only" for="tracking-<?= (int) $order['id']; ?>">Tracking number</label>
                      <input id="tracking-<?= (int) $order['id']; ?>" type="text" name="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tracking number">
                      <button type="submit" class="btn">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No shipments require attention right now.</p>
      <?php endif; ?>
    </section>

    <section
      id="manager-panel-sync"
      class="store__panel<?= $activeTab === 'sync' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-sync"
      <?= $activeTab === 'sync' ? '' : 'hidden'; ?>
    >
      <h2>Sync</h2>
      <p>Square integration flags and runtime hints.</p>
      <ul>
        <li>Square sync: <?= !empty($syncState['enabled']) ? 'Enabled' : 'Disabled'; ?></li>
        <li>Sync direction: <?= htmlspecialchars((string) $syncState['direction'], ENT_QUOTES, 'UTF-8'); ?></li>
        <li>Orders API: <?= !empty($syncState['orders_flag']) ? 'Enabled' : 'Disabled'; ?></li>
      </ul>
      <p>Deeper telemetry will surface here once the sync daemon reports heartbeat metrics.</p>
    </section>

    <section
      id="manager-panel-reports"
      class="store__panel<?= $activeTab === 'reports' ? ' is-active' : ''; ?>"
      role="tabpanel"
      aria-labelledby="manager-tab-reports"
      <?= $activeTab === 'reports' ? '' : 'hidden'; ?>
    >
      <h2>Reports</h2>
      <p>Snapshot of marketplace activity to guide staffing and support follow-up.</p>
      <div class="report-cards">
        <div class="report-card">
          <h3>Total products</h3>
          <p><?= (int) $reports['total_products']; ?></p>
        </div>
        <div class="report-card">
          <h3>Official catalog</h3>
          <p><?= (int) $reports['official_products']; ?></p>
        </div>
        <div class="report-card">
          <h3>Pending listing updates</h3>
          <p><?= (int) $reports['pending_listing_requests']; ?></p>
        </div>
        <div class="report-card">
          <h3>Open orders</h3>
          <p><?= (int) $reports['open_orders']; ?></p>
        </div>
      </div>
      <p>Expanded analytics will follow in later phases once the event stream is available.</p>
    </section>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
