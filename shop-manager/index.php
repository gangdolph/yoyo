<?php
/*
 * Discovery note: Seller manager supported tags and status edits but lacked controlled updates for pricing or quantity.
 * Change: Added moderated detail edits with change request escalation, surfaced pending review indicators, and now expose
 *         a Square sync action for administrators.
 * Change: Unified the legacy Store Manager scope controls and catalogue views so Shop Manager is the single dashboard
 *         with products, reports, and fulfillment tooling.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/shop_manager.php';
require_once __DIR__ . '/../includes/tags.php';
require_once __DIR__ . '/../includes/repositories/ChangeRequestsService.php';
require_once __DIR__ . '/../includes/repositories/ListingsRepo.php';
require_once __DIR__ . '/../includes/repositories/SquareCatalogSync.php';
require_once __DIR__ . '/../includes/SyncService.php';
require_once __DIR__ . '/../includes/listing-query.php';
require_once __DIR__ . '/../includes/features.php';
require_once __DIR__ . '/../includes/InventoryService.php';
require_once __DIR__ . '/../includes/TaxonomyService.php';
require_once __DIR__ . '/../includes/WalletService.php';

$config = require __DIR__ . '/../config.php';

ensure_seller();

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
/** @var mysqli $conn */
if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    $db = require __DIR__ . '/../includes/db.php';
}

function shop_manager_table_exists(mysqli $db, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $escaped = $db->real_escape_string($table);
    $result = $db->query(sprintf("SHOW TABLES LIKE '%s'", $escaped));
    $exists = $result !== false && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->free();
    }

    $cache[$table] = $exists;

    return $exists;
}

$changeRequests = new ChangeRequestsService($db);
$listingsRepository = new ListingsRepo($db, $changeRequests);
$squareSync = new SquareCatalogSync($db, $config);
$squareSyncEnabled = $squareSync->isEnabled();
$inventoryService = new InventoryService($db);
$taxonomyService = new TaxonomyService($db);
$walletService = new WalletService($db);
$isAdmin = authz_has_role('admin');
$isOfficialRole = authz_has_role('skuze_official');
$isOfficial = store_user_is_official($db, $viewerId);
$scopeOptions = store_scope_options($isOfficial, $isAdmin);
$requestedScope = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['scope'] ?? $_GET['scope'] ?? STORE_SCOPE_MINE)
    : ($_GET['scope'] ?? STORE_SCOPE_MINE);
$managerScope = store_resolve_scope((string) $requestedScope, $isOfficial, $isAdmin);

$requestedTab = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['tab'] ?? SHOP_MANAGER_DEFAULT_TAB)
    : ($_GET['tab'] ?? SHOP_MANAGER_DEFAULT_TAB);
$activeTab = shop_manager_resolve_tab($requestedTab);
$tabs = shop_manager_tabs();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_token($token)) {
        shop_manager_flash($activeTab, 'error', 'Invalid request token. Please try again.');
    } else {
        $action = $_POST['manager_action'] ?? '';
        $redirectTab = shop_manager_resolve_tab($_POST['tab'] ?? $activeTab);

        switch ($action) {
            case 'update_listing_tags':
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                $tagsInput = trim((string) ($_POST['tags'] ?? ''));
                $tags = tags_from_input($tagsInput);
                $result = $listingsRepository->updateTags($listingId, $viewerId, $tags);
                if (!$result['success']) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to update listing tags.');
                } else {
                    $message = $result['changed'] ? 'Listing tags updated.' : 'No changes were required.';
                    shop_manager_flash($redirectTab, 'success', $message);
                }
                break;
            case 'update_listing_status':
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                $status = (string) ($_POST['status'] ?? '');
                $result = $listingsRepository->updateStatus($listingId, $viewerId, $status, $isAdmin);
                if (!$result['success']) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to update listing status.');
                } else {
                    if (!empty($result['requires_review'])) {
                        $message = 'Status change submitted for review.';
                    } else {
                        $message = $result['changed'] ? 'Listing status updated.' : 'Status is unchanged.';
                    }
                    shop_manager_flash($redirectTab, 'success', $message);
                }
                break;
            case 'delete_listing':
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                $result = $listingsRepository->delete($listingId, $viewerId, $isAdmin);
                if (!$result['success']) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to delete the listing.');
                } else {
                    $message = $result['changed'] ? 'Listing deleted.' : 'Listing was not found or already removed.';
                    shop_manager_flash($redirectTab, 'success', $message);
                }
                break;
            case 'update_listing_details':
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $priceInput = trim((string) ($_POST['price'] ?? ''));
                $quantityInput = trim((string) ($_POST['quantity'] ?? ''));
                $brandInput = trim((string) ($_POST['brand_id'] ?? ''));
                $modelInput = trim((string) ($_POST['model_id'] ?? ''));

                if ($listingId <= 0) {
                    shop_manager_flash($redirectTab, 'error', 'Invalid listing selected.');
                    break;
                }

                if ($priceInput !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $priceInput)) {
                    shop_manager_flash($redirectTab, 'error', 'Prices must be numeric with up to two decimals.');
                    break;
                }

                if ($quantityInput !== '' && !preg_match('/^\d+$/', $quantityInput)) {
                    shop_manager_flash($redirectTab, 'error', 'Quantity adjustments must be whole numbers.');
                    break;
                }

                $payload = [
                    'title' => $title,
                    'price' => $priceInput,
                    'quantity' => $quantityInput,
                    'brand_id' => $brandInput,
                    'model_id' => $modelInput,
                ];

                try {
                    $result = $listingsRepository->updateDetails(
                        $listingId,
                        $viewerId,
                        $payload,
                        $isAdmin,
                        $isOfficialRole
                    );

                    if (!empty($result['requires_review'])) {
                        shop_manager_flash($redirectTab, 'success', 'Change request submitted for approval.');
                    } elseif (!empty($result['changed'])) {
                        shop_manager_flash($redirectTab, 'success', 'Listing details updated.');
                    } else {
                        shop_manager_flash($redirectTab, 'info', 'No changes detected for this listing.');
                    }
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to update listing details: ' . $e->getMessage());
                }
                break;

            case 'sync_listing':
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                if (!$squareSyncEnabled) {
                    shop_manager_flash($redirectTab, 'error', 'Square sync is disabled.');
                    break;
                }

                $listing = $listingsRepository->fetchListing($listingId);
                if (!$listing || ((int) $listing['owner_id'] !== $viewerId && !$isAdmin)) {
                    shop_manager_flash($redirectTab, 'error', 'Listing could not be found.');
                    break;
                }

                try {
                    $squareSync->queueListingSync($listingId, $viewerId);
                    shop_manager_flash($redirectTab, 'success', 'Listing queued for Square sync.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to queue Square sync.');
                }
                break;
            case 'square_sync_now':
                $redirectTab = 'sync';
                if (!$squareSyncEnabled) {
                    shop_manager_flash($redirectTab, 'error', 'Square sync is disabled.');
                    break;
                }

                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only admins or SkuzE Official staff can run manual syncs.');
                    break;
                }

                try {
                    $syncService = new SyncService($db, $config);
                    if (!$syncService->isEnabled()) {
                        shop_manager_flash($redirectTab, 'error', 'Square sync service is not fully configured.');
                        break;
                    }

                    $catalogResult = $syncService->pullCatalog();
                    $variationIds = $catalogResult['variation_ids'] ?? [];
                    $inventoryResult = $variationIds ? $syncService->pullInventory($variationIds) : ['status' => 'no_variations'];

                    $syncedListings = (int) ($catalogResult['synced_listings'] ?? 0);
                    $inventoryApplied = (int) ($inventoryResult['applied'] ?? 0);

                    $summaryParts = [];
                    $summaryParts[] = $syncedListings . ' listing' . ($syncedListings === 1 ? '' : 's') . ' refreshed';
                    if ($inventoryApplied > 0) {
                        $summaryParts[] = $inventoryApplied . ' inventory row' . ($inventoryApplied === 1 ? '' : 's') . ' updated';
                    } else {
                        $summaryParts[] = 'inventory unchanged';
                    }

                    shop_manager_flash(
                        $redirectTab,
                        'success',
                        'Square sync complete: ' . implode(', ', $summaryParts) . '.'
                    );
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Square sync failed: ' . $e->getMessage());
                }
                break;
            case 'taxonomy_create_brand':
                $redirectTab = 'taxonomy';
                if (!feature_taxonomy_enabled()) {
                    shop_manager_flash($redirectTab, 'error', 'Device taxonomy is disabled.');
                    break;
                }
                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only staff can manage brands.');
                    break;
                }
                $brandName = trim((string) ($_POST['brand_name'] ?? ''));
                $brandSlug = trim((string) ($_POST['brand_slug'] ?? ''));
                if ($brandName === '' || $brandSlug === '') {
                    shop_manager_flash($redirectTab, 'error', 'Brand name and slug are required.');
                    break;
                }
                try {
                    $taxonomyService->createBrand($brandName, $brandSlug);
                    shop_manager_flash($redirectTab, 'success', 'Brand created.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to create brand: ' . $e->getMessage());
                }
                break;
            case 'taxonomy_update_brand':
                $redirectTab = 'taxonomy';
                if (!feature_taxonomy_enabled()) {
                    shop_manager_flash($redirectTab, 'error', 'Device taxonomy is disabled.');
                    break;
                }
                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only staff can manage brands.');
                    break;
                }
                $brandId = (int) ($_POST['brand_id'] ?? 0);
                $brandName = trim((string) ($_POST['brand_name'] ?? ''));
                $brandSlug = trim((string) ($_POST['brand_slug'] ?? ''));
                if ($brandId <= 0 || $brandName === '' || $brandSlug === '') {
                    shop_manager_flash($redirectTab, 'error', 'Brand update requires a valid selection, name, and slug.');
                    break;
                }
                try {
                    $taxonomyService->updateBrand($brandId, $brandName, $brandSlug);
                    shop_manager_flash($redirectTab, 'success', 'Brand updated.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to update brand: ' . $e->getMessage());
                }
                break;
            case 'taxonomy_delete_brand':
                $redirectTab = 'taxonomy';
                if (!feature_taxonomy_enabled()) {
                    shop_manager_flash($redirectTab, 'error', 'Device taxonomy is disabled.');
                    break;
                }
                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only staff can manage brands.');
                    break;
                }
                $brandId = (int) ($_POST['brand_id'] ?? 0);
                if ($brandId <= 0) {
                    shop_manager_flash($redirectTab, 'error', 'Brand selection is required for deletion.');
                    break;
                }
                try {
                    $taxonomyService->deleteBrand($brandId);
                    shop_manager_flash($redirectTab, 'success', 'Brand removed.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to delete brand: ' . $e->getMessage());
                }
                break;
            case 'taxonomy_create_model':
                $redirectTab = 'taxonomy';
                if (!feature_taxonomy_enabled()) {
                    shop_manager_flash($redirectTab, 'error', 'Device taxonomy is disabled.');
                    break;
                }
                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only staff can manage models.');
                    break;
                }
                $brandId = (int) ($_POST['brand_id'] ?? 0);
                $modelName = trim((string) ($_POST['model_name'] ?? ''));
                $modelSlug = trim((string) ($_POST['model_slug'] ?? ''));
                if ($brandId <= 0 || $modelName === '' || $modelSlug === '') {
                    shop_manager_flash($redirectTab, 'error', 'Model creation requires brand, name, and slug.');
                    break;
                }
                try {
                    $taxonomyService->createModel($brandId, $modelName, $modelSlug, []);
                    shop_manager_flash($redirectTab, 'success', 'Model created.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to create model: ' . $e->getMessage());
                }
                break;
            case 'taxonomy_update_model':
                $redirectTab = 'taxonomy';
                if (!feature_taxonomy_enabled()) {
                    shop_manager_flash($redirectTab, 'error', 'Device taxonomy is disabled.');
                    break;
                }
                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only staff can manage models.');
                    break;
                }
                $modelId = (int) ($_POST['model_id'] ?? 0);
                $brandId = (int) ($_POST['brand_id'] ?? 0);
                $modelName = trim((string) ($_POST['model_name'] ?? ''));
                $modelSlug = trim((string) ($_POST['model_slug'] ?? ''));
                if ($modelId <= 0 || $brandId <= 0 || $modelName === '' || $modelSlug === '') {
                    shop_manager_flash($redirectTab, 'error', 'Model update requires a valid selection, brand, name, and slug.');
                    break;
                }
                try {
                    $taxonomyService->updateModel($modelId, $brandId, $modelName, $modelSlug, []);
                    shop_manager_flash($redirectTab, 'success', 'Model updated.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to update model: ' . $e->getMessage());
                }
                break;
            case 'taxonomy_delete_model':
                $redirectTab = 'taxonomy';
                if (!feature_taxonomy_enabled()) {
                    shop_manager_flash($redirectTab, 'error', 'Device taxonomy is disabled.');
                    break;
                }
                if (!$isAdmin && !$isOfficialRole) {
                    shop_manager_flash($redirectTab, 'error', 'Only staff can manage models.');
                    break;
                }
                $modelId = (int) ($_POST['model_id'] ?? 0);
                if ($modelId <= 0) {
                    shop_manager_flash($redirectTab, 'error', 'Model selection is required for deletion.');
                    break;
                }
                try {
                    $taxonomyService->deleteModel($modelId);
                    shop_manager_flash($redirectTab, 'success', 'Model removed.');
                } catch (Throwable $e) {
                    shop_manager_flash($redirectTab, 'error', 'Unable to delete model: ' . $e->getMessage());
                }
                break;
            default:
                shop_manager_flash($redirectTab, 'error', 'Unsupported manager action.');
        }
    }

    $query = [
        'tab' => $activeTab,
        'scope' => $managerScope,
    ];
    if (!empty($_POST['filters']) && is_array($_POST['filters'])) {
        foreach ($_POST['filters'] as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $query[$key] = is_string($value) ? $value : (string) $value;
        }
    }

    $redirectUrl = '/shop-manager/index.php';
    if ($query) {
        $redirectUrl .= '?' . http_build_query($query);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

$listingsFilters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'page' => isset($_GET['page']) ? (int) $_GET['page'] : 1,
    'per_page' => isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10,
];
$listingsData = $listingsRepository->paginateForOwner($viewerId, $listingsFilters, $squareSyncEnabled);
$listingsStatuses = $listingsRepository->allowedStatuses();
$serviceBrandOptions = listing_brand_options($db);
$serviceModelIndex = listing_model_index($db);

$productsList = store_fetch_products($db, $viewerId, $managerScope);
$storeInventory = store_fetch_inventory($db, $viewerId, $managerScope);
$ordersList = store_fetch_orders($db, $viewerId, $managerScope, $isAdmin, $isOfficial);
$shippingList = store_manageable_shipping_orders($ordersList, $viewerId, $isAdmin, $isOfficial);
$fulfillmentStatusOptions = order_fulfillment_status_options();
$syncDirection = strtolower((string) ($config['SQUARE_SYNC_DIRECTION'] ?? 'pull'));
$scopeLabel = $scopeOptions[$managerScope] ?? 'My inventory';
$showOwnerColumn = $managerScope !== STORE_SCOPE_MINE;
$listingsItems = $listingsData['items'];
$listingsPagination = $listingsData['pagination'];
$listingsTotal = (int) ($listingsPagination['total'] ?? count($listingsItems));
$reportsSummary = [
    'total_products' => count($productsList),
    'official_products' => count(array_filter(
        $productsList,
        static function (array $product): bool {
            return !empty($product['is_skuze_official']) || !empty($product['is_skuze_product']);
        }
    )),
    'total_listings' => $listingsTotal,
    'pending_listings' => count(array_filter(
        $listingsItems,
        static function (array $listing): bool {
            $status = strtolower((string) ($listing['status'] ?? ''));
            return in_array($status, ['pending', 'under_review', 'review'], true);
        }
    )),
    'open_orders' => count(array_filter(
        $ordersList,
        static function (array $order): bool {
            $status = strtolower((string) ($order['shipping_status'] ?? ''));
            return !in_array($status, ['completed', 'cancelled'], true);
        }
    )),
    'low_stock' => count(array_filter(
        $storeInventory,
        static function (array $item): bool {
            return (int) $item['reorder_threshold'] > 0 && (int) $item['stock'] <= (int) $item['reorder_threshold'];
        }
    )),
    'sync_enabled' => $squareSyncEnabled,
    'sync_direction' => $syncDirection,
    'scope_label' => $scopeLabel,
];

$walletQueue = [];
$walletPendingCount = 0;
if (feature_wallets_enabled()) {
    if (shop_manager_table_exists($db, 'withdrawal_requests')) {
        $result = $db->query('SELECT wr.*, u.username FROM withdrawal_requests wr LEFT JOIN users u ON u.id = wr.user_id ORDER BY wr.created_at DESC LIMIT 20');
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $walletQueue[] = $row;
            }
            $result->free();
        }
    } elseif (shop_manager_table_exists($db, 'wallet_withdrawals')) {
        $result = $db->query('SELECT ww.*, u.username FROM wallet_withdrawals ww LEFT JOIN users u ON u.id = ww.user_id ORDER BY ww.created_at DESC LIMIT 20');
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $walletQueue[] = $row;
            }
            $result->free();
        }
    }

    $walletPendingCount = count(array_filter(
        $walletQueue,
        static function (array $row): bool {
            $status = strtolower((string) ($row['status'] ?? ''));
            return in_array($status, ['requested', 'pending', 'approved', 'processing'], true);
        }
    ));
}

$reportsSummary['pending_withdrawals'] = $walletPendingCount;

$taxonomyBrands = [];
$taxonomyModels = [];
$selectedTaxonomyBrand = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : null;
if (feature_taxonomy_enabled()) {
    $taxonomyBrands = $taxonomyService->listBrands();
    if ($selectedTaxonomyBrand === null && !empty($taxonomyBrands)) {
        $selectedTaxonomyBrand = (int) $taxonomyBrands[0]['id'];
    }

    if ($selectedTaxonomyBrand !== null) {
        try {
            $taxonomyModels = $taxonomyService->listModelsForBrand($selectedTaxonomyBrand);
        } catch (Throwable $e) {
            $taxonomyModels = [];
        }
    }
}

$csrfToken = generate_token();
$flash = shop_manager_consume_flash($activeTab);
?>
<?php require __DIR__ . '/../includes/layout.php'; ?>
  <title>Shop Manager</title>
  <link rel="stylesheet" href="/assets/style.css">
  <script src="/assets/tags.js" defer></script>
  <script type="module" src="/assets/shop-manager.js" defer></script>
</head>
<body>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div
    class="page-container store shop-manager"
    data-shop-manager
    data-active-tab="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>"
    data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
  >
    <header class="store__header">
      <div>
        <h2>Shop Manager</h2>
        <p class="store__subtitle">A single workspace for listings, inventory, and fulfillment tasks.</p>
      </div>
      <?php if (count($scopeOptions) > 1): ?>
        <form class="store__scope" method="get">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
          <?php foreach ($_GET as $key => $value): ?>
            <?php if (in_array($key, ['tab', 'scope'], true)) { continue; } ?>
            <?php if (!is_scalar($value)) { continue; } ?>
            <input type="hidden" name="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>">
          <?php endforeach; ?>
          <label for="shop-manager-scope">Viewing</label>
          <select id="shop-manager-scope" name="scope" onchange="this.form.submit()">
            <?php foreach ($scopeOptions as $value => $label): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?= $managerScope === $value ? 'selected' : ''; ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </header>

    <div class="store__tabs" role="tablist" aria-label="Shop manager sections">
      <?php foreach ($tabs as $tabKey => $tabMeta): ?>
        <?php
          $isActive = $tabKey === $activeTab;
          $buttonId = 'shop-manager-tab-' . $tabKey;
          $panelId = 'shop-manager-panel-' . $tabKey;
        ?>
        <button
          type="button"
          id="<?= htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8'); ?>"
          class="store__tab<?= $isActive ? ' is-active' : ''; ?>"
          role="tab"
          aria-controls="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
          aria-selected="<?= $isActive ? 'true' : 'false'; ?>"
          data-manager-tab="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8'); ?>"
        >
          <?= htmlspecialchars($tabMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="store__alert<?= $flash && $flash['type'] === 'error' ? ' is-error' : ''; ?>" role="status" aria-live="polite" data-manager-alert>
      <?= $flash ? htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') : ''; ?>
    </div>

    <?php
      $listingsItems = $listingsData['items'];
      $listingsFilterState = $listingsData['filters'];
      $listingsPagination = $listingsData['pagination'];
      $orders = $ordersList;
      $shipping = $shippingList;
      $squareSyncAvailable = $squareSyncEnabled;
      $squareSyncDirection = $syncDirection;
      $products = $productsList;
      $reports = $reportsSummary;
      $walletRequests = $walletQueue;
      $selectedBrandId = $selectedTaxonomyBrand;
      $taxonomyBrandList = $taxonomyBrands;
      $taxonomyModelList = $taxonomyModels;
      include __DIR__ . '/dashboard.php';
      include __DIR__ . '/products.php';
      include __DIR__ . '/listings.php';
      include __DIR__ . '/inventory.php';
      include __DIR__ . '/orders.php';
      include __DIR__ . '/shipping.php';
      include __DIR__ . '/sync.php';
      include __DIR__ . '/reports.php';
      include __DIR__ . '/settings.php';
      include __DIR__ . '/wallets.php';
      include __DIR__ . '/taxonomy.php';
    ?>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
