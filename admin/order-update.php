<?php
require_once __DIR__ . '/../includes/auth.php';
require '../includes/db.php';
require '../includes/orders.php';
require '../includes/csrf.php';
require '../includes/notifications.php';

if (!is_admin()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

function order_admin_set_flash(string $message, string $type = 'success'): void {
    $_SESSION['order_admin_flash'] = [
        'message' => $message,
        'type' => $type === 'error' ? 'error' : 'success',
    ];
}

if (!validate_token($_POST['csrf_token'] ?? '')) {
    order_admin_set_flash('Invalid request token. Please try again.', 'error');
    header('Location: orders.php');
    exit;
}

$orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$context = $_POST['context'] ?? 'detail';
$redirect = $context === 'list' ? 'orders.php' : 'order.php?id=' . $orderId;

if ($orderId <= 0) {
    order_admin_set_flash('Invalid order specified.', 'error');
    header('Location: orders.php');
    exit;
}

$order = fetch_order_detail_for_admin($conn, $orderId, (int) ($_SESSION['user_id'] ?? 0));
if (!$order) {
    order_admin_set_flash('Order not found.', 'error');
    header('Location: orders.php');
    exit;
}

$statusOptions = order_fulfillment_status_options();
$status = $_POST['status'] ?? '';
if (!is_string($status) || !array_key_exists($status, $statusOptions)) {
    order_admin_set_flash('Unsupported fulfillment status selected.', 'error');
    header('Location: ' . $redirect);
    exit;
}

$trackingInput = trim((string) ($_POST['tracking_number'] ?? ''));
$tracking = $trackingInput === '' ? null : $trackingInput;
if ($tracking !== null && strlen($tracking) > 100) {
    order_admin_set_flash('Tracking numbers must be 100 characters or fewer.', 'error');
    header('Location: ' . $redirect);
    exit;
}

$inventoryInput = trim((string) ($_POST['inventory_delta'] ?? ''));
$manualDelta = 0;
if ($inventoryInput !== '') {
    if (!preg_match('/^-?\d+$/', $inventoryInput)) {
        order_admin_set_flash('Inventory adjustments must be whole numbers.', 'error');
        header('Location: ' . $redirect);
        exit;
    }
    $manualDelta = (int) $inventoryInput;
}

$autoRestock = !empty($_POST['auto_restock']);
$inventoryDelta = order_admin_compute_inventory_delta($manualDelta, $autoRestock, $status);
$restockApplied = $autoRestock && $status === 'cancelled';

$messages = [];
$inventoryApplied = false;

$conn->begin_transaction();
try {
    if ($tracking !== null) {
        $stmt = $conn->prepare('UPDATE order_fulfillments SET status = ?, tracking_number = ? WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare fulfillment update: ' . $conn->error);
        }
        $stmt->bind_param('ssi', $status, $tracking, $orderId);
    } else {
        $stmt = $conn->prepare('UPDATE order_fulfillments SET status = ?, tracking_number = NULL WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare fulfillment update: ' . $conn->error);
        }
        $stmt->bind_param('si', $status, $orderId);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update fulfillment record: ' . $stmt->error);
    }
    $stmt->close();

    if ($inventoryDelta !== 0) {
        $sku = $order['product']['sku'] ?? null;
        if ($sku) {
            $stmt = $conn->prepare('UPDATE products SET stock = GREATEST(stock + ?, 0), quantity = CASE WHEN quantity IS NULL THEN NULL ELSE GREATEST(quantity + ?, 0) END WHERE sku = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to prepare inventory adjustment: ' . $conn->error);
            }
            $stmt->bind_param('iis', $inventoryDelta, $inventoryDelta, $sku);
            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to adjust inventory: ' . $stmt->error);
            }
            $inventoryApplied = true;
            $stmt->close();
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log($e->getMessage());
    order_admin_set_flash('Failed to update the order. Please try again.', 'error');
    header('Location: ' . $redirect);
    exit;
}

$statusLabel = $statusOptions[$status];
$messages[] = 'Fulfillment status updated to ' . $statusLabel . '.';

if ($inventoryDelta !== 0) {
    if ($inventoryApplied) {
        if ($inventoryDelta > 0) {
            $messages[] = 'Inventory increased by ' . $inventoryDelta . ' unit' . ($inventoryDelta === 1 ? '' : 's') . '.';
        } else {
            $messages[] = 'Inventory reduced by ' . abs($inventoryDelta) . ' unit' . ($inventoryDelta === -1 ? '' : 's') . '.';
        }
    } else {
        $messages[] = 'Inventory adjustment skipped because the listing is not linked to a product SKU.';
    }
} elseif ($restockApplied) {
    // Auto restock was requested but cancelled out by a negative manual delta.
    $messages[] = 'Auto restock request ignored because a balancing manual adjustment was provided.';
}

$notificationMessage = notification_message('order_status', [
    'order_id' => $orderId,
    'status' => $statusLabel,
]);

$recipientIds = [];
if (!empty($order['buyer']['id'])) {
    $recipientIds[] = (int) $order['buyer']['id'];
}
if (!empty($order['listing']['owner_id'])) {
    $recipientIds[] = (int) $order['listing']['owner_id'];
}
$recipientIds = array_unique(array_filter($recipientIds));

foreach ($recipientIds as $recipientId) {
    create_notification($conn, $recipientId, 'order_status', $notificationMessage);
}

order_admin_set_flash(implode(' ', $messages));
header('Location: ' . $redirect);
exit;
