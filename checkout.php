<?php
require __DIR__ . '/_debug_bootstrap.php';
$client = require __DIR__ . '/includes/square.php';
$squareConfig = require __DIR__ . '/includes/square-config.php';
require 'includes/requirements.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/url.php';

// $paymentsApi = $client->getPaymentsApi();
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
if (!$listing_id) {
    header('Location: buy.php');
    exit;
}

// Fetch the listing details
$stmt = $conn->prepare('SELECT id, title, description, price, sale_price, pickup_only FROM listings WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();

if (!$listing) {
    http_response_code(404);
    echo 'Listing not found';
    exit;
}

$pickupOnly = !empty($listing['pickup_only']);
if (!$pickupOnly && !isset($_SESSION['shipping'][$listing_id])) {
    header('Location: shipping.php?listing_id=' . $listing_id);
    exit;
}
$shipping = $pickupOnly ? ['address' => '', 'method' => 'pickup', 'notes' => ''] : $_SESSION['shipping'][$listing_id];

$basePrice = $listing['sale_price'] !== null ? (float)$listing['sale_price'] : (float)$listing['price'];
$couponCode = trim($_GET['coupon'] ?? '');
$discount = 0.0;
if ($couponCode !== '') {
    $stmt = $conn->prepare('SELECT discount_type, discount_value FROM coupons WHERE listing_id = ? AND code = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $stmt->bind_param('is', $listing['id'], $couponCode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($c = $res->fetch_assoc()) {
        if ($c['discount_type'] === 'percentage') {
            $discount = $basePrice * ((float)$c['discount_value'] / 100);
        } else {
            $discount = (float)$c['discount_value'];
        }
        if ($discount > $basePrice) {
            $discount = $basePrice;
        }
    }
    $stmt->close();
}
$finalPrice = $basePrice - $discount;

$applicationId = $squareConfig['application_id'];
$locationId = $squareConfig['location_id'];
$environment = $squareConfig['environment'];
$squareJs = $environment === 'production'
    ? 'https://web.squarecdn.com/v1/square.js'
    : 'https://sandbox.web.squarecdn.com/v1/square.js';
?>
<?php require 'includes/layout.php'; ?>
  <title>Checkout</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="<?= $squareJs; ?>"></script>
  <script src="assets/checkout.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Checkout</h2>
  <div class="checkout-summary">
    <h3>Order Summary</h3>
    <p class="item"><?= htmlspecialchars($listing['title']); ?></p>
    <p class="description"><?= nl2br(htmlspecialchars($listing['description'])); ?></p>
    <?php if ($listing['sale_price'] !== null): ?>
      <p class="price"><span class="original">$<?= htmlspecialchars($listing['price']); ?></span> <span class="sale">$<?= htmlspecialchars($listing['sale_price']); ?></span></p>
    <?php else: ?>
      <p class="price">$<?= htmlspecialchars($listing['price']); ?></p>
    <?php endif; ?>
    <?php if ($discount > 0): ?>
      <p class="discount">Coupon: -$<?= htmlspecialchars(number_format($discount, 2)); ?></p>
    <?php endif; ?>
    <p class="subtotal">Subtotal: $<?= htmlspecialchars(number_format($finalPrice, 2)); ?></p>
  </div>
  <form method="get" class="coupon-form">
    <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
    <input type="text" name="coupon" value="<?= htmlspecialchars($couponCode); ?>" placeholder="Coupon code">
    <button type="submit">Apply</button>
  </form>
  <?php if ($pickupOnly): ?>
    <div class="shipping-summary">
      <h3>Pickup</h3>
      <p>This item is pickup only. No shipping address required.</p>
    </div>
  <?php else: ?>
    <div class="shipping-summary">
      <h3>Shipping</h3>
      <p><?= nl2br(htmlspecialchars($shipping['address'])); ?></p>
      <p>Method: <?= htmlspecialchars($shipping['method']); ?></p>
      <?php if (!empty($shipping['notes'])): ?>
        <p>Notes: <?= nl2br(htmlspecialchars($shipping['notes'])); ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <form id="payment-form" method="post" action="checkout_process.php">
    <div id="card-container" data-app-id="<?= htmlspecialchars($applicationId); ?>" data-location-id="<?= htmlspecialchars($locationId); ?>"></div>
    <input type="hidden" name="token" id="token">
    <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
    <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($couponCode); ?>">
    <button type="submit" class="checkout-submit">Pay Now</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
