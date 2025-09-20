<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/shop_manager.php';
require_once __DIR__ . '/../includes/tags.php';
require_once __DIR__ . '/../includes/repositories/ChangeRequestsService.php';
require_once __DIR__ . '/../includes/repositories/ListingsRepo.php';
require_once __DIR__ . '/../includes/repositories/SquareCatalogSync.php';

$config = require __DIR__ . '/../config.php';

ensure_seller();

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
/** @var mysqli $conn */
if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    $db = require __DIR__ . '/../includes/db.php';
}

$changeRequests = new ChangeRequestsService($db);
$listingsRepository = new ListingsRepo($db, $changeRequests);
$squareSync = new SquareCatalogSync($db, $config);
$squareSyncEnabled = $squareSync->isEnabled();
$isAdmin = authz_has_role('admin');

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
            default:
                shop_manager_flash($redirectTab, 'error', 'Unsupported manager action.');
        }
    }

    $query = ['tab' => $activeTab];
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

$storeInventory = store_fetch_inventory($db, $viewerId, STORE_SCOPE_MINE);
$isOfficial = store_user_is_official($db, $viewerId);
$ordersList = store_fetch_orders($db, $viewerId, STORE_SCOPE_MINE, $isAdmin, $isOfficial);
$shippingList = store_manageable_shipping_orders($ordersList, $viewerId, $isAdmin, $isOfficial);
$fulfillmentStatusOptions = order_fulfillment_status_options();

$csrfToken = generate_token();
$flash = shop_manager_consume_flash($activeTab);
?>
<?php require __DIR__ . '/../includes/layout.php'; ?>
  <title>Shop Manager</title>
  <link rel="stylesheet" href="/assets/style.css">
  <script src="/assets/tags.js" defer></script>
  <script type="module" src="/assets/store.js" defer></script>
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
      include __DIR__ . '/listings.php';
      include __DIR__ . '/inventory.php';
      include __DIR__ . '/orders.php';
      include __DIR__ . '/shipping.php';
      include __DIR__ . '/settings.php';
    ?>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
