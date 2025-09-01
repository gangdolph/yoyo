<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$listing = null;

if ($listing_id) {
    if ($stmt = $conn->prepare('SELECT tl.id, tl.have_item, tl.want_item, tl.status, tl.owner_id, u.username FROM trade_listings tl JOIN users u ON tl.owner_id = u.id WHERE tl.id = ?')) {
        $stmt->bind_param('i', $listing_id);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$listing || $listing['status'] !== 'open' || $listing['owner_id'] == $user_id) {
    die('Listing not available for offers.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $offer_item = trim($_POST['offer_item'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($offer_item === '') {
            $error = 'Offer item is required.';
        }
        if (!$error) {
            if ($stmt = $conn->prepare('INSERT INTO trade_offers (listing_id, offerer_id, message, offer_item) VALUES (?,?,?,?)')) {
                $stmt->bind_param('iiss', $listing_id, $user_id, $message, $offer_item);
                $stmt->execute();
                $stmt->close();
                header('Location: trade-listings.php');
                exit;
            } else {
                $error = 'Database error.';
            }
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Make Offer</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Offer Trade to <?= htmlspecialchars($listing['username']) ?></h2>
  <p>They have: <strong><?= htmlspecialchars($listing['have_item']) ?></strong></p>
  <p>They want: <strong><?= htmlspecialchars($listing['want_item']) ?></strong></p>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Your Item Offer:<br><input type="text" name="offer_item" required></label><br>
    <label>Message:<br><textarea name="message"></textarea></label><br>
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <button type="submit">Submit Offer</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
