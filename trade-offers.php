<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$listing_id = isset($_GET['listing']) ? intval($_GET['listing']) : 0;
$error = '';
$listing = null;

if ($listing_id) {
    if ($stmt = $conn->prepare('SELECT id, have_item, want_item, status FROM trade_listings WHERE id = ? AND owner_id = ?')) {
        $stmt->bind_param('ii', $listing_id, $user_id);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
if (!$listing) {
    die('Listing not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $offer_id = intval($_POST['offer_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'accept') {
            if ($stmt = $conn->prepare('UPDATE trade_offers SET status="accepted" WHERE id=? AND listing_id=?')) {
                $stmt->bind_param('ii', $offer_id, $listing_id);
                $stmt->execute();
                $stmt->close();
            }
            if ($stmt = $conn->prepare('UPDATE trade_offers SET status="declined" WHERE listing_id=? AND id<>?')) {
                $stmt->bind_param('ii', $listing_id, $offer_id);
                $stmt->execute();
                $stmt->close();
            }
            if ($stmt = $conn->prepare('UPDATE trade_listings SET status="closed" WHERE id=?')) {
                $stmt->bind_param('i', $listing_id);
                $stmt->execute();
                $stmt->close();
            }
            header('Location: trade-fulfillment.php?offer=' . $offer_id);
            exit;
        } elseif ($action === 'decline') {
            if ($stmt = $conn->prepare('UPDATE trade_offers SET status="declined" WHERE id=? AND listing_id=?')) {
                $stmt->bind_param('ii', $offer_id, $listing_id);
                $stmt->execute();
                $stmt->close();
            }
            header('Location: trade-offers.php?listing=' . $listing_id);
            exit;
        }
        header('Location: trade-offers.php?listing=' . $listing_id);
        exit;
    }
}

$offers = [];
if ($stmt = $conn->prepare('SELECT o.id, o.offer_item, o.message, o.status, u.username FROM trade_offers o JOIN users u ON o.offerer_id = u.id WHERE o.listing_id = ? ORDER BY o.created_at DESC')) {
    $stmt->bind_param('i', $listing_id);
    $stmt->execute();
    $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Offers for Listing</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Offers for Your Listing</h2>
  <p>You have: <strong><?= htmlspecialchars($listing['have_item']) ?></strong></p>
  <p>You want: <strong><?= htmlspecialchars($listing['want_item']) ?></strong></p>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <table>
    <tr><th>Offerer</th><th>Offer Item</th><th>Message</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($offers as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['username']) ?></td>
        <td><?= htmlspecialchars($o['offer_item']) ?></td>
        <td><?= htmlspecialchars($o['message']) ?></td>
        <td><?= htmlspecialchars($o['status']) ?></td>
        <td>
          <?php if ($o['status'] === 'pending' && $listing['status'] === 'open'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
              <button type="submit" name="action" value="accept">Accept</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
              <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
              <button type="submit" name="action" value="decline">Decline</button>
            </form>
          <?php elseif ($o['status'] === 'accepted'): ?>
            <a href="trade-fulfillment.php?offer=<?= $o['id'] ?>">View Fulfillment</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
