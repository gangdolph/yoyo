<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json');

if (!validate_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request token.']);
    exit;
}

$orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$status = trim((string) ($_POST['status'] ?? ''));

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'A valid order is required.']);
    exit;
}

if ($status === '') {
    echo json_encode(['success' => false, 'message' => 'Please select a fulfillment status.']);
    exit;
}

try {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $result = store_update_order_status(
        $conn,
        $userId,
        $orderId,
        $status,
        store_session_is_admin(),
        store_user_is_official($conn, $userId)
    );

    echo json_encode([
        'success' => true,
        'message' => 'Fulfillment status updated.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    error_log('[order_update_status] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
