<?php
$squareConfig = require __DIR__ . '/includes/square-config.php';
$client = require __DIR__ . '/includes/square.php';
// $paymentsApi = $client->getPaymentsApi();

require 'includes/requirements.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/url.php';

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
if (!$listing_id) {
    header('Location: buy.php');
    exit;
}

// Fetch the listing details
$stmt = $conn->prepare('SELECT id, title, description, price FROM listings WHERE id = ? LIMIT 1');
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
  <h3><?= htmlspecialchars($listing['title']); ?></h3>
  <p><?= nl2br(htmlspecialchars($listing['description'])); ?></p>
  <p class="price">$<?= htmlspecialchars($listing['price']); ?></p>
  <form id="payment-form" method="post" action="checkout_process.php">
    <div id="card-container" data-app-id="<?= htmlspecialchars($applicationId); ?>" data-location-id="<?= htmlspecialchars($locationId); ?>"></div>
    <input type="hidden" name="token" id="token">
    <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
    <button type="submit">Pay Now</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
