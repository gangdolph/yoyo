<?php
require_once __DIR__ . '/includes/auth.php';
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
            if ($action === 'accept') {
                if ($stmt = $conn->prepare('SELECT tl.trade_type, tl.have_sku, o.offered_sku, o.use_escrow, ph.quantity AS have_qty, po.quantity AS offer_qty FROM trade_offers o JOIN trade_listings tl ON o.listing_id = tl.id LEFT JOIN products ph ON tl.have_sku = ph.sku LEFT JOIN products po ON o.offered_sku = po.sku WHERE o.id=? AND tl.owner_id=? AND o.status="pending"')) {
                    $stmt->bind_param('ii', $offer_id, $user_id);
                    $stmt->execute();
                    $stmt->bind_result($trade_type, $have_sku, $offered_sku, $use_escrow, $have_qty, $offer_qty);
                    if ($stmt->fetch()) {
                        $stmt->close();
                        $conn->begin_transaction();
                        if ($stmt2 = $conn->prepare('UPDATE trade_offers o JOIN trade_listings l ON o.listing_id = l.id SET o.status="accepted", l.status="accepted" WHERE o.id=? AND l.owner_id=? AND o.status="pending"')) {
                            $stmt2->bind_param('ii', $offer_id, $user_id);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                        if ($trade_type === 'item') {
                            if ($have_sku && $offered_sku && $have_qty > 0 && $offer_qty > 0) {
                                if ($stmt2 = $conn->prepare('UPDATE products SET quantity = quantity - 1 WHERE sku = ? AND quantity > 0')) {
                                    $stmt2->bind_param('s', $have_sku);
                                    $stmt2->execute();
                                    $stmt2->close();
                                }
                                if ($stmt2 = $conn->prepare('UPDATE products SET quantity = quantity - 1 WHERE sku = ? AND quantity > 0')) {
                                    $stmt2->bind_param('s', $offered_sku);
                                    $stmt2->execute();
                                    $stmt2->close();
                                }
                                $conn->commit();
                            } else {
                                $conn->rollback();
                                $error = 'Trade items out of stock.';
                            }
                        } else {
                            $conn->commit();
                        }
                        if ($use_escrow) {
                            if ($stmt2 = $conn->prepare('INSERT INTO escrow_transactions (offer_id) VALUES (?)')) {
                                $stmt2->bind_param('i', $offer_id);
                                $stmt2->execute();
                                $stmt2->close();
                            }
                        }
                    } else {
                        $stmt->close();
                    }
                }
            } else {
                $status = 'declined';
                if ($stmt = $conn->prepare('UPDATE trade_offers o JOIN trade_listings l ON o.listing_id = l.id SET o.status=? WHERE o.id=? AND l.owner_id=? AND o.status="pending"')) {
                    $stmt->bind_param('sii', $status, $offer_id, $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
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
    if ($stmt = $conn->prepare('SELECT have_item, want_item, trade_type, description, image FROM trade_listings WHERE id = ? AND owner_id = ?')) {
        $stmt->bind_param('ii', $listing_filter, $user_id);
        $stmt->execute();
        $listing_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fetch offers
$offers = [];
if ($listing_filter) {
    $sql = 'SELECT o.id, p.title AS offer_item, o.payment_amount, o.payment_method, o.status, o.use_escrow, o.message, u.username FROM trade_offers o JOIN trade_listings l ON o.listing_id = l.id JOIN users u ON o.offerer_id = u.id LEFT JOIN products p ON o.offered_sku = p.sku WHERE o.listing_id = ? AND l.owner_id = ? ORDER BY o.created_at DESC';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ii', $listing_filter, $user_id);
        $stmt->execute();
        $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $sql = 'SELECT o.id, p.title AS offer_item, o.payment_amount, o.payment_method, o.status, o.use_escrow, o.message, tl.have_item, tl.want_item, tl.trade_type, u.username, (tl.owner_id = ?) AS incoming FROM trade_offers o JOIN trade_listings tl ON o.listing_id = tl.id JOIN users u ON o.offerer_id = u.id LEFT JOIN products p ON o.offered_sku = p.sku WHERE o.offerer_id = ? OR tl.owner_id = ? ORDER BY o.created_at DESC';
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
        <p>Type: <strong><?= htmlspecialchars($listing_info['trade_type']) ?></strong></p>
        <p><?= nl2br(htmlspecialchars($listing_info['description'])) ?></p>
      <?php if (!empty($listing_info['image'])): ?><p><img src="uploads/<?= htmlspecialchars($listing_info['image']) ?>" alt="Listing image" style="max-width:200px"></p><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($offers): ?>
    <table>
      <tr>
          <?php if ($listing_filter): ?>
            <th>From</th><th>Offer</th><th>Escrow</th><th>Message</th><th>Status</th><th>Actions</th>
          <?php else: ?>
            <th>Listing Have/Want</th><th>From</th><th>Offer</th><th>Escrow</th><th>Status</th><th>Actions</th>
          <?php endif; ?>
      </tr>
      <?php foreach ($offers as $o): ?>
        <tr>
            <?php if ($listing_filter): ?>
              <td><?= htmlspecialchars($o['username']) ?></td>
              <td>
                <?php if (($listing_info['trade_type'] ?? '') === 'cash_card'): ?>
                  $<?= htmlspecialchars(number_format($o['payment_amount'], 2)) ?> via <?= htmlspecialchars($o['payment_method']) ?>
                <?php else: ?>
                  <?= htmlspecialchars($o['offer_item']) ?>
                <?php endif; ?>
              </td>
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
                <?php if ($o['use_escrow']): ?>
                  <a href="escrow.php?offer=<?= $o['id'] ?>">Escrow</a>
                <?php endif; ?>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                  <form method="post" action="trade-offer-delete.php" style="display:inline" onsubmit="return confirm('Delete offer?');">
                    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                    <input type="hidden" name="id" value="<?= $o['id']; ?>">
                    <input type="hidden" name="redirect" value="trade.php?listing=<?= $listing_filter ?>">
                    <button type="submit">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            <?php else: ?>
              <td><?= htmlspecialchars($o['have_item']) ?>/<?= htmlspecialchars($o['want_item']) ?></td>
              <td><?= htmlspecialchars($o['username']) ?></td>
              <td>
                <?php if ($o['trade_type'] === 'cash_card'): ?>
                  $<?= htmlspecialchars(number_format($o['payment_amount'], 2)) ?> via <?= htmlspecialchars($o['payment_method']) ?>
                <?php else: ?>
                  <?= htmlspecialchars($o['offer_item']) ?>
                <?php endif; ?>
              </td>
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
                <?php if ($o['use_escrow']): ?>
                  <a href="escrow.php?offer=<?= $o['id'] ?>">Escrow</a>
                <?php endif; ?>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                  <form method="post" action="trade-offer-delete.php" style="display:inline" onsubmit="return confirm('Delete offer?');">
                    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                    <input type="hidden" name="id" value="<?= $o['id']; ?>">
                    <input type="hidden" name="redirect" value="trade.php">
                    <button type="submit">Delete</button>
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
