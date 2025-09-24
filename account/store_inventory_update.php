<?php
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

if (!authz_has_role('seller')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Seller access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!validate_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request token.']);
    exit;
}

$sku = trim((string) ($_POST['sku'] ?? ''));
$deltaRaw = trim((string) ($_POST['stock_delta'] ?? $_POST['quantity_delta'] ?? '0'));
$thresholdRaw = trim((string) ($_POST['reorder_threshold'] ?? ''));

if ($sku === '') {
    echo json_encode(['success' => false, 'message' => 'A product SKU is required.']);
    exit;
}

if ($deltaRaw === '') {
    $deltaRaw = '0';
}

if (!preg_match('/^-?\d+$/', $deltaRaw)) {
    echo json_encode(['success' => false, 'message' => 'Inventory adjustments must be whole numbers.']);
    exit;
}

$delta = (int) $deltaRaw;
$threshold = null;
if ($thresholdRaw !== '') {
    if (!preg_match('/^\d+$/', $thresholdRaw)) {
        echo json_encode(['success' => false, 'message' => 'Reorder thresholds must be zero or greater.']);
        exit;
    }
    $threshold = (int) $thresholdRaw;
}

try {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $result = store_apply_inventory_delta(
        $conn,
        $userId,
        $sku,
        $delta,
        $threshold,
        store_session_is_admin(),
        store_user_is_official($conn, $userId)
    );
    echo json_encode([
        'success' => true,
        'message' => 'Inventory updated successfully.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    error_log('[store_inventory_update] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
