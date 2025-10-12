<?php
/*
 * Discovery note: Orders service allowed arbitrary status jumps in the legacy pending/processing flow.
 * Change: Normalised to the New→Completed pipeline with sequential enforcement and cancellation guardrails.
 * Change: Added wallet settlement hooks so escrow holds release automatically on completion or cancellation.
 */
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';
if (!class_exists('InventoryService')) {
    require_once __DIR__ . '/InventoryService.php';
}
require_once __DIR__ . '/../orders.php';
require_once __DIR__ . '/../WalletService.php';

final class OrdersService
{
    private mysqli $conn;
    private InventoryService $inventory;
    private ?WalletService $wallet;
    /**
     * @var array<string, array<int, string>>
     */
    private array $statusTransitions = [
        'pending' => ['new', 'cancelled'],
        'new' => ['paid', 'cancelled'],
        'paid' => ['packing', 'cancelled'],
        'packing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(mysqli $conn, ?InventoryService $inventory = null, ?WalletService $wallet = null)
    {
        $this->conn = $conn;
        $this->inventory = $inventory ?? new InventoryService($conn);
        $this->wallet = $wallet;
    }

    public function attachWalletService(WalletService $wallet): void
    {
        $this->wallet = $wallet;
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

        $existingOrderId = null;
        $check = $this->conn->prepare('SELECT id FROM order_fulfillments WHERE payment_id = ? LIMIT 1');
        if ($check !== false) {
            $check->bind_param('i', $paymentId);
            if ($check->execute()) {
                $check->bind_result($existingOrderId);
                if ($check->fetch()) {
                    $check->close();
                    return ['order_id' => (int) $existingOrderId];
                }
            }
            $check->close();
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO order_fulfillments '
            . '(payment_id, user_id, listing_id, sku, shipping_address, delivery_method, notes, tracking_number, status, shipping_profile_id, shipping_snapshot, is_official_order) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NULL, "new", NULLIF(?, 0), ?, ?)'
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
        $status = $this->normaliseStatus($status);
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

        $currentStatus = $this->normaliseStatus((string) ($order['shipping_status'] ?? 'new'));
        if ($currentStatus === $status) {
            return [
                'order_id' => $orderId,
                'status' => $status,
                'status_label' => $statusOptions[$status],
            ];
        }

        $allowed = $this->statusTransitions[$currentStatus] ?? [];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('The order cannot transition from ' . $currentStatus . ' to ' . $status . '.');
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

        if ($status === 'completed') {
            $this->settleWalletHold($orderId, false);
        } elseif ($status === 'cancelled') {
            $this->settleWalletHold($orderId, true);
        }

        return [
            'order_id' => $orderId,
            'status' => $status,
            'status_label' => $statusOptions[$status],
        ];
    }

    private function settleWalletHold(int $orderId, bool $refundBuyer): void
    {
        try {
            if ($this->wallet === null) {
                $this->wallet = new WalletService($this->conn);
            }
        } catch (Throwable $walletBootstrap) {
            error_log('[OrdersService] wallet bootstrap failed: ' . $walletBootstrap->getMessage());
            return;
        }

        if ($this->wallet === null) {
            return;
        }

        try {
            $order = fetch_order_detail_for_admin($this->conn, $orderId, null);
        } catch (Throwable $orderFetchError) {
            error_log('[OrdersService] wallet settle fetch failed: ' . $orderFetchError->getMessage());
            return;
        }

        if (!$order) {
            return;
        }

        $reference = (string) ($order['payment_reference'] ?? '');
        if (strpos($reference, 'wallet-') !== 0) {
            return;
        }

        $idempotencyKey = 'wallet-settle-' . $orderId . ($refundBuyer ? '-refund' : '-release');
        try {
            $this->wallet->releaseHold($orderId, $idempotencyKey, !$refundBuyer);
        } catch (Throwable $releaseError) {
            error_log('[OrdersService] wallet release failed: ' . $releaseError->getMessage());
            return;
        }

        if (!empty($order['payment_id'])) {
            $stmt = $this->conn->prepare('UPDATE payments SET status = ? WHERE id = ?');
            if ($stmt) {
                $status = $refundBuyer ? 'WALLET_REFUNDED' : 'WALLET_SETTLED';
                $paymentId = (int) $order['payment_id'];
                $stmt->bind_param('si', $status, $paymentId);
                $stmt->execute();
                $stmt->close();
            }
        }
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

    private function normaliseStatus(string $status): string
    {
        $status = strtolower(trim($status));

        switch ($status) {
            case 'pending':
                return 'new';
            case 'processing':
                return 'packing';
            default:
                return $status;
        }
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
