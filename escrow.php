<?php
// Update: Added wallet hold policy reminder for escrow participants.
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$offer_id = isset($_GET['offer']) ? intval($_GET['offer']) : 0;
$error = '';
$escrow = null;

if (!$offer_id) {
    die('Offer not specified.');
}

// Fetch offer and escrow info
if ($stmt = $conn->prepare('SELECT o.offerer_id, l.owner_id, o.use_escrow, e.id AS escrow_id, e.status, e.verified_by FROM trade_offers o JOIN trade_listings l ON o.listing_id = l.id LEFT JOIN escrow_transactions e ON e.offer_id = o.id WHERE o.id = ?')) {
    $stmt->bind_param('i', $offer_id);
    $stmt->execute();
    $escrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$escrow || !$escrow['use_escrow']) {
    die('Escrow not available.');
}

// Create escrow record if missing
if (!$escrow['escrow_id']) {
    if ($stmt = $conn->prepare('INSERT INTO escrow_transactions (offer_id) VALUES (?)')) {
        $stmt->bind_param('i', $offer_id);
        $stmt->execute();
        $stmt->close();
        $escrow['escrow_id'] = $conn->insert_id;
        $escrow['status'] = 'initiated';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'fund' && $escrow['status'] === 'initiated' && $escrow['offerer_id'] == $user_id) {
            if ($stmt = $conn->prepare("UPDATE escrow_transactions SET status='funded' WHERE id=?")) {
                $stmt->bind_param('i', $escrow['escrow_id']);
                $stmt->execute();
                $stmt->close();
                $escrow['status'] = 'funded';
            }
        } elseif ($action === 'verify' && is_admin() && $escrow['status'] === 'funded') {
            if ($stmt = $conn->prepare("UPDATE escrow_transactions SET status='verified', verified_by=? WHERE id=?")) {
                $stmt->bind_param('ii', $user_id, $escrow['escrow_id']);
                $stmt->execute();
                $stmt->close();
                $escrow['status'] = 'verified';
                $escrow['verified_by'] = $user_id;
            }
        } elseif ($action === 'release' && is_admin() && $escrow['status'] === 'verified') {
            if ($stmt = $conn->prepare("UPDATE escrow_transactions SET status='released' WHERE id=?")) {
                $stmt->bind_param('i', $escrow['escrow_id']);
                $stmt->execute();
                $stmt->close();
                $escrow['status'] = 'released';
            }
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Escrow Status</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Escrow Status</h2>
  <aside class="policy-callout wallet-policy-callout" aria-live="polite">
    <h3>Holds &amp; Withdrawals</h3>
    <p>Escrow balances follow our <a href="/policies/wallet.php">wallet policy</a> — holds auto-release on schedule unless escalated.</p>
  </aside>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <p>Status: <strong><?= htmlspecialchars($escrow['status']) ?></strong></p>
  <?php if ($escrow['status'] === 'initiated' && $escrow['offerer_id'] == $user_id): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit" name="action" value="fund">Fund Escrow</button>
    </form>
  <?php endif; ?>
  <?php if (is_admin() && $escrow['status'] === 'funded'): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit" name="action" value="verify">Verify Item</button>
    </form>
  <?php endif; ?>
  <?php if (is_admin() && $escrow['status'] === 'verified'): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit" name="action" value="release">Release Escrow</button>
    </form>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
