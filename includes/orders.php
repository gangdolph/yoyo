<?php
/**
 * Helper functions for retrieving enriched order data.
 */

if (!defined('YOYO_SKIP_DB_BOOTSTRAP')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Fetch all orders that involve the provided viewer.
 *
 * @param mysqli $conn
 * @param int    $viewerId
 * @return array<int, array<string, mixed>>
 */
function fetch_orders_for_user(mysqli $conn, int $viewerId): array {
    $sql = _orders_build_select_sql(
        'CASE WHEN l.owner_id = ? THEN "sell" WHEN of.user_id = ? OR pay.user_id = ? THEN "buy" ELSE "observer" END'
    );
    $sql .= ' WHERE (of.user_id = ? OR l.owner_id = ? OR pay.user_id = ?)'
         . ' ORDER BY of.created_at DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Failed to prepare order lookup: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('iiiiii', $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId);
    if (!$stmt->execute()) {
        error_log('Failed to execute order lookup: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = _orders_normalize_row($row);
    }
    $stmt->close();

    return $orders;
}

/**
 * Fetch all orders for the admin dashboard.
 *
 * @param mysqli   $conn
 * @param bool|null $officialOnly true to fetch only official inventory, false for community, null for all
 * @param int|null $viewerId Optional viewer for direction context.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_orders_for_admin(mysqli $conn, ?bool $officialOnly = null, ?int $viewerId = null): array {
    $viewer = $viewerId ?? 0;
    $sql = _orders_build_select_sql(
        'CASE WHEN l.owner_id = ? THEN "sell" WHEN of.user_id = ? OR pay.user_id = ? THEN "buy" ELSE "observer" END'
    );
    $sql .= ' WHERE 1=1';
    if ($officialOnly === true) {
        $sql .= ' AND (prod.is_skuze_official = 1 OR prod.is_skuze_product = 1 OR l.is_official_listing = 1 OR of.is_official_order = 1)';
    } elseif ($officialOnly === false) {
        $sql .= ' AND (COALESCE(prod.is_skuze_official, 0) = 0 AND COALESCE(prod.is_skuze_product, 0) = 0 AND COALESCE(l.is_official_listing, 0) = 0 AND COALESCE(of.is_official_order, 0) = 0)';
    }
    $sql .= ' ORDER BY of.created_at DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Failed to prepare admin order lookup: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('iii', $viewer, $viewer, $viewer);
    if (!$stmt->execute()) {
        error_log('Failed to execute admin order lookup: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = _orders_normalize_row($row);
    }
    $stmt->close();

    return $orders;
}

/**
 * Fetch a single order for the current user, enforcing participation.
 */
function fetch_order_detail_for_user(mysqli $conn, int $orderId, int $viewerId): ?array {
    $sql = _orders_build_select_sql(
        'CASE WHEN l.owner_id = ? THEN "sell" WHEN of.user_id = ? OR pay.user_id = ? THEN "buy" ELSE "observer" END'
    );
    $sql .= ' WHERE of.id = ? AND (of.user_id = ? OR l.owner_id = ? OR pay.user_id = ?)'
         . ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Failed to prepare order detail lookup: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('iiiiiii', $viewerId, $viewerId, $viewerId, $orderId, $viewerId, $viewerId, $viewerId);
    if (!$stmt->execute()) {
        error_log('Failed to execute order detail lookup: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? _orders_normalize_row($row) : null;
}

/**
 * Fetch a single order for administrative review.
 */
function fetch_order_detail_for_admin(mysqli $conn, int $orderId, ?int $viewerId = null): ?array {
    $viewer = $viewerId ?? 0;
    $sql = _orders_build_select_sql(
        'CASE WHEN l.owner_id = ? THEN "sell" WHEN of.user_id = ? OR pay.user_id = ? THEN "buy" ELSE "observer" END'
    );
    $sql .= ' WHERE of.id = ? LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Failed to prepare admin order detail lookup: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('iiii', $viewer, $viewer, $viewer, $orderId);
    if (!$stmt->execute()) {
        error_log('Failed to execute admin order detail lookup: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? _orders_normalize_row($row) : null;
}

/**
 * Build the base SELECT clause for order lookups.
 */
function _orders_build_select_sql(string $directionExpression): string {
    return 'SELECT'
        . ' of.id AS id,'
        . ' of.status AS shipping_status,'
        . ' of.tracking_number,'
        . ' of.delivery_method,'
        . ' of.notes,'
        . ' of.is_official_order,'
        . ' of.shipping_snapshot,'
        . ' of.created_at AS placed_at,'
        . ' of.user_id AS fulfillment_user_id,'
        . ' pay.id AS payment_id,'
        . ' pay.status AS payment_status,'
        . ' pay.amount AS payment_amount,'
        . ' pay.payment_id AS payment_reference,'
        . ' pay.created_at AS payment_created_at,'
        . ' pay.user_id AS payment_user_id,'
        . ' l.id AS listing_id,'
        . ' l.title AS listing_title,'
        . ' l.owner_id AS listing_owner_id,'
        . ' l.price AS listing_price,'
        . ' l.status AS listing_status,'
        . ' l.is_official_listing AS listing_is_official,'
        . ' prod.sku AS product_sku,'
        . ' prod.title AS product_title,'
        . ' prod.stock AS product_stock,'
        . ' prod.reorder_threshold AS product_reorder_threshold,'
        . ' prod.is_skuze_official AS product_is_skuze_official,'
        . ' prod.is_skuze_product AS product_is_skuze_product,'
        . ' seller.username AS seller_username,'
        . ' buyer.username AS buyer_username,'
        . ' ' . $directionExpression . ' AS direction'
        . ' FROM order_fulfillments of'
        . ' JOIN listings l ON of.listing_id = l.id'
        . ' LEFT JOIN products prod ON l.product_sku = prod.sku'
        . ' LEFT JOIN payments pay ON of.payment_id = pay.id'
        . ' LEFT JOIN users seller ON l.owner_id = seller.id'
        . ' LEFT JOIN users buyer ON pay.user_id = buyer.id';
}

/**
 * Normalise database rows into a structured array.
 */
function _orders_normalize_row(array $row): array {
    return [
        'id' => (int) $row['id'],
        'direction' => $row['direction'],
        'shipping_status' => $row['shipping_status'] ?? 'pending',
        'tracking_number' => $row['tracking_number'],
        'delivery_method' => $row['delivery_method'],
        'notes' => $row['notes'],
        'is_official_order' => !empty($row['is_official_order']),
        'shipping_snapshot' => $row['shipping_snapshot'],
        'placed_at' => $row['placed_at'],
        'fulfillment_user_id' => (int) $row['fulfillment_user_id'],
        'listing' => [
            'id' => (int) $row['listing_id'],
            'title' => $row['listing_title'],
            'price' => $row['listing_price'],
            'status' => $row['listing_status'],
            'owner_id' => (int) $row['listing_owner_id'],
            'owner_username' => $row['seller_username'],
            'is_official' => !empty($row['listing_is_official']),
        ],
        'product' => [
            'sku' => $row['product_sku'],
            'title' => $row['product_title'],
            'stock' => $row['product_stock'] !== null ? (int) $row['product_stock'] : null,
            'reorder_threshold' => $row['product_reorder_threshold'] !== null ? (int) $row['product_reorder_threshold'] : null,
            'is_skuze_official' => $row['product_is_skuze_official'] !== null ? (bool) $row['product_is_skuze_official'] : null,
            'is_skuze_product' => $row['product_is_skuze_product'] !== null ? (bool) $row['product_is_skuze_product'] : null,
            'is_official' => (!empty($row['product_is_skuze_official']) || !empty($row['listing_is_official'])),
        ],
        'payment' => [
            'id' => $row['payment_id'] !== null ? (int) $row['payment_id'] : null,
            'amount' => $row['payment_amount'] !== null ? (int) $row['payment_amount'] : null,
            'status' => $row['payment_status'],
            'reference' => $row['payment_reference'],
            'created_at' => $row['payment_created_at'],
        ],
        'buyer' => [
            'id' => $row['payment_user_id'] !== null ? (int) $row['payment_user_id'] : null,
            'username' => $row['buyer_username'],
        ],
    ];
}

/**
 * Return the canonical set of fulfillment statuses administrators can apply.
 *
 * @return array<string, string>
 */
function order_fulfillment_status_options(): array {
    return [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
}

/**
 * Calculate the desired inventory delta for an admin order update.
 *
 * @param int    $manualDelta  Direct quantity adjustment requested by the admin.
 * @param bool   $autoRestock  Whether auto restock was requested.
 * @param string $status       The target fulfillment status.
 * @param int    $restockUnits How many units should be returned when restocking automatically.
 */
function order_admin_compute_inventory_delta(int $manualDelta, bool $autoRestock, string $status, int $restockUnits = 1): int {
    $delta = $manualDelta;
    if ($autoRestock && $status === 'cancelled') {
        $delta += $restockUnits;
    }

    return $delta;
}
