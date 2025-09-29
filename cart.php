<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'includes/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cartMessages = $_SESSION['cart_messages'] ?? [];
if (!empty($cartMessages)) {
    $cartMessages = array_unique($cartMessages);
}
unset($_SESSION['cart_messages']);

// Handle add-to-cart via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $available = 0;
    $message = '';
    $remaining = 0;
    if ($id > 0) {
        $stmt = $conn->prepare('SELECT quantity, reserved_qty FROM listings WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
        } else {
            $row = null;
        }

        $available = 0;
        if ($row) {
            $rawQuantity = isset($row['quantity']) ? (int) $row['quantity'] : 0;
            $rawReserved = isset($row['reserved_qty']) ? (int) $row['reserved_qty'] : 0;
            $available = max(0, $rawQuantity - $rawReserved);
        }

        $currentQty = $_SESSION['cart'][$id] ?? 0;
        $message = '';

        if ($available <= 0) {
            unset($_SESSION['cart'][$id]);
            $response = [
                'success' => false,
                'count' => array_sum($_SESSION['cart']),
                'available' => 0,
                'message' => 'This listing is out of stock.',
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $newQty = $currentQty + 1;
        if ($newQty > $available) {
            $newQty = $available;
            $message = 'Only ' . $available . ' available. Quantity adjusted.';
        }

        $_SESSION['cart'][$id] = $newQty;
        $remaining = max(0, $available - $newQty);
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $available > 0,
        'count' => array_sum($_SESSION['cart']),
        'available' => isset($remaining) ? $remaining : 0,
        'message' => $message !== '' ? $message : null,
    ]);
    exit;
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty'])) {
    $requested = [];
    foreach ($_POST['qty'] as $id => $qty) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $requested[$id] = (int) $qty;
    }

    $messages = [];
    if ($requested) {
        $ids = array_keys($requested);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT id, title, quantity, reserved_qty FROM listings WHERE id IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[(int) $row['id']] = $row;
            }
            $stmt->close();
        } else {
            $rows = [];
        }

        foreach ($requested as $id => $qty) {
            if (!isset($rows[$id])) {
                unset($_SESSION['cart'][$id]);
                $messages[] = 'Listing #' . $id . ' is no longer available and was removed from your cart.';
                continue;
            }

            $row = $rows[$id];
            $rawQuantity = isset($row['quantity']) ? (int) $row['quantity'] : 0;
            $rawReserved = isset($row['reserved_qty']) ? (int) $row['reserved_qty'] : 0;
            $available = max(0, $rawQuantity - $rawReserved);
            $title = isset($row['title']) ? (string) $row['title'] : ('Listing #' . $id);

            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
                $messages[] = $title . ' removed from your cart.';
                continue;
            }

            if ($available <= 0) {
                unset($_SESSION['cart'][$id]);
                $messages[] = $title . ' is out of stock and was removed from your cart.';
                continue;
            }

            if ($qty > $available) {
                $_SESSION['cart'][$id] = $available;
                $messages[] = 'Limited stock for ' . $title . '. Quantity adjusted to ' . $available . '.';
            } else {
                $_SESSION['cart'][$id] = $qty;
            }
        }
    }

    if (!empty($messages)) {
        $_SESSION['cart_messages'] = $messages;
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
    $query = "SELECT l.id, l.title, l.image, l.price, l.sale_price, l.product_sku, l.quantity AS listing_quantity, l.reserved_qty
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
        $listingQty = isset($row['listing_quantity']) ? (int) $row['listing_quantity'] : 0;
        $listingReserved = isset($row['reserved_qty']) ? (int) $row['reserved_qty'] : 0;
        $available = max(0, $listingQty - $listingReserved);

        if ($available <= 0) {
            unset($_SESSION['cart'][$id]);
            $cartMessages[] = ($row['title'] ?? ('Listing #' . $id)) . ' is out of stock and was removed from your cart.';
            continue;
        }

        if ($quantity > $available) {
            $quantity = $available;
            $_SESSION['cart'][$id] = $available;
            $cartMessages[] = 'Limited stock for ' . ($row['title'] ?? ('Listing #' . $id)) . '. Quantity adjusted to ' . $available . '.';
        }

        $effectivePrice = $row['sale_price'] !== null && $row['sale_price'] !== ''
            ? (float)$row['sale_price']
            : (float)$row['price'];

        $row['cart_quantity'] = $quantity;
        $row['available_quantity'] = $available;
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
            $cartMessages[] = 'Listing #' . $missingId . ' is no longer available and was removed from your cart.';
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

$targetListingId = 0;
$checkoutListingIds = [];
if (!empty($cart_items)) {
    $firstItem = $cart_items[0];
    if (isset($firstItem['id'])) {
        $targetListingId = (int) $firstItem['id'];
    }
    foreach ($cart_items as $item) {
        if (isset($item['id'])) {
            $checkoutListingIds[] = (int) $item['id'];
        }
    }
}
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
<?php if (!empty($cartMessages)): ?>
    <div class="cart-messages" role="status" aria-live="polite">
        <?php foreach ($cartMessages as $message): ?>
            <p><?= htmlspecialchars($message); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
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
            <td>
                <input type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['cart_quantity'] ?>" min="0" max="<?= isset($item['available_quantity']) ? (int)$item['available_quantity'] : 0 ?>">
                <small class="stock-availability">In stock: <?= isset($item['available_quantity']) ? (int)$item['available_quantity'] : 0 ?></small>
            </td>
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
    <?php if ($targetListingId > 0): ?>
        <input type="hidden" name="listing_id" value="<?= $targetListingId; ?>">
    <?php endif; ?>
    <?php if (!empty($checkoutListingIds)): ?>
        <?php foreach ($checkoutListingIds as $id): ?>
            <input type="hidden" name="listing_id[]" value="<?= $id; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <button type="submit">Proceed to Checkout</button>
</form>
<?php endif; ?>
</body>
</html>
