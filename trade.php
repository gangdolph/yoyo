<?php
// Update: Added trades policy reminder so offer management references transparency docs.
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/trade.php';

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
        if ($offer_id && in_array($action, ['accept', 'decline'], true)) {
            try {
                if ($action === 'accept') {
                    trade_accept_offer($conn, $offer_id, $user_id);
                } else {
                    trade_decline_offer($conn, $offer_id, $user_id, 'declined');
                }

                $redirect = 'trade.php';
                if ($listing_filter) {
                    $redirect .= '?listing=' . $listing_filter;
                }
                header('Location: ' . $redirect);
                exit;
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('[trade] Failed to update offer #' . $offer_id . ': ' . $e->getMessage());
                $error = 'Unable to update the offer. Please try again later.';
            }
        }
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
  <aside class="policy-callout trade-policy-overview" aria-live="polite">
    <h3>How trades stay fair</h3>
    <p>Review our <a href="/policies/trades.php">Trades &amp; Escrow policy</a> for hold releases, dispute paths, and audit logging expectations.</p>
  </aside>
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
                <?php if (is_admin()): ?>
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
                <?php if (is_admin()): ?>
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
