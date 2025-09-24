<?php
/*
 * Discovery note: The app lacked a way to create Square Orders when preparing payments.
 * Change: Added a lightweight order service that wraps the new HTTP client to build orders when enabled.
 */

declare(strict_types=1);

require_once __DIR__ . '/square-log.php';
require_once __DIR__ . '/SquareHttpClient.php';

final class OrderService
{
    private SquareHttpClient $client;

    public function __construct(SquareHttpClient $client)
    {
        $this->client = $client;
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
}
