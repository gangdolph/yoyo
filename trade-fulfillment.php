<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$offer_id = isset($_GET['offer']) ? intval($_GET['offer']) : 0;
$error = '';
$offer = null;

if ($offer_id) {
    if ($stmt = $conn->prepare('SELECT o.*, l.owner_id FROM trade_offers o JOIN trade_listings l ON o.listing_id = l.id WHERE o.id = ? AND o.status = "accepted"')) {
        $stmt->bind_param('i', $offer_id);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
if (!$offer || ($offer['owner_id'] != $user_id && $offer['offerer_id'] != $user_id)) {
    die('Offer not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $fulfillment_type = $_POST['fulfillment_type'] ?? '';
        $shipping_address = null;
        $meeting_location = null;
        $tracking_number = null;
        if ($fulfillment_type === 'ship_to_skuzE') {
            $shipping_address = trim($_POST['shipping_address'] ?? '');
            if (!$shipping_address) {
                $error = 'Shipping address required.';
            } else {
                $tracking_number = 'SKZ' . strtoupper(bin2hex(random_bytes(4)));
                $label = "Ship to: SkuzE Warehouse\n123 SkuzE St\nCity, ST\nTracking: $tracking_number\nFrom:\n$shipping_address\n";
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                file_put_contents("uploads/label_$offer_id.txt", $label);
            }
        } elseif ($fulfillment_type === 'meetup') {
            $meeting_location = trim($_POST['meeting_location'] ?? '');
            if (!$meeting_location) {
                $error = 'Meeting location required.';
            }
        } else {
            $error = 'Invalid fulfillment type.';
        }
        if (!$error) {
            if ($stmt = $conn->prepare('UPDATE trade_offers SET fulfillment_type=?, shipping_address=?, meeting_location=?, tracking_number=? WHERE id=?')) {
                $stmt->bind_param('ssssi', $fulfillment_type, $shipping_address, $meeting_location, $tracking_number, $offer_id);
                $stmt->execute();
                $stmt->close();
            }
            header('Location: trade-fulfillment.php?offer=' . $offer_id);
            exit;
        }
    }
    // reload offer after update or validation
    if ($stmt = $conn->prepare('SELECT o.*, l.owner_id FROM trade_offers o JOIN trade_listings l ON o.listing_id = l.id WHERE o.id = ?')) {
        $stmt->bind_param('i', $offer_id);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Fulfillment Details</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Fulfillment for Offer</h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($offer['fulfillment_type']): ?>
    <p>Fulfillment method: <strong><?= htmlspecialchars($offer['fulfillment_type']) ?></strong></p>
    <?php if ($offer['fulfillment_type'] === 'meetup'): ?>
      <p>Meeting location: <?= nl2br(htmlspecialchars($offer['meeting_location'])) ?></p>
    <?php else: ?>
      <p>Shipping address: <?= nl2br(htmlspecialchars($offer['shipping_address'])) ?></p>
      <p>Tracking number: <?= htmlspecialchars($offer['tracking_number']) ?></p>
      <?php $label_file = "uploads/label_{$offer_id}.txt"; if (file_exists($label_file)): ?>
        <p><a href="<?= htmlspecialchars($label_file) ?>" download>Download Shipping Label</a></p>
      <?php endif; ?>
      <p>Please attach the label and drop off your package at your nearest shipping center.</p>
    <?php endif; ?>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <label><input type="radio" name="fulfillment_type" value="meetup" required> Meetup</label>
      <label><input type="radio" name="fulfillment_type" value="ship_to_skuzE" required> Ship to SkuzE</label>
      <div id="meetup_fields" style="display:none;">
        <label>Meeting Location:<br>
          <input type="text" name="meeting_location" value="<?= htmlspecialchars($_POST['meeting_location'] ?? '') ?>">
        </label>
      </div>
      <div id="ship_fields" style="display:none;">
        <label>Shipping Address:<br>
          <textarea name="shipping_address" rows="4" cols="40"><?= htmlspecialchars($_POST['shipping_address'] ?? '') ?></textarea>
        </label>
      </div>
      <button type="submit">Save</button>
    </form>
    <script>
      const radios = document.querySelectorAll('input[name="fulfillment_type"]');
      const meetup = document.getElementById('meetup_fields');
      const ship = document.getElementById('ship_fields');
      function toggleFields() {
        if (document.querySelector('input[name="fulfillment_type"]:checked')?.value === 'meetup') {
          meetup.style.display = 'block';
          ship.style.display = 'none';
        } else {
          meetup.style.display = 'none';
          ship.style.display = 'block';
        }
      }
      radios.forEach(r => r.addEventListener('change', toggleFields));
      toggleFields();
    </script>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
