<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$listing = null;
$inventory = [];

if ($listing_id) {
    if ($stmt = $conn->prepare('SELECT tl.id, tl.have_sku, tl.want_sku, tl.description, tl.image, tl.status, tl.owner_id, u.username, p_have.title AS have_title, p_want.title AS want_title FROM trade_listings tl JOIN users u ON tl.owner_id = u.id JOIN products p_have ON tl.have_sku = p_have.sku JOIN products p_want ON tl.want_sku = p_want.sku WHERE tl.id = ?')) {
        $stmt->bind_param('i', $listing_id);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($stmt = $conn->prepare('SELECT sku, title FROM products WHERE owner_id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (!$listing || $listing['status'] !== 'open' || $listing['owner_id'] == $user_id) {
    die('Listing not available for offers.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $offered_sku = $_POST['offered_sku'] ?? '';
        $use_escrow = isset($_POST['use_escrow']) ? 1 : 0;
        $message = trim($_POST['message'] ?? '');
        $valid_item = false;
        foreach ($inventory as $item) {
            if ($item['sku'] === $offered_sku) {
                $valid_item = true;
                break;
            }
        }
        if (!$valid_item) {
            $error = 'Valid inventory item required.';
        }
        if (!$error) {
            if ($stmt = $conn->prepare('INSERT INTO trade_offers (listing_id, offerer_id, offered_sku, message, use_escrow) VALUES (?,?,?,?,?)')) {
                $stmt->bind_param('iissi', $listing_id, $user_id, $offered_sku, $message, $use_escrow);
                $stmt->execute();
                $stmt->close();
                header('Location: trade.php?listing=' . $listing_id);
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
  <p>They have: <strong><?= htmlspecialchars($listing['have_title']) ?></strong></p>
  <p>They want: <strong><?= htmlspecialchars($listing['want_title']) ?></strong></p>
  <p><?= nl2br(htmlspecialchars($listing['description'])) ?></p>
  <?php if (!empty($listing['image'])): ?><p><img src="uploads/<?= htmlspecialchars($listing['image']) ?>" alt="Listing image" style="max-width:200px"></p><?php endif; ?>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Your Item Offer:<br>
      <select name="offered_sku" required>
        <option value="">-- Select Item --</option>
        <?php foreach ($inventory as $item): ?>
          <option value="<?= htmlspecialchars($item['sku']) ?>"><?= htmlspecialchars($item['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <label><input type="checkbox" name="use_escrow" value="1"> Use SkuzE escrow</label><br>
    <label>Message:<br><textarea name="message"></textarea></label><br>
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <button type="submit">Submit Offer</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
