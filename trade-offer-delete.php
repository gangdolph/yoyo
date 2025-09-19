<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/trade.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_token($_POST['csrf_token'] ?? '') && !empty($_SESSION['is_admin'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        trade_release_offer_inventory($conn, $id, $actorId, 'admin_delete');
        if ($stmt = $conn->prepare('DELETE FROM trade_offers WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}
$redirect = $_POST['redirect'] ?? 'trade.php';
header('Location: ' . $redirect);
exit;
?>
