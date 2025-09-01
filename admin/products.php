<?php
// Simple product management interface for administrators.
require '../includes/auth.php';

// Handle new product submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $image = trim($_POST['image']);

    if ($name && $price) {
        $stmt = $conn->prepare('INSERT INTO products (name, description, price, image) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssds', $name, $description, $price, $image);
        $stmt->execute();
        $stmt->close();
    }
}

$products = $conn->query('SELECT * FROM products ORDER BY id DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Products</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<h1>Products</h1>
<p><a href="orders.php">View Orders</a></p>
<table border="1" cellpadding="5">
    <tr><th>ID</th><th>Name</th><th>Price</th><th>Image</th></tr>
    <?php while ($row = $products->fetch_assoc()): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td>$<?= number_format($row['price'], 2) ?></td>
        <td><?= htmlspecialchars($row['image']) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<h2>Add Product</h2>
<form method="post">
    <label>Name <input type="text" name="name" required></label><br>
    <label>Description <textarea name="description"></textarea></label><br>
    <label>Price <input type="number" step="0.01" name="price" required></label><br>
    <label>Image URL <input type="text" name="image"></label><br>
    <button type="submit">Add</button>
</form>
</body>
</html>
