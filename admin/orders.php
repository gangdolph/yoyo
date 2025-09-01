<?php
// Simple order listing for administrators.
require '../includes/auth.php';

$orders = $conn->query('SELECT * FROM orders ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<h1>Orders</h1>
<p><a href="products.php">Manage Products</a></p>
<table border="1" cellpadding="5">
    <tr><th>ID</th><th>Stripe Session</th><th>Total</th><th>Created</th><th>Items</th></tr>
    <?php while ($row = $orders->fetch_assoc()): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['session_id']) ?></td>
        <td>$<?= number_format($row['total'], 2) ?></td>
        <td><?= $row['created_at'] ?></td>
        <td><pre><?= htmlspecialchars($row['items']) ?></pre></td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
