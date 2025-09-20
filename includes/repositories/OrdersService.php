<?php
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';
require_once __DIR__ . '/InventoryService.php';
require_once __DIR__ . '/../orders.php';

final class OrdersService
{
    private mysqli $conn;
    private InventoryService $inventory;

    public function __construct(mysqli $conn, ?InventoryService $inventory = null)
    {
        $this->conn = $conn;
        $this->inventory = $inventory ?? new InventoryService($conn);
    }

    /**
     * Create an order fulfillment record and capture the reserved inventory.
     *
     * @param array<string, mixed> $shipping
     * @param array<string, mixed> $options
     * @return array{order_id: int}
     */
    public function createFulfillment(
        int $paymentId,
        int $buyerId,
        int $listingId,
        array $shipping,
        array $options = []
    ): array {
        $quantity = max(1, (int) ($options['quantity'] ?? 1));
        $sku = isset($options['sku']) ? (string) $options['sku'] : null;
        $isOfficialOrder = !empty($options['is_official_order']) ? 1 : 0;
        $shippingProfileId = isset($options['shipping_profile_id']) ? (int) $options['shipping_profile_id'] : null;
        $notes = isset($shipping['notes']) ? (string) $shipping['notes'] : '';
        $address = isset($shipping['address']) ? (string) $shipping['address'] : '';
        $deliveryMethod = isset($shipping['delivery_method']) ? (string) $shipping['delivery_method'] : '';
        $snapshot = $shipping['snapshot'] ?? null;

        $stmt = $this->conn->prepare(
            'INSERT INTO order_fulfillments '
            . '(payment_id, user_id, listing_id, sku, shipping_address, delivery_method, notes, tracking_number, status, shipping_profile_id, shipping_snapshot, is_official_order) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NULL, "pending", NULLIF(?, 0), ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare order creation.');
        }

        $stmt->bind_param(
            'iiissssisi',
            $paymentId,
            $buyerId,
            $listingId,
            $sku,
            $address,
            $deliveryMethod,
            $notes,
            $shippingProfileId,
            $snapshot,
            $isOfficialOrder
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to create order fulfillment.');
        }

        $orderId = (int) $stmt->insert_id;
        $stmt->close();

        $this->inventory->captureListing($listingId, $quantity, $buyerId, $orderId);

        shop_log('order.created', [
            'order_id' => $orderId,
            'listing_id' => $listingId,
            'buyer_id' => $buyerId,
            'quantity' => $quantity,
        ]);

        return ['order_id' => $orderId];
    }

    /**
     * Update an order fulfillment status with permission enforcement.
     *
     * @return array{order_id: int, status: string, status_label: string}
     */
    public function updateStatus(int $orderId, string $status, int $viewerId, bool $isAdmin, bool $isOfficial): array
    {
        $statusOptions = order_fulfillment_status_options();
        if (!array_key_exists($status, $statusOptions)) {
            throw new RuntimeException('Unsupported fulfillment status.');
        }

        $order = $isAdmin
            ? fetch_order_detail_for_admin($this->conn, $orderId, $viewerId)
            : fetch_order_detail_for_user($this->conn, $orderId, $viewerId);

        if (!$order && $isOfficial) {
            $order = fetch_order_detail_for_admin($this->conn, $orderId, $viewerId);
        }

        if (!$order) {
            throw new RuntimeException('Order could not be found.');
        }

        if (!$this->canManageOrder($order, $viewerId, $isAdmin, $isOfficial)) {
            throw new RuntimeException('You do not have permission to update this order.');
        }

        $stmt = $this->conn->prepare('UPDATE order_fulfillments SET status = ? WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare order update.');
        }

        $stmt->bind_param('si', $status, $orderId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to update the order status.');
        }
        $stmt->close();

        shop_log('order.status_updated', [
            'order_id' => $orderId,
            'actor_id' => $viewerId,
            'status' => $status,
        ]);

        return [
            'order_id' => $orderId,
            'status' => $status,
            'status_label' => $statusOptions[$status],
        ];
    }

    /**
     * Update tracking information for a fulfillment.
     *
     * @return array{order_id: int, tracking_number: ?string}
     */
    public function updateTracking(int $orderId, ?string $tracking, int $viewerId, bool $isAdmin, bool $isOfficial): array
    {
        $tracking = $tracking !== null ? trim($tracking) : null;
        if ($tracking === '') {
            $tracking = null;
        }

        if ($tracking !== null && strlen($tracking) > 100) {
            throw new RuntimeException('Tracking numbers must be 100 characters or fewer.');
        }

        $order = $isAdmin
            ? fetch_order_detail_for_admin($this->conn, $orderId, $viewerId)
            : fetch_order_detail_for_user($this->conn, $orderId, $viewerId);

        if (!$order && $isOfficial) {
            $order = fetch_order_detail_for_admin($this->conn, $orderId, $viewerId);
        }

        if (!$order) {
            throw new RuntimeException('Order could not be found.');
        }

        if (!$this->canManageOrder($order, $viewerId, $isAdmin, $isOfficial)) {
            throw new RuntimeException('You do not have permission to update tracking for this order.');
        }

        if ($tracking !== null) {
            $stmt = $this->conn->prepare('UPDATE order_fulfillments SET tracking_number = ? WHERE id = ?');
        } else {
            $stmt = $this->conn->prepare('UPDATE order_fulfillments SET tracking_number = NULL WHERE id = ?');
        }

        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare tracking update.');
        }

        if ($tracking !== null) {
            $stmt->bind_param('si', $tracking, $orderId);
        } else {
            $stmt->bind_param('i', $orderId);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to update tracking details.');
        }
        $stmt->close();

        shop_log('order.tracking_updated', [
            'order_id' => $orderId,
            'actor_id' => $viewerId,
            'tracking' => $tracking,
        ]);

        return [
            'order_id' => $orderId,
            'tracking_number' => $tracking,
        ];
    }

    /**
     * Determine whether the viewer can manage the order.
     *
     * @param array<string, mixed> $order
     */
    private function canManageOrder(array $order, int $viewerId, bool $isAdmin, bool $isOfficial): bool
    {
        if ($isAdmin) {
            return true;
        }

        if ($isOfficial && (
            !empty($order['product']['is_skuze_official'])
            || !empty($order['product']['is_skuze_product'])
            || !empty($order['listing']['is_official'])
            || !empty($order['is_official_order'])
        )) {
            return true;
        }

        $ownerId = (int) ($order['listing']['owner_id'] ?? 0);
        return $ownerId === $viewerId;
    }
}
