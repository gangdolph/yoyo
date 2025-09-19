<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'includes/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add-to-cart via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]++;
        } else {
            $_SESSION['cart'][$id] = 1;
        }
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => array_sum($_SESSION['cart'])
    ]);
    exit;
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty'])) {
    foreach ($_POST['qty'] as $id => $qty) {
        $id = (int)$id;
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
    }
    header('Location: cart.php');
    exit;
}

$cart_items = [];
$total = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $query = "SELECT l.id, l.title, l.image, l.price, l.sale_price, l.product_sku, p.quantity AS stock_quantity
        FROM listings l
        LEFT JOIN products p ON l.product_sku = p.sku
        WHERE l.id IN ($placeholders)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    $rowsById = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        if (!isset($_SESSION['cart'][$id])) {
            continue;
        }

        $quantity = (int)$_SESSION['cart'][$id];
        $effectivePrice = $row['sale_price'] !== null && $row['sale_price'] !== ''
            ? (float)$row['sale_price']
            : (float)$row['price'];

        $row['quantity'] = $quantity;
        $row['effective_price'] = $effectivePrice;
        $row['subtotal'] = $effectivePrice * $quantity;
        $total += $row['subtotal'];
        $rowsById[$id] = $row;
    }
    $stmt->close();

    $missingIds = array_diff($ids, array_keys($rowsById));
    if (!empty($missingIds)) {
        foreach ($missingIds as $missingId) {
            unset($_SESSION['cart'][$missingId]);
        }
    }

    foreach ($ids as $id) {
        if (isset($rowsById[$id])) {
            $cart_items[] = $rowsById[$id];
        }
    }
}

$tax = 0.0; // placeholder
$shipping = 0.0; // placeholder
$grand_total = $total + $tax + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3601799131755099"
        crossorigin="anonymous"></script>
    <title>Your Cart</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>Your Cart</h1>
<p><a href="buy.php">Continue Shopping</a></p>
<?php if (empty($cart_items)): ?>
    <p>Your cart is empty.</p>
<?php else: ?>
<form method="post">
    <table class="cart-table">
        <tr>
            <th>Listing</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Subtotal</th>
        </tr>
        <?php foreach ($cart_items as $item): ?>
        <tr>
            <td class="cart-listing">
                <?php if (!empty($item['image'])): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="cart-thumb">
                <?php endif; ?>
                <span class="cart-title"><?= htmlspecialchars($item['title']) ?></span>
            </td>
            <td class="cart-price">
                <?php if ($item['sale_price'] !== null && $item['sale_price'] !== ''): ?>
                    <span class="original-price">$<?= number_format((float)$item['price'], 2) ?></span>
                    <span class="sale-price">$<?= number_format((float)$item['sale_price'], 2) ?></span>
                <?php else: ?>
                    $<?= number_format((float)$item['price'], 2) ?>
                <?php endif; ?>
            </td>
            <td><input type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="0"></td>
            <td>$<?= number_format($item['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><strong>Subtotal: $<?= number_format($total, 2) ?></strong></p>
    <p>Estimated Tax: $<?= number_format($tax, 2) ?></p>
    <p>Estimated Shipping: $<?= number_format($shipping, 2) ?></p>
    <p><strong>Total: $<?= number_format($grand_total, 2) ?></strong></p>
    <button type="submit">Update Cart</button>
</form>
<form action="checkout.php" method="get">
    <button type="submit">Proceed to Checkout</button>
</form>
<?php endif; ?>
</body>
</html>
