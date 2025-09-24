<?php
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/db.php';
require '../includes/csrf.php';

ensure_admin_or_official('/index.php');

$statusMessage = '';
$statusType = 'success';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Invalid request token. Please refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $sku = strtoupper(trim((string)($_POST['sku'] ?? '')));
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $ownerId = (int)($_POST['owner_id'] ?? 0);
            $stock = max(0, (int)($_POST['stock'] ?? 0));
            $threshold = max(0, (int)($_POST['reorder_threshold'] ?? 0));
            $price = (float)($_POST['price'] ?? 0);
            $isOfficial = !empty($_POST['is_skuze_official']) ? 1 : 0;
            $isProduct = !empty($_POST['is_skuze_product']) ? 1 : 0;

            if ($sku === '' || $title === '' || $ownerId <= 0) {
                throw new RuntimeException('SKU, title, and owner are required to create a product.');
            }

            $quantityMirror = $stock;
            $stmt = $conn->prepare('INSERT INTO products (sku, title, description, stock, quantity, reorder_threshold, owner_id, price, is_skuze_official, is_skuze_product, is_official) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare product insert: ' . $conn->error);
            }
            $stmt->bind_param(
                'sssiiiidiii',
                $sku,
                $title,
                $description,
                $stock,
                $quantityMirror,
                $threshold,
                $ownerId,
                $price,
                $isOfficial,
                $isProduct,
                $isOfficial
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to create product: ' . $stmt->error);
            }
            $stmt->close();

            $listingFlag = ($isOfficial || $isProduct) ? 1 : 0;
            $link = $conn->prepare('UPDATE listings SET is_official_listing = ? WHERE product_sku = ?');
            if ($link) {
                $link->bind_param('is', $listingFlag, $sku);
                $link->execute();
                $link->close();
            }

            $statusMessage = 'Product created successfully.';
            $statusType = 'success';
        } elseif ($action === 'update') {
            $sku = strtoupper(trim((string)($_POST['sku'] ?? '')));
            if ($sku === '') {
                throw new RuntimeException('A valid SKU is required for updates.');
            }
            $stock = max(0, (int)($_POST['stock'] ?? 0));
            $threshold = max(0, (int)($_POST['reorder_threshold'] ?? 0));
            $isOfficial = !empty($_POST['is_skuze_official']) ? 1 : 0;
            $isProduct = !empty($_POST['is_skuze_product']) ? 1 : 0;
            $quantityMirror = $stock;

            $stmt = $conn->prepare('UPDATE products SET stock = ?, reorder_threshold = ?, is_skuze_official = ?, is_skuze_product = ?, is_official = ?, quantity = CASE WHEN quantity IS NULL THEN NULL ELSE ? END WHERE sku = ?');
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare product update: ' . $conn->error);
            }
            $stmt->bind_param('iiiiiis', $stock, $threshold, $isOfficial, $isProduct, $isOfficial, $quantityMirror, $sku);
            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to update product: ' . $stmt->error);
            }
            $stmt->close();

            $listingFlag = ($isOfficial || $isProduct) ? 1 : 0;
            $link = $conn->prepare('UPDATE listings SET is_official_listing = ? WHERE product_sku = ?');
            if ($link) {
                $link->bind_param('is', $listingFlag, $sku);
                $link->execute();
                $link->close();
            }

            $statusMessage = 'Product settings updated.';
            $statusType = 'success';
        } else {
            throw new RuntimeException('Unsupported action requested.');
        }
    }
} catch (Throwable $e) {
    $statusMessage = $e->getMessage();
    $statusType = 'error';
}

$products = [];
if ($result = $conn->query('SELECT sku, title, stock, reorder_threshold, owner_id, price, is_skuze_official, is_skuze_product FROM products ORDER BY created_at DESC')) {
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
}
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
<?php if ($statusMessage !== ''): ?>
  <div class="alert <?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<table border="1" cellpadding="5">
    <tr><th>SKU</th><th>Title</th><th>Stock</th><th>Threshold</th><th>Owner</th><th>Price</th><th>Badges</th><th>Actions</th></tr>
    <?php foreach ($products as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['sku']); ?></td>
        <td><?= htmlspecialchars($row['title']); ?></td>
        <td><?= (int)$row['stock']; ?></td>
        <td><?= (int)$row['reorder_threshold']; ?></td>
        <td><?= (int)$row['owner_id']; ?></td>
        <td>$<?= number_format((float)$row['price'], 2); ?></td>
        <td>
            <?php if (!empty($row['is_skuze_official'])): ?>
              <span class="badge badge-official">SkuzE Official</span>
            <?php endif; ?>
            <?php if (!empty($row['is_skuze_product'])): ?>
              <span class="badge badge-product">SkuzE Product</span>
            <?php endif; ?>
        </td>
        <td>
            <form method="post" class="product-update-form">
                <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="sku" value="<?= htmlspecialchars($row['sku']); ?>">
                <label>Stock
                    <input type="number" name="stock" value="<?= (int)$row['stock']; ?>" min="0">
                </label>
                <label>Threshold
                    <input type="number" name="reorder_threshold" value="<?= (int)$row['reorder_threshold']; ?>" min="0">
                </label>
                <label>
                    <input type="checkbox" name="is_skuze_official" value="1" <?= !empty($row['is_skuze_official']) ? 'checked' : ''; ?>>
                    SkuzE Official
                </label>
                <label>
                    <input type="checkbox" name="is_skuze_product" value="1" <?= !empty($row['is_skuze_product']) ? 'checked' : ''; ?>>
                    SkuzE Product
                </label>
                <button type="submit" class="btn btn-small">Save</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>Add Product</h2>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="action" value="create">
    <label>SKU <input type="text" name="sku" required></label><br>
    <label>Title <input type="text" name="title" required></label><br>
    <label>Description <textarea name="description"></textarea></label><br>
    <label>Price <input type="number" step="0.01" name="price" required></label><br>
    <label>Stock <input type="number" name="stock" value="0" required min="0"></label><br>
    <label>Reorder Threshold <input type="number" name="reorder_threshold" value="0" min="0"></label><br>
    <label>Owner ID <input type="number" name="owner_id" required min="1"></label><br>
    <label><input type="checkbox" name="is_skuze_official" value="1"> SkuzE Official</label><br>
    <label><input type="checkbox" name="is_skuze_product" value="1"> SkuzE Product</label><br>
    <button type="submit">Add</button>
</form>
</body>
</html>
