<?php
require '../includes/auth.php';

$rows = [];
if ($stmt = $conn->prepare('SELECT of.id, u.username, of.sku, p.title, p.quantity, p.reorder_threshold, of.status FROM order_fulfillments of JOIN users u ON of.user_id = u.id JOIN products p ON of.sku = p.sku ORDER BY of.created_at DESC')) {
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Order Fulfillments</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<h1>Order Fulfillments</h1>
<p><a href="products.php">Manage Products</a></p>
<table border="1" cellpadding="5">
    <tr><th>ID</th><th>User</th><th>SKU</th><th>Product</th><th>Remaining Stock</th><th>Threshold</th><th>Shipment Status</th></tr>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= htmlspecialchars($row['sku']) ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= (int)$row['quantity'] ?></td>
        <td><?= (int)$row['reorder_threshold'] ?></td>
        <td><?= htmlspecialchars($row['status']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
