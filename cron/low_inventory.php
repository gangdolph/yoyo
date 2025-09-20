<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/notifications.php';
require __DIR__ . '/../mail.php';

$result = $conn->query("SELECT sku, title, stock, reorder_threshold, owner_id FROM products WHERE reorder_threshold > 0 AND stock <= reorder_threshold");
$alerts = [];
while ($row = $result->fetch_assoc()) {
    $msg = "Product {$row['sku']} is low on stock ({$row['stock']} remaining).";
    create_notification($conn, $row['owner_id'], 'low_stock', $msg);
    $alerts[] = $msg;
}
if ($alerts) {
    $body = implode("\n", $alerts);
    $admins = $conn->query("SELECT email FROM users WHERE role = 'admin'");
    while ($admin = $admins->fetch_assoc()) {
        if (!empty($admin['email'])) {
            send_email($admin['email'], 'Low inventory summary', $body);
        }
    }
}
?>
