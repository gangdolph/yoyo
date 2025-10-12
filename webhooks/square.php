<?php
/*
 * Discovery note: The application received Square callbacks without any verification or
 * persistence, leaving payments and inventory out of sync.
 * Change: Added a webhook endpoint that verifies signatures, de-duplicates events, and updates
 *         payments, orders, and inventory while logging sync health.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/square-log.php';
require_once __DIR__ . '/../includes/square-migrations.php';
if (!class_exists('InventoryService')) {
    require_once __DIR__ . '/../includes/repositories/InventoryService.php';
}

$config = require __DIR__ . '/../config.php';
/** @var mysqli $conn */
$conn = require __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

square_run_migrations($conn);

$body = file_get_contents('php://input');
if ($body === false) {
    $body = '';
}

$secret = trim((string) ($config['square_webhook_signature_key'] ?? ''));
$signature = (string) ($_SERVER['HTTP_X_SQUARE_SIGNATURE'] ?? '');

if ($secret !== '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $uri = $_SERVER['REQUEST_URI'] ?? '/webhooks/square.php';
    $notificationUrl = $scheme . '://' . $host . $uri;
    $computed = base64_encode(hash_hmac('sha1', $notificationUrl . $body, $secret, true));
    if (!hash_equals($computed, $signature)) {
        square_log('square.webhook_signature_invalid', [
            'notification_url' => $notificationUrl,
        ]);
        http_response_code(400);
        echo json_encode(['error' => 'invalid_signature']);
        exit;
    }
} else {
    square_log('square.webhook_signature_missing', []);
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    square_log('square.webhook_invalid_payload', []);
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit;
}

$eventId = (string) ($payload['event_id'] ?? '');
$eventType = (string) ($payload['type'] ?? '');

if ($eventId === '' || $eventType === '') {
    square_log('square.webhook_missing_event', ['payload' => $payload]);
    http_response_code(400);
    echo json_encode(['error' => 'invalid_event']);
    exit;
}

if (!square_webhook_mark_processed($conn, $eventId, $eventType)) {
    square_log('square.webhook_duplicate', ['event_id' => $eventId, 'type' => $eventType]);
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

try {
    $handled = false;
    switch ($eventType) {
        case 'payment.updated':
            $handled = square_webhook_handle_payment($conn, $payload);
            break;
        case 'order.updated':
            $handled = square_webhook_handle_order($conn, $payload);
            break;
        case 'inventory.count.updated':
            $handled = square_webhook_handle_inventory($conn, $payload);
            break;
        default:
            square_log('square.webhook_unhandled', ['event_id' => $eventId, 'type' => $eventType]);
            $handled = true;
            break;
    }

    square_webhook_touch_state($conn, ['last_webhook_at' => date('Y-m-d H:i:s')]);

    if ($handled) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(202);
        echo json_encode(['status' => 'ignored']);
    }
} catch (Throwable $e) {
    square_log('square.webhook_exception', [
        'event_id' => $eventId,
        'type' => $eventType,
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}

/**
 * Persist the webhook event identifier for idempotency checks.
 */
function square_webhook_mark_processed(mysqli $conn, string $eventId, string $eventType): bool
{
    $stmt = $conn->prepare(
        'INSERT INTO square_processed_events (event_id, event_type, received_at) VALUES (?, ?, NOW()) '
        . 'ON DUPLICATE KEY UPDATE event_id = event_id'
    );
    if ($stmt === false) {
        return true;
    }

    $stmt->bind_param('ss', $eventId, $eventType);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected === 1;
}

function square_webhook_touch_state(mysqli $conn, array $fields): void
{
    if (!$fields) {
        return;
    }

    $columns = [];
    $updates = [];
    $types = 's';
    $values = ['square_core'];

    foreach ($fields as $column => $value) {
        $columns[] = sprintf('`%s`', $column);
        $updates[] = sprintf('`%s` = VALUES(`%s`)', $column, $column);
        $types .= 's';
        $values[] = (string) $value;
    }

    $placeholders = implode(', ', array_fill(0, count($columns) + 1, '?'));
    $sql = sprintf(
        'INSERT INTO square_sync_state (setting_key, %s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        implode(', ', $columns),
        $placeholders,
        implode(', ', $updates)
    );

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return;
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function square_webhook_handle_payment(mysqli $conn, array $payload): bool
{
    $payment = $payload['data']['object']['payment'] ?? null;
    if (!is_array($payment)) {
        return false;
    }

    $paymentId = (string) ($payment['id'] ?? '');
    if ($paymentId === '') {
        return false;
    }

    $status = strtoupper((string) ($payment['status'] ?? ''));

    $paymentRecordId = null;
    if ($stmt = $conn->prepare('SELECT id FROM payments WHERE payment_id = ? LIMIT 1')) {
        $stmt->bind_param('s', $paymentId);
        if ($stmt->execute()) {
            $stmt->bind_result($localId);
            if ($stmt->fetch()) {
                $paymentRecordId = (int) $localId;
            }
        }
        $stmt->close();
    }

    if ($paymentRecordId !== null && $stmt = $conn->prepare('UPDATE payments SET status = ? WHERE id = ?')) {
        $stmt->bind_param('si', $status, $paymentRecordId);
        $stmt->execute();
        $stmt->close();
    }

    if ($paymentRecordId === null) {
        return true;
    }

    $targetStatus = null;
    if (in_array($status, ['COMPLETED', 'APPROVED'], true)) {
        $targetStatus = 'paid';
    } elseif (in_array($status, ['CANCELED', 'FAILED'], true)) {
        $targetStatus = 'cancelled';
    }

    if ($targetStatus === null) {
        return true;
    }

    $fulfillments = [];
    if ($stmt = $conn->prepare('SELECT id, status FROM order_fulfillments WHERE payment_id = ?')) {
        $stmt->bind_param('i', $paymentRecordId);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $fulfillments[] = [
                    'id' => (int) $row['id'],
                    'status' => (string) ($row['status'] ?? ''),
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    foreach ($fulfillments as $fulfillment) {
        $current = strtolower($fulfillment['status']);
        $shouldUpdate = false;
        if ($targetStatus === 'paid' && in_array($current, ['pending', 'new'], true)) {
            $shouldUpdate = true;
        }
        if ($targetStatus === 'cancelled' && $current !== 'completed') {
            $shouldUpdate = true;
        }

        if (!$shouldUpdate) {
            continue;
        }

        if ($stmt = $conn->prepare('UPDATE order_fulfillments SET status = ? WHERE id = ?')) {
            $stmt->bind_param('si', $targetStatus, $fulfillment['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    square_log('square.webhook_payment', [
        'payment_id' => $paymentId,
        'status' => $status,
        'target_status' => $targetStatus,
    ]);

    return true;
}

function square_webhook_handle_order(mysqli $conn, array $payload): bool
{
    $order = $payload['data']['object']['order'] ?? null;
    if (!is_array($order)) {
        return false;
    }

    $state = strtoupper((string) ($order['state'] ?? ''));
    $paymentIds = [];
    $tenders = $order['tenders'] ?? [];
    if (is_array($tenders)) {
        foreach ($tenders as $tender) {
            if (is_array($tender) && isset($tender['payment_id'])) {
                $paymentId = (string) $tender['payment_id'];
                if ($paymentId !== '') {
                    $paymentIds[] = $paymentId;
                }
            }
        }
    }

    if (!$paymentIds) {
        return false;
    }

    $stateMap = [
        'OPEN' => 'paid',
        'COMPLETED' => 'completed',
        'CANCELED' => 'cancelled',
        'DRAFT' => 'new',
    ];
    $targetStatus = $stateMap[$state] ?? null;
    if ($targetStatus === null) {
        return false;
    }

    foreach ($paymentIds as $remotePaymentId) {
        $paymentRecordId = null;
        if ($stmt = $conn->prepare('SELECT id FROM payments WHERE payment_id = ? LIMIT 1')) {
            $stmt->bind_param('s', $remotePaymentId);
            if ($stmt->execute()) {
                $stmt->bind_result($localId);
                if ($stmt->fetch()) {
                    $paymentRecordId = (int) $localId;
                }
            }
            $stmt->close();
        }

        if ($paymentRecordId === null) {
            continue;
        }

        $fulfillments = [];
        if ($stmt = $conn->prepare('SELECT id, status FROM order_fulfillments WHERE payment_id = ?')) {
            $stmt->bind_param('i', $paymentRecordId);
            if ($stmt->execute() && ($result = $stmt->get_result())) {
                while ($row = $result->fetch_assoc()) {
                    $fulfillments[] = [
                        'id' => (int) $row['id'],
                        'status' => (string) ($row['status'] ?? ''),
                    ];
                }
                $result->free();
            }
            $stmt->close();
        }

        foreach ($fulfillments as $fulfillment) {
            $current = strtolower($fulfillment['status']);
            $shouldUpdate = false;
            if ($targetStatus === 'paid' && in_array($current, ['new', 'pending'], true)) {
                $shouldUpdate = true;
            } elseif ($targetStatus === 'completed' && !in_array($current, ['cancelled', 'completed'], true)) {
                $shouldUpdate = true;
            } elseif ($targetStatus === 'cancelled' && $current !== 'completed') {
                $shouldUpdate = true;
            } elseif ($targetStatus === 'new' && $current === 'pending') {
                $shouldUpdate = true;
            }

            if (!$shouldUpdate) {
                continue;
            }

            if ($stmt = $conn->prepare('UPDATE order_fulfillments SET status = ? WHERE id = ?')) {
                $stmt->bind_param('si', $targetStatus, $fulfillment['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    square_log('square.webhook_order', [
        'state' => $state,
        'target_status' => $targetStatus,
        'payments' => $paymentIds,
    ]);

    return true;
}

function square_webhook_handle_inventory(mysqli $conn, array $payload): bool
{
    $inventory = $payload['data']['object']['inventory_counts'] ?? null;
    if (!is_array($inventory)) {
        return false;
    }

    $service = new InventoryService($conn);
    $applied = 0;

    foreach ($inventory as $count) {
        if (!is_array($count)) {
            continue;
        }

        $squareId = isset($count['catalog_object_id']) ? (string) $count['catalog_object_id'] : '';
        if ($squareId === '') {
            continue;
        }

        $quantity = $count['quantity'] ?? null;
        if ($quantity === null) {
            continue;
        }
        $quantityValue = is_string($quantity) || is_numeric($quantity)
            ? (int) floor((float) $quantity)
            : null;
        if ($quantityValue === null) {
            continue;
        }
        if ($quantityValue < 0) {
            $quantityValue = 0;
        }

        $sku = null;
        if ($stmt = $conn->prepare(
            'SELECT l.product_sku FROM square_catalog_map scm '
            . 'JOIN listings l ON l.id = scm.local_id '
            . 'WHERE scm.local_type = "listing" AND scm.square_object_id = ? LIMIT 1'
        )) {
            $stmt->bind_param('s', $squareId);
            if ($stmt->execute() && ($result = $stmt->get_result())) {
                if ($row = $result->fetch_assoc()) {
                    $candidate = (string) ($row['product_sku'] ?? '');
                    if ($candidate !== '') {
                        $sku = $candidate;
                    }
                }
                $result->free();
            }
            $stmt->close();
        }

        if ($sku === null) {
            continue;
        }

        try {
            $service->reconcileExternalStock(
                $sku,
                $quantityValue,
                'square_webhook',
                null,
                [
                    'square_object_id' => $squareId,
                    'state' => $count['state'] ?? null,
                    'source' => 'webhook',
                ]
            );
            $applied++;
        } catch (Throwable $e) {
            square_log('square.webhook_inventory_error', [
                'sku' => $sku,
                'square_object_id' => $squareId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    square_log('square.webhook_inventory', [
        'applied' => $applied,
    ]);

    return $applied > 0;
}
