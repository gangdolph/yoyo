<?php
/*
 * Discovery note: The app lacked a way to create Square Orders when preparing payments.
 * Change: Added a lightweight order service that wraps the new HTTP client to build orders when enabled.
 */

declare(strict_types=1);

require_once __DIR__ . '/square-log.php';
require_once __DIR__ . '/SquareHttpClient.php';
require_once __DIR__ . '/InventoryService.php';
require_once __DIR__ . '/ShippingService.php';
require_once __DIR__ . '/features.php';

final class OrderService
{
    private SquareHttpClient $client;
    private ?InventoryService $inventory = null;
    private ?ShippingService $shipping = null;
    private ?mysqli $db = null;

    public function __construct(SquareHttpClient $client)
    {
        $this->client = $client;
    }

    public function enableLocalStack(mysqli $db, InventoryService $inventory, ?ShippingService $shipping = null): void
    {
        $this->db = $db;
        $this->inventory = $inventory;
        $this->shipping = $shipping;
    }

    /**
     * @param array<int, array<string, mixed>> $lineItems
     * @param array{taxes?: array<int, array<string, mixed>>, discounts?: array<int, array<string, mixed>>} $adjustments
     */
    public function createOrder(array $lineItems, array $adjustments = [], ?string $locationId = null): string
    {
        if (empty($lineItems)) {
            throw new InvalidArgumentException('Square orders require at least one line item.');
        }

        $location = $locationId !== null && $locationId !== ''
            ? $locationId
            : $this->client->getLocationId();

        $payload = [
            'idempotency_key' => bin2hex(random_bytes(16)),
            'order' => [
                'location_id' => $location,
                'line_items' => array_values($lineItems),
            ],
        ];

        if (!empty($adjustments['taxes'])) {
            $payload['order']['taxes'] = array_values($adjustments['taxes']);
        }
        if (!empty($adjustments['discounts'])) {
            $payload['order']['discounts'] = array_values($adjustments['discounts']);
        }

        $response = $this->client->request('POST', '/v2/orders', $payload, $payload['idempotency_key']);

        if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
            square_log('square.order_http_error', [
                'status' => $response['statusCode'],
                'body' => $response['raw'],
            ]);
            throw new RuntimeException('Square order creation failed.');
        }

        $body = $response['body'];
        if (!is_array($body) || !isset($body['order']['id'])) {
            square_log('square.order_missing_id', [
                'status' => $response['statusCode'],
                'body' => $response['raw'],
            ]);
            throw new RuntimeException('Square order response missing identifier.');
        }

        $orderId = (string)$body['order']['id'];
        square_log('square.order_created', [
            'order_id' => $orderId,
            'location_id' => $location,
        ]);

        return $orderId;
    }

    /**
     * Reserve inventory and persist local order items for the provided payment identifier.
     *
     * @param array<int, array{product_sku:string, listing_id?:int, quantity:int, unit_price:float, modifiers?:array<string,mixed>}> $items
     */
    public function reserveLocalOrder(int $paymentId, int $buyerId, array $items): void
    {
        if (!feature_order_centralization_enabled() || $this->inventory === null || $this->db === null) {
            return;
        }

        if ($items === []) {
            return;
        }

        $reservations = [];

        try {
            $this->clearOrderItems($paymentId);

            foreach ($items as $item) {
                if (!isset($item['product_sku'], $item['quantity'])) {
                    throw new InvalidArgumentException('Order items must include product_sku and quantity.');
                }

                $sku = (string) $item['product_sku'];
                $quantity = (int) $item['quantity'];
                $reference = [
                    'type' => 'payment',
                    'id' => $paymentId,
                    'buyer_id' => $buyerId,
                    'sku' => $sku,
                ];

                $this->inventory->reserve($sku, $quantity, $reference);
                $reservations[] = [
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'reference' => $reference,
                ];

                $this->upsertOrderItem($paymentId, $item);
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($reservations) as $reservation) {
                try {
                    $this->inventory->release(
                        $reservation['sku'],
                        $reservation['quantity'],
                        $reservation['reference']
                    );
                } catch (Throwable $releaseError) {
                    square_log('square.order_reservation_release_failed', [
                        'payment_id' => $paymentId,
                        'sku' => $reservation['sku'],
                        'quantity' => $reservation['quantity'],
                        'error' => $releaseError->getMessage(),
                    ]);
                }
            }

            try {
                $this->clearOrderItems($paymentId);
            } catch (Throwable $cleanupError) {
                square_log('square.order_item_cleanup_failed', [
                    'payment_id' => $paymentId,
                    'error' => $cleanupError->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Finalise an order after payment success by decrementing inventory.
     */
    public function finalizeLocalOrder(int $paymentId, array $items): void
    {
        if (!feature_order_centralization_enabled() || $this->inventory === null || $this->db === null) {
            return;
        }

        foreach ($items as $item) {
            $sku = (string) $item['product_sku'];
            $quantity = (int) $item['quantity'];
            $reference = [
                'type' => 'payment',
                'id' => $paymentId,
                'sku' => $sku,
            ];

            $this->inventory->decrement($sku, $quantity, $reference);
        }
    }

    /**
     * Release reserved inventory when an order fails or is cancelled.
     */
    public function releaseLocalOrder(int $paymentId, array $items): void
    {
        if (!feature_order_centralization_enabled() || $this->inventory === null || $this->db === null) {
            return;
        }

        foreach ($items as $item) {
            $sku = (string) $item['product_sku'];
            $quantity = (int) $item['quantity'];
            $reference = [
                'type' => 'payment',
                'id' => $paymentId,
                'sku' => $sku,
            ];

            $this->inventory->release($sku, $quantity, $reference);
        }
    }

    /**
     * Simple accessor to connected shipping service when enabled.
     */
    public function shippingService(): ?ShippingService
    {
        return $this->shipping;
    }

    private function upsertOrderItem(int $paymentId, array $item): void
    {
        $stmt = $this->db->prepare('INSERT INTO order_items (payment_id, product_sku, listing_id, quantity, unit_price, modifiers, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare order item insert.');
        }

        $listingId = isset($item['listing_id']) ? (int) $item['listing_id'] : null;
        $quantity = max(1, (int) $item['quantity']);
        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;
        $modifiers = isset($item['modifiers']) ? json_encode($item['modifiers']) : null;
        $subtotal = $unitPrice * $quantity;

        $stmt->bind_param(
            'isiidsd',
            $paymentId,
            $item['product_sku'],
            $listingId,
            $quantity,
            $unitPrice,
            $modifiers,
            $subtotal
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to persist order item: ' . $error);
        }

        $stmt->close();
    }

    private function clearOrderItems(int $paymentId): void
    {
        $stmt = $this->db->prepare('DELETE FROM order_items WHERE payment_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare order item cleanup.');
        }

        $stmt->bind_param('i', $paymentId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to reset order items: ' . $error);
        }

        $stmt->close();
    }
}
