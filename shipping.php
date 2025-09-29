<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$listingParam = $_GET['listing_id'] ?? null;
$requestedListingIds = [];
if (is_array($listingParam)) {
    foreach ($listingParam as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $requestedListingIds[] = $id;
        }
    }
} elseif ($listingParam !== null) {
    $id = (int) $listingParam;
    if ($id > 0) {
        $requestedListingIds[] = $id;
    }
}
$requestedListingIds = array_values(array_unique($requestedListingIds));
if (empty($requestedListingIds)) {
    header('Location: buy.php');
    exit;
}
$listing_id = $requestedListingIds[0];
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch listing to confirm exists
if ($stmt = $conn->prepare('SELECT id, title, price, pickup_only, quantity, reserved_qty FROM listings WHERE id = ? LIMIT 1')) {
    $stmt->bind_param('i', $listing_id);
    $stmt->execute();
    $listing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$listing) {
    http_response_code(404);
    echo 'Listing not found';
    exit;
}

$availableQuantity = max(0, (int)($listing['quantity'] ?? 0) - (int)($listing['reserved_qty'] ?? 0));
if (!isset($_SESSION['checkout_quantities']) || !is_array($_SESSION['checkout_quantities'])) {
    $_SESSION['checkout_quantities'] = [];
}
if (!isset($_SESSION['checkout_notices']) || !is_array($_SESSION['checkout_notices'])) {
    $_SESSION['checkout_notices'] = [];
}
if (!isset($_SESSION['shipping']) || !is_array($_SESSION['shipping'])) {
    $_SESSION['shipping'] = [];
}

$cartQuantities = $_SESSION['cart'];
foreach ($requestedListingIds as $requestedId) {
    if (isset($cartQuantities[$requestedId])) {
        $_SESSION['checkout_quantities'][$requestedId] = (int) $cartQuantities[$requestedId];
    }
}

$requestedQuantity = isset($_GET['quantity']) ? (int) $_GET['quantity'] : 0;
if ($requestedQuantity <= 0) {
    $requestedQuantity = isset($_SESSION['checkout_quantities'][$listing_id])
        ? (int) $_SESSION['checkout_quantities'][$listing_id]
        : 1;
}
$requestedQuantity = max(1, $requestedQuantity);
$quantityNotice = '';

if ($availableQuantity <= 0) {
    $quantityNotice = 'This listing is out of stock.';
    unset($_SESSION['checkout_quantities'][$listing_id]);
} else {
    if ($requestedQuantity > $availableQuantity) {
        $requestedQuantity = $availableQuantity;
        $quantityNotice = 'Only ' . $availableQuantity . ' available. Quantity adjusted.';
    }
    $_SESSION['checkout_quantities'][$listing_id] = $requestedQuantity;
}
$_SESSION['checkout_notices'][$listing_id] = $quantityNotice;

// If pickup only, skip shipping form when stock remains
if (!empty($listing['pickup_only']) && $availableQuantity > 0) {
    $_SESSION['shipping'][$listing_id] = [
        'address' => '',
        'method' => 'pickup',
        'notes' => ''
    ];
    $redirectQuery = http_build_query(['listing_id' => array_values($requestedListingIds)]);
    header('Location: checkout.php?' . $redirectQuery);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($availableQuantity <= 0) {
        $error = 'This listing is out of stock.';
    } elseif (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $address = trim($_POST['address'] ?? '');
        $method = trim($_POST['method'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($address === '' || $method === '') {
            $error = 'Address and delivery method required.';
        } else {
            $_SESSION['shipping'][$listing_id] = [
                'address' => $address,
                'method' => $method,
                'notes' => $notes,
            ];
            $redirectQuery = http_build_query(['listing_id' => array_values($requestedListingIds)]);
            header('Location: checkout.php?' . $redirectQuery);
            exit;
        }
    }
}
$stored = $_SESSION['shipping'][$listing_id] ?? ['address' => '', 'method' => '', 'notes' => ''];
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Shipping Details</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Shipping Information</h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <p class="stock-availability <?= $availableQuantity > 0 ? '' : 'out-of-stock'; ?>" aria-live="polite">
    <?= $availableQuantity > 0
        ? 'In stock: ' . htmlspecialchars((string) $availableQuantity)
        : 'Out of stock'; ?>
  </p>
  <?php if ($quantityNotice !== ''): ?>
    <p class="stock-availability notice"><?= htmlspecialchars($quantityNotice); ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <label>Shipping Address:<br>
      <textarea name="address" rows="4" cols="40" required><?= htmlspecialchars($stored['address']); ?></textarea>
    </label>
    <label>Delivery Method:<br>
      <select name="method" required>
        <option value="" disabled <?= $stored['method']===''?'selected':'' ?>>Select...</option>
        <option value="standard" <?= $stored['method']==='standard'?'selected':'' ?>>Standard</option>
        <option value="express" <?= $stored['method']==='express'?'selected':'' ?>>Express</option>
      </select>
    </label>
    <label>Special Notes:<br>
      <textarea name="notes" rows="3" cols="40"><?= htmlspecialchars($stored['notes']); ?></textarea>
    </label>
    <button type="submit">Continue to Payment</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
