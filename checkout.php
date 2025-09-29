<?php
// Update: Added policy-aware fee transparency callout, config-driven breakdown data, and wallet payments.
require __DIR__ . '/_debug_bootstrap.php';
$client = require __DIR__ . '/includes/square.php';
$squareConfig = require __DIR__ . '/includes/square-config.php';
require 'includes/requirements.php';
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/url.php';
$transparencyConfig = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/WalletService.php';

// $paymentsApi = $client->getPaymentsApi();
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
$additionalListingIds = array_slice($requestedListingIds, 1);
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch the listing details
$stmt = $conn->prepare('SELECT id, title, description, price, sale_price, pickup_only, quantity, reserved_qty FROM listings WHERE id = ? LIMIT 1');
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

if (!isset($_SESSION['checkout_quantities']) || !is_array($_SESSION['checkout_quantities'])) {
    $_SESSION['checkout_quantities'] = [];
}
if (!isset($_SESSION['checkout_notices']) || !is_array($_SESSION['checkout_notices'])) {
    $_SESSION['checkout_notices'] = [];
}
if (!isset($_SESSION['reservation_tokens']) || !is_array($_SESSION['reservation_tokens'])) {
    $_SESSION['reservation_tokens'] = [];
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

$availableQuantity = max(0, (int)($listing['quantity'] ?? 0) - (int)($listing['reserved_qty'] ?? 0));
$selectedQuantity = isset($_SESSION['checkout_quantities'][$listing_id])
    ? (int) $_SESSION['checkout_quantities'][$listing_id]
    : 1;
$quantityNotice = isset($_SESSION['checkout_notices'][$listing_id])
    ? (string) $_SESSION['checkout_notices'][$listing_id]
    : '';

if ($availableQuantity <= 0) {
    $selectedQuantity = 0;
    $quantityNotice = 'This listing is out of stock.';
    unset($_SESSION['checkout_quantities'][$listing_id]);
} else {
    if ($selectedQuantity <= 0) {
        $selectedQuantity = 1;
    }
    if ($selectedQuantity > $availableQuantity) {
        $selectedQuantity = $availableQuantity;
        $quantityNotice = 'Only ' . $availableQuantity . ' available. Quantity adjusted.';
    }
    $_SESSION['checkout_quantities'][$listing_id] = $selectedQuantity;
}
$_SESSION['checkout_notices'][$listing_id] = $quantityNotice;
$now = time();
foreach ($_SESSION['reservation_tokens'] as $token => $state) {
    if (!is_array($state)) {
        unset($_SESSION['reservation_tokens'][$token]);
        continue;
    }
    if (($state['created_at'] ?? 0) < ($now - 3600)) {
        unset($_SESSION['reservation_tokens'][$token]);
        continue;
    }
    if (($state['listing_id'] ?? null) === $listing_id) {
        unset($_SESSION['reservation_tokens'][$token]);
    }
}
$reservationToken = null;
if ($selectedQuantity > 0) {
    $reservationToken = bin2hex(random_bytes(16));
    $_SESSION['reservation_tokens'][$reservationToken] = [
        'listing_id' => $listing_id,
        'quantity' => $selectedQuantity,
        'reserved' => false,
        'created_at' => $now,
    ];
}
$checkoutDisabled = $selectedQuantity <= 0 || $availableQuantity <= 0;

$pickupOnly = !empty($listing['pickup_only']);
if (!$pickupOnly && !isset($_SESSION['shipping'][$listing_id])) {
    $redirectIds = $requestedListingIds;
    if (empty($redirectIds)) {
        $redirectIds = [$listing_id];
    }
    $redirectQuery = http_build_query(['listing_id' => array_values($redirectIds)]);
    header('Location: shipping.php?' . $redirectQuery);
    exit;
}
$shipping = $pickupOnly ? ['address' => '', 'method' => 'pickup', 'notes' => ''] : $_SESSION['shipping'][$listing_id];

$unitPrice = $listing['sale_price'] !== null ? (float)$listing['sale_price'] : (float)$listing['price'];
$couponCode = trim($_GET['coupon'] ?? '');
$unitDiscount = 0.0;
if ($couponCode !== '') {
    $stmt = $conn->prepare('SELECT discount_type, discount_value FROM coupons WHERE listing_id = ? AND code = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $stmt->bind_param('is', $listing['id'], $couponCode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($c = $res->fetch_assoc()) {
        if ($c['discount_type'] === 'percentage') {
            $unitDiscount = $unitPrice * ((float)$c['discount_value'] / 100);
        } else {
            $unitDiscount = (float)$c['discount_value'];
        }
        if ($unitDiscount > $unitPrice) {
            $unitDiscount = $unitPrice;
        }
    }
    $stmt->close();
}
$unitNetPrice = max(0.0, $unitPrice - $unitDiscount);
$subtotal = round($unitNetPrice * $selectedQuantity, 2);
$discountTotal = round($unitDiscount * $selectedQuantity, 2);

$applicationId = $squareConfig['application_id'];
$locationId = $squareConfig['location_id'];
$environment = $squareConfig['environment'];
$squareJs = $environment === 'production'
    ? 'https://web.squarecdn.com/v1/square.js'
    : 'https://sandbox.web.squarecdn.com/v1/square.js';

$showFeeBreakdown = !empty($transparencyConfig['FEE_SHOW_BREAKDOWN']);
$feesPercent = (float)($transparencyConfig['FEES_PERCENT'] ?? 2.0);
$feesFixed = (int)($transparencyConfig['FEES_FIXED_CENTS'] ?? 0) / 100;
$shippingCost = isset($shipping['cost']) ? (float)$shipping['cost'] : 0.0;
$taxAmount = isset($shipping['tax']) ? (float)$shipping['tax'] : 0.0;
$processorFee = isset($shipping['processor_fee']) ? (float)$shipping['processor_fee'] : 0.0;
$marketplaceFee = round(($subtotal * ($feesPercent / 100)) + $feesFixed, 2);
$estimatedTotal = round($subtotal + $shippingCost + $taxAmount + $processorFee + $marketplaceFee, 2);
$walletEnabled = !empty($transparencyConfig['SHOW_WALLET']);
$walletBalanceCents = 0;
$walletPendingCents = 0;
$walletSufficient = false;
if ($walletEnabled && isset($_SESSION['user_id'])) {
    try {
        $walletService = new WalletService($conn);
        $walletData = $walletService->getBalance((int) $_SESSION['user_id']);
        $walletBalanceCents = (int) $walletData['available_cents'];
        $walletPendingCents = (int) $walletData['pending_cents'];
    } catch (Throwable $walletError) {
        error_log('[checkout] wallet lookup failed: ' . $walletError->getMessage());
        $walletEnabled = false;
    }
}
$amountDueCents = (int) round($subtotal * 100);
if ($amountDueCents < 0) {
    $amountDueCents = 0;
}
if ($walletEnabled) {
    $walletSufficient = $walletBalanceCents >= $amountDueCents;
}
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
    <p class="stock-availability <?= $availableQuantity > 0 ? '' : 'out-of-stock'; ?>" aria-live="polite">
      <?= $availableQuantity > 0
          ? 'In stock: ' . htmlspecialchars((string) $availableQuantity)
          : 'Out of stock'; ?>
    </p>
    <?php if ($quantityNotice !== ''): ?>
      <p class="stock-availability notice"><?= htmlspecialchars($quantityNotice); ?></p>
    <?php endif; ?>
    <p class="quantity">Quantity: <?= htmlspecialchars((string) $selectedQuantity); ?></p>
    <?php if ($discountTotal > 0): ?>
      <p class="discount">Coupon: -$<?= htmlspecialchars(number_format($discountTotal, 2)); ?></p>
    <?php endif; ?>
    <p class="subtotal">Subtotal: $<?= htmlspecialchars(number_format($subtotal, 2)); ?></p>
  </div>
  <form method="get" class="coupon-form">
    <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
    <?php if (!empty($additionalListingIds)): ?>
      <?php foreach ($additionalListingIds as $additionalId): ?>
        <input type="hidden" name="listing_id[]" value="<?= $additionalId; ?>">
      <?php endforeach; ?>
    <?php endif; ?>
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
  <?php if ($showFeeBreakdown): ?>
    <aside class="policy-callout fee-transparency" aria-live="polite">
      <h3>Fee Transparency</h3>
      <ul class="policy-callout__list">
        <li><span>Subtotal</span><span>$<?= htmlspecialchars(number_format($subtotal, 2)); ?></span></li>
        <?php if ($shippingCost > 0): ?>
          <li><span>Shipping</span><span>$<?= htmlspecialchars(number_format($shippingCost, 2)); ?></span></li>
        <?php endif; ?>
        <?php if ($taxAmount > 0): ?>
          <li><span>Tax</span><span>$<?= htmlspecialchars(number_format($taxAmount, 2)); ?></span></li>
        <?php endif; ?>
        <?php if ($processorFee > 0): ?>
          <li><span>Payment processor</span><span>$<?= htmlspecialchars(number_format($processorFee, 2)); ?></span></li>
        <?php else: ?>
          <li><span>Payment processor</span><span>Shown at confirmation</span></li>
        <?php endif; ?>
        <li><span>Marketplace fee</span><span><?= htmlspecialchars(number_format($feesPercent, 2)); ?>% + $<?= htmlspecialchars(number_format($feesFixed, 2)); ?> = $<?= htmlspecialchars(number_format($marketplaceFee, 2)); ?></span></li>
        <li><span>Estimated total*</span><span>$<?= htmlspecialchars(number_format($estimatedTotal, 2)); ?></span></li>
      </ul>
      <p class="policy-callout__note">*Totals settle once processor fees finalize. <a href="/policies/fees.php">See full fee policy</a>.</p>
    </aside>
  <?php endif; ?>
  <form id="payment-form" method="post" action="checkout_process.php" class="checkout-payment">
    <?php if ($walletEnabled): ?>
      <fieldset class="wallet-options">
        <legend>Payment Method</legend>
        <p>Wallet available: $<?= htmlspecialchars(number_format($walletBalanceCents / 100, 2)); ?><?php if ($walletPendingCents > 0): ?> (Pending: $<?= htmlspecialchars(number_format($walletPendingCents / 100, 2)); ?>)<?php endif; ?></p>
        <label>
          <input type="radio" name="payment_method" value="wallet" <?= $walletSufficient ? 'checked' : 'disabled'; ?>>
          Pay with Wallet
          <?php if (!$walletSufficient): ?>
            <span class="wallet-note">Add funds to cover this order or use your card below.</span>
          <?php endif; ?>
        </label>
        <label>
          <input type="radio" name="payment_method" value="card" <?= $walletSufficient ? '' : 'checked'; ?>>
          Pay with Card (Square)
        </label>
      </fieldset>
    <?php else: ?>
      <input type="hidden" name="payment_method" value="card">
    <?php endif; ?>
    <div id="card-container" data-app-id="<?= htmlspecialchars($applicationId); ?>" data-location-id="<?= htmlspecialchars($locationId); ?>"></div>
    <input type="hidden" name="token" id="token">
    <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
    <input type="hidden" name="quantity" value="<?= max(0, $selectedQuantity); ?>">
    <?php if ($reservationToken !== null): ?>
      <input type="hidden" name="reservation_token" value="<?= htmlspecialchars($reservationToken); ?>">
    <?php endif; ?>
    <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($couponCode); ?>">
    <?php if ($walletEnabled): ?>
      <input type="hidden" name="wallet_allowed" value="1">
    <?php endif; ?>
    <button type="submit" class="checkout-submit" <?= $checkoutDisabled ? 'disabled' : ''; ?>>Pay Now</button>
    <?php if ($checkoutDisabled): ?>
      <p class="stock-availability out-of-stock">This item is currently unavailable. Please adjust your cart.</p>
    <?php endif; ?>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
