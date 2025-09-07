<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/notifications.php';
require __DIR__ . '/../mail.php';

$result = $conn->query("SELECT sku, title, quantity, reorder_threshold, owner_id FROM products WHERE reorder_threshold > 0 AND quantity <= reorder_threshold");
$alerts = [];
while ($row = $result->fetch_assoc()) {
    $msg = "Product {$row['sku']} is low on stock ({$row['quantity']} remaining).";
    create_notification($conn, $row['owner_id'], 'low_stock', $msg);
    $alerts[] = $msg;
}
if ($alerts) {
    $body = implode("\n", $alerts);
    $admins = $conn->query("SELECT email FROM users WHERE is_admin = 1");
    while ($admin = $admins->fetch_assoc()) {
        if (!empty($admin['email'])) {
            send_email($admin['email'], 'Low inventory summary', $body);
        }
    }
}
?>
