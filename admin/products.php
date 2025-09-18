<?php
require_once __DIR__ . '/../includes/auth.php';
if (!$_SESSION['is_admin']) {
    header('Location: /index.php');
    exit;
}

// Handle new product submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $owner_id = (int)$_POST['owner_id'];
    $threshold = (int)$_POST['reorder_threshold'];
    if ($sku && $title && $owner_id) {
        $stmt = $conn->prepare('INSERT INTO products (sku, title, description, quantity, owner_id, price, reorder_threshold) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssiidi', $sku, $title, $description, $quantity, $owner_id, $price, $threshold);
        $stmt->execute();
        $stmt->close();
    }
}

$products = $conn->query('SELECT sku, title, quantity, reorder_threshold, owner_id, price FROM products ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3601799131755099"
        crossorigin="anonymous"></script>
    <title>Admin - Products</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<h1>Products</h1>
<p><a href="orders.php">View Orders</a></p>
<table border="1" cellpadding="5">
    <tr><th>SKU</th><th>Title</th><th>Qty</th><th>Threshold</th><th>Owner</th><th>Price</th></tr>
    <?php while ($row = $products->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['sku']) ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= (int)$row['quantity'] ?></td>
        <td><?= (int)$row['reorder_threshold'] ?></td>
        <td><?= (int)$row['owner_id'] ?></td>
        <td>$<?= number_format($row['price'], 2) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<h2>Add Product</h2>
<form method="post">
    <label>SKU <input type="text" name="sku" required></label><br>
    <label>Title <input type="text" name="title" required></label><br>
    <label>Description <textarea name="description"></textarea></label><br>
    <label>Price <input type="number" step="0.01" name="price" required></label><br>
    <label>Quantity <input type="number" name="quantity" value="0" required></label><br>
    <label>Reorder Threshold <input type="number" name="reorder_threshold" value="0"></label><br>
    <label>Owner ID <input type="number" name="owner_id" required></label><br>
    <button type="submit">Add</button>
</form>
</body>
</html>
