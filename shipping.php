<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
if (!$listing_id) {
    header('Location: buy.php');
    exit;
}

// Fetch listing to confirm exists
if ($stmt = $conn->prepare('SELECT id, title, price, pickup_only FROM listings WHERE id = ? LIMIT 1')) {
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

// If pickup only, skip shipping form
if (!empty($listing['pickup_only'])) {
    $_SESSION['shipping'][$listing_id] = [
        'address' => '',
        'method' => 'pickup',
        'notes' => ''
    ];
    header('Location: checkout.php?listing_id=' . $listing_id);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
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
            header('Location: checkout.php?listing_id=' . $listing_id);
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
