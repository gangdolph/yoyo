<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$listing = null;
$step = 'select';

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

function fetch_inventory($conn, $uid) {
    $items = [];
    if ($stmt = $conn->prepare('SELECT id, item_name FROM user_inventory WHERE user_id = ? ORDER BY item_name')) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return $items;
}

$your_items = fetch_inventory($conn, $user_id);
$their_items = fetch_inventory($conn, $listing['owner_id']);
$your_map = array_column($your_items, 'item_name', 'id');
$their_map = array_column($their_items, 'item_name', 'id');

$selected_offer = [];
$selected_request = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? 'select';
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
        $step = 'select';
    } else {
        $message = trim($_POST['message'] ?? '');
        if ($step === 'review') {
            $selected_offer = array_map('intval', $_POST['offer_items'] ?? []);
            $selected_request = array_map('intval', $_POST['request_items'] ?? []);
            if (empty($selected_offer) && empty($selected_request)) {
                $error = 'Select at least one item.';
                $step = 'select';
            }
        } elseif ($step === 'confirm') {
            $selected_offer = array_map('intval', $_POST['offer_items'] ?? []);
            $selected_request = array_map('intval', $_POST['request_items'] ?? []);
            if (empty($selected_offer) && empty($selected_request)) {
                $error = 'Select at least one item.';
                $step = 'select';
            }
            if (!$error) {
                $offer_json = json_encode(array_values(array_intersect_key($your_map, array_flip($selected_offer))));
                $request_json = json_encode(array_values(array_intersect_key($their_map, array_flip($selected_request))));
                if ($stmt = $conn->prepare('INSERT INTO trade_offers (listing_id, offerer_id, message, offer_item, offer_items, request_items) VALUES (?,?,?,?,?,?)')) {
                    $empty = '';
                    $stmt->bind_param('iissss', $listing_id, $user_id, $message, $empty, $offer_json, $request_json);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: trade-listings.php');
                    exit;
                } else {
                    $error = 'Database error.';
                    $step = 'select';
                }
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
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($step === 'select'): ?>
    <form method="post">
      <p>Select items you will give:</p>
      <?php foreach ($your_items as $item): ?>
        <label><input type="checkbox" name="offer_items[]" value="<?= $item['id'] ?>"> <?= htmlspecialchars($item['item_name']) ?></label><br>
      <?php endforeach; ?>
      <p>Select items you want from <?= htmlspecialchars($listing['username']) ?>:</p>
      <?php foreach ($their_items as $item): ?>
        <label><input type="checkbox" name="request_items[]" value="<?= $item['id'] ?>"> <?= htmlspecialchars($item['item_name']) ?></label><br>
      <?php endforeach; ?>
      <label>Message:<br><textarea name="message"></textarea></label><br>
      <input type="hidden" name="step" value="review">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit">Review Offer</button>
    </form>
  <?php elseif ($step === 'review'): ?>
    <h3>Review Your Offer</h3>
    <p>You will give: <?= htmlspecialchars(implode(', ', array_values(array_intersect_key($your_map, array_flip($selected_offer))))) ?: 'Nothing' ?></p>
    <p>You will receive: <?= htmlspecialchars(implode(', ', array_values(array_intersect_key($their_map, array_flip($selected_request))))) ?: 'Nothing' ?></p>
    <form method="post" style="display:inline">
      <?php foreach ($selected_offer as $id): ?><input type="hidden" name="offer_items[]" value="<?= $id ?>"><?php endforeach; ?>
      <?php foreach ($selected_request as $id): ?><input type="hidden" name="request_items[]" value="<?= $id ?>"><?php endforeach; ?>
      <input type="hidden" name="message" value="<?= htmlspecialchars($message, ENT_QUOTES) ?>">
      <input type="hidden" name="step" value="confirm">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit">Confirm Offer</button>
    </form>
    <form method="get" style="display:inline">
      <input type="hidden" name="id" value="<?= $listing_id ?>">
      <button type="submit">Back</button>
    </form>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
