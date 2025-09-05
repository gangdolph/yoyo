<?php
session_start();
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = !empty($_SESSION['is_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_token($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        $owner_id = null;
        if ($stmt = $conn->prepare('SELECT owner_id FROM trade_listings WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($owner_id);
            $stmt->fetch();
            $stmt->close();
        }
        if ($is_admin || ($owner_id !== null && $owner_id == $user_id)) {
            if ($stmt = $conn->prepare('DELETE FROM trade_listings WHERE id = ?')) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
$redirect = $_POST['redirect'] ?? 'trade-listings.php';
header('Location: ' . $redirect);
exit;
?>
