<?php
declare(strict_types=1);

if (!defined('YOYO_SKIP_DB_BOOTSTRAP')) {
    require_once __DIR__ . '/db.php';
}

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/repositories/InventoryService.php';
require_once __DIR__ . '/repositories/OrdersService.php';

const STORE_SCOPE_MINE = 'mine';
const STORE_SCOPE_OFFICIAL = 'official';
const STORE_SCOPE_ALL = 'all';

/**
 * Determine whether the current session user has administrator rights.
 */
function store_session_is_admin(): bool
{
    if (function_exists('authz_has_role')) {
        return authz_has_role('admin');
    }

    return function_exists('is_admin') ? is_admin() : !empty($_SESSION['is_admin']);
}

/**
 * Resolve the current user's status, fetching from the database when needed.
 */
function store_lookup_user_status(mysqli $conn, int $userId): string
{
    if (!empty($_SESSION['status'])) {
        return (string) $_SESSION['status'];
    }

    $status = '';
    if ($stmt = $conn->prepare('SELECT status FROM users WHERE id = ?')) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $stmt->bind_result($status);
            $stmt->fetch();
        }
        $stmt->close();
    }

    $_SESSION['status'] = $status;

    return (string) $status;
}

/**
 * Fetch the canonical role for a given user.
 */
function store_lookup_user_role(mysqli $conn, int $userId): string
{
    if (function_exists('current_user_id') && current_user_id() === $userId && isset($_SESSION['user_role'])) {
        return (string) $_SESSION['user_role'];
    }

    static $roleCache = [];
    if (array_key_exists($userId, $roleCache)) {
        return $roleCache[$userId];
    }

    $role = 'user';
    if ($stmt = $conn->prepare('SELECT role FROM users WHERE id = ?')) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $stmt->bind_result($roleValue);
            if ($stmt->fetch() && $roleValue !== null) {
                $role = (string) $roleValue;
            }
        }
        $stmt->close();
    }

    $roleCache[$userId] = $role;

    return $role;
}

/**
 * Check whether the user should be treated as a SkuzE Official account.
 */
function store_user_is_official(mysqli $conn, int $userId): bool
{
    if (function_exists('authz_has_role') && current_user_id() === $userId) {
        return authz_has_role('skuze_official');
    }

    if (function_exists('is_skuze_official') && current_user_id() === $userId) {
        return is_skuze_official();
    }

    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    $role = strtolower(store_lookup_user_role($conn, $userId));
    if ($role === 'skuze_official' || $role === 'admin') {
        $cache[$userId] = true;
        return true;
    }

    $status = strtolower(trim(store_lookup_user_status($conn, $userId)));
    $isOfficial = $status === 'skuze official' || $status === 'official';
    $cache[$userId] = $isOfficial;

    return $isOfficial;
}

/**
 * Return the available scope options for the user.
 *
 * @return array<string, string> Map of scope => human readable label
 */
function store_scope_options(bool $isOfficial, bool $isAdmin): array
{
    $options = [
        STORE_SCOPE_MINE => 'My inventory',
    ];

    if ($isOfficial) {
        $options[STORE_SCOPE_OFFICIAL] = 'SkuzE Official inventory';
    }

    if ($isAdmin) {
        $options[STORE_SCOPE_ALL] = 'All inventory';
    }

    return $options;
}

/**
 * Normalise the requested scope to one permitted for the viewer.
 */
function store_resolve_scope(string $requested, bool $isOfficial, bool $isAdmin): string
{
    $allowed = [STORE_SCOPE_MINE];
    if ($isOfficial) {
        $allowed[] = STORE_SCOPE_OFFICIAL;
    }
    if ($isAdmin) {
        $allowed[] = STORE_SCOPE_ALL;
    }

    return in_array($requested, $allowed, true) ? $requested : STORE_SCOPE_MINE;
}

/**
 * Fetch inventory rows for the requested scope.
 *
 * @return array<int, array<string, mixed>>
 */
function store_fetch_inventory(mysqli $conn, int $viewerId, string $scope): array
{
    $sql = 'SELECT p.sku, p.title, p.stock, p.reorder_threshold, p.owner_id, p.is_skuze_official, '
         . 'p.is_skuze_product, u.username AS owner_username '
         . 'FROM products p '
         . 'LEFT JOIN users u ON p.owner_id = u.id';
    $types = '';
    $params = [];

    if ($scope === STORE_SCOPE_MINE) {
        $sql .= ' WHERE p.owner_id = ?';
        $types = 'i';
        $params[] = $viewerId;
    } elseif ($scope === STORE_SCOPE_OFFICIAL) {
        $sql .= ' WHERE p.is_skuze_official = 1 OR p.is_skuze_product = 1';
    }

    $sql .= ' ORDER BY p.title ASC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('[store] Failed to prepare inventory query: ' . $conn->error);
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log('[store] Failed to execute inventory query: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'sku' => $row['sku'],
            'title' => $row['title'],
            'stock' => (int) $row['stock'],
            'reorder_threshold' => (int) $row['reorder_threshold'],
            'owner_id' => (int) $row['owner_id'],
            'owner_username' => $row['owner_username'],
            'is_skuze_official' => (bool) $row['is_skuze_official'],
            'is_skuze_product' => (bool) $row['is_skuze_product'],
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Fetch orders relevant to the current viewer and scope.
 *
 * @return array<int, array<string, mixed>>
 */
function store_fetch_orders(mysqli $conn, int $viewerId, string $scope, bool $isAdmin, bool $isOfficial): array
{
    if ($isAdmin) {
        if ($scope === STORE_SCOPE_OFFICIAL) {
            return fetch_orders_for_admin($conn, true, $viewerId);
        }
        if ($scope === STORE_SCOPE_ALL) {
            return fetch_orders_for_admin($conn, null, $viewerId);
        }
        return fetch_orders_for_user($conn, $viewerId);
    }

    if ($isOfficial && $scope === STORE_SCOPE_OFFICIAL) {
        return fetch_orders_for_admin($conn, true, $viewerId);
    }

    return fetch_orders_for_user($conn, $viewerId);
}

/**
 * Filter orders that the viewer can manage for shipping tasks.
 *
 * @param array<int, array<string, mixed>> $orders
 * @return array<int, array<string, mixed>>
 */
function store_manageable_shipping_orders(array $orders, int $viewerId, bool $isAdmin, bool $isOfficial): array
{
    $manageable = [];
    foreach ($orders as $order) {
        if (!store_user_can_manage_order($order, $viewerId, $isAdmin, $isOfficial)) {
            continue;
        }

        $status = strtolower((string) ($order['shipping_status'] ?? 'pending'));
        if (in_array($status, ['pending', 'processing', 'shipped'], true)) {
            $manageable[] = $order;
        }
    }

    return $manageable;
}

/**
 * Determine if the viewer can manage the provided order.
 */
function store_user_can_manage_order(array $order, int $viewerId, bool $isAdmin, bool $isOfficial): bool
{
    if ($isAdmin) {
        return true;
    }

    if ($isOfficial && (!empty($order['product']['is_skuze_official']) || !empty($order['product']['is_skuze_product']) || !empty($order['listing']['is_official']) || !empty($order['is_official_order']))) {
        return true;
    }

    $ownerId = (int) ($order['listing']['owner_id'] ?? 0);

    return $ownerId === $viewerId;
}

/**
 * Apply a stock delta atomically, optionally updating the reorder threshold.
 *
 * @return array<string, mixed>
 */
function store_apply_inventory_delta(
    mysqli $conn,
    int $viewerId,
    string $sku,
    int $delta,
    ?int $reorderThreshold,
    bool $isAdmin,
    bool $isOfficial
): array {
    $service = new InventoryService($conn);

    return $service->adjustProductStock($sku, $delta, $viewerId, $reorderThreshold, $isAdmin, $isOfficial);
}

/**
 * Update the fulfillment status for an order.
 *
 * @return array<string, mixed>
 */
function store_update_order_status(
    mysqli $conn,
    int $viewerId,
    int $orderId,
    string $status,
    bool $isAdmin,
    bool $isOfficial
): array {
    $service = new OrdersService($conn);

    return $service->updateStatus($orderId, $status, $viewerId, $isAdmin, $isOfficial);
}

/**
 * Update the tracking number for an order fulfillment.
 *
 * @return array<string, mixed>
 */
function store_update_order_tracking(
    mysqli $conn,
    int $viewerId,
    int $orderId,
    ?string $tracking,
    bool $isAdmin,
    bool $isOfficial
): array {
    $service = new OrdersService($conn);

    return $service->updateTracking($orderId, $tracking, $viewerId, $isAdmin, $isOfficial);
}
