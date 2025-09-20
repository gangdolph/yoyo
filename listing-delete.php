<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_token($_POST['csrf_token'] ?? '') && is_admin()) {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        if ($stmt = $conn->prepare('DELETE FROM listings WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}
$redirect = $_POST['redirect'] ?? 'buy.php';
header('Location: ' . $redirect);
exit;
?>
