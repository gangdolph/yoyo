<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$listing_filter = isset($_GET['listing']) ? intval($_GET['listing']) : null;
$error = '';
$listing_info = null;

// Handle accept/decline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $offer_id = intval($_POST['offer_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($offer_id && in_array($action, ['accept','decline'], true)) {
            $status = $action === 'accept' ? 'accepted' : 'declined';
            if ($stmt = $conn->prepare('UPDATE trade_offers o JOIN trade_listings l ON o.listing_id = l.id SET o.status=? WHERE o.id=? AND l.owner_id=? AND o.status="pending"')) {
                $stmt->bind_param('sii', $status, $offer_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        $redirect = 'trade.php';
        if ($listing_filter) {
            $redirect .= '?listing=' . $listing_filter;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

// Fetch listing details when filtering
if ($listing_filter) {
    if ($stmt = $conn->prepare('SELECT have_item, want_item, description, image FROM trade_listings WHERE id = ? AND owner_id = ?')) {
        $stmt->bind_param('ii', $listing_filter, $user_id);
        $stmt->execute();
        $listing_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fetch offers
$offers = [];
if ($listing_filter) {
    $sql = 'SELECT o.id, i.name AS offer_item, o.status, o.use_escrow, o.message, u.username FROM trade_offers o JOIN trade_listings l ON o.listing_id = l.id JOIN users u ON o.offerer_id = u.id JOIN inventory_items i ON o.offered_item_id = i.id WHERE o.listing_id = ? AND l.owner_id = ? ORDER BY o.created_at DESC';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ii', $listing_filter, $user_id);
        $stmt->execute();
        $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $sql = 'SELECT o.id, i.name AS offer_item, o.status, o.use_escrow, o.message, tl.have_item, tl.want_item, u.username, (tl.owner_id = ?) AS incoming FROM trade_offers o JOIN trade_listings tl ON o.listing_id = tl.id JOIN users u ON o.offerer_id = u.id JOIN inventory_items i ON o.offered_item_id = i.id WHERE o.offerer_id = ? OR tl.owner_id = ? ORDER BY o.created_at DESC';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('iii', $user_id, $user_id, $user_id);
        $stmt->execute();
        $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Trade Offers</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Trade Offers</h2>
  <?php if ($user_id): ?>
    <p><a class="button" href="trade-listing.php">Create Trade Listing</a></p>
  <?php endif; ?>
  <?php if ($listing_filter && $listing_info): ?>
    <div class="listing-details">
      <p>Have: <strong><?= htmlspecialchars($listing_info['have_item']) ?></strong></p>
      <p>Want: <strong><?= htmlspecialchars($listing_info['want_item']) ?></strong></p>
      <p><?= nl2br(htmlspecialchars($listing_info['description'])) ?></p>
      <?php if (!empty($listing_info['image'])): ?><p><img src="uploads/<?= htmlspecialchars($listing_info['image']) ?>" alt="Listing image" style="max-width:200px"></p><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($offers): ?>
    <table>
      <tr>
        <?php if ($listing_filter): ?>
          <th>From</th><th>Item</th><th>Escrow</th><th>Message</th><th>Status</th><th>Actions</th>
        <?php else: ?>
          <th>Listing Have/Want</th><th>From</th><th>Item</th><th>Escrow</th><th>Status</th><th>Actions</th>
        <?php endif; ?>
      </tr>
      <?php foreach ($offers as $o): ?>
        <tr>
          <?php if ($listing_filter): ?>
            <td><?= htmlspecialchars($o['username']) ?></td>
            <td><?= htmlspecialchars($o['offer_item']) ?></td>
            <td><?= $o['use_escrow'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($o['message']) ?></td>
            <td><?= htmlspecialchars($o['status']) ?></td>
            <td>
              <?php if ($o['status'] === 'pending'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                  <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
                  <button name="action" value="accept" type="submit">Accept</button>
                  <button name="action" value="decline" type="submit">Decline</button>
                </form>
              <?php endif; ?>
            </td>
          <?php else: ?>
            <td><?= htmlspecialchars($o['have_item']) ?>/<?= htmlspecialchars($o['want_item']) ?></td>
            <td><?= htmlspecialchars($o['username']) ?></td>
            <td><?= htmlspecialchars($o['offer_item']) ?></td>
            <td><?= $o['use_escrow'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($o['status']) ?></td>
            <td>
              <?php if ($o['incoming'] && $o['status'] === 'pending'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                  <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
                  <button name="action" value="accept" type="submit">Accept</button>
                  <button name="action" value="decline" type="submit">Decline</button>
                </form>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p>No trade offers yet.</p>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
