<?php
require_once __DIR__ . '/../includes/auth.php';
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

$orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$tracking = isset($_POST['tracking_number']) ? (string) $_POST['tracking_number'] : null;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'A valid order is required.']);
    exit;
}

try {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $result = store_update_order_tracking(
        $conn,
        $userId,
        $orderId,
        $tracking,
        store_session_is_admin(),
        store_user_is_official($conn, $userId)
    );

    $message = $result['tracking_number'] ? 'Tracking number saved.' : 'Tracking number cleared.';

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $result,
    ]);
} catch (Throwable $e) {
    error_log('[order_add_tracking] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
