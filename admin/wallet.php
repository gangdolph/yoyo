<?php
/*
 * Admin wallet console: view balances, run adjustments, and export ledger history for compliance.
 */
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = require __DIR__ . '/../includes/db.php';
}

if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/../config.php';
}

if (empty($config['SHOW_WALLET'])) {
    if (!headers_sent()) {
        header('HTTP/1.1 404 Not Found');
    }
    exit('Wallet management disabled.');
}

ensure_admin('../dashboard.php');

require_once __DIR__ . '/../includes/WalletService.php';

$walletService = new WalletService($conn);
$feedback = '';
$feedbackType = '';

$selectedUserId = isset($_GET['user']) ? max(0, (int) $_GET['user']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $amount = isset($_POST['amount_cents']) ? (int) $_POST['amount_cents'] : 0;
    $note = trim((string) ($_POST['note'] ?? ''));

    if ($targetUserId <= 0 || $amount <= 0) {
        $feedback = 'A user and positive amount are required.';
        $feedbackType = 'error';
    } else {
        $idempotencyKey = 'admin-adjust-' . bin2hex(random_bytes(8));
        $meta = ['note' => $note, 'actor' => (int) $_SESSION['user_id']];
        try {
            if ($action === 'credit') {
                $walletService->credit($targetUserId, $amount, $idempotencyKey, 'admin_adjust', null, $meta, 'adjust');
                $walletService->logAudit((int) $_SESSION['user_id'], 'wallet_credit', [
                    'user_id' => $targetUserId,
                    'amount_cents' => $amount,
                    'note' => $note,
                ]);
                $feedback = 'Credit applied successfully.';
                $feedbackType = 'success';
            } elseif ($action === 'debit') {
                $walletService->debit($targetUserId, $amount, $idempotencyKey, 'admin_adjust', null, $meta, 'adjust');
                $walletService->logAudit((int) $_SESSION['user_id'], 'wallet_debit', [
                    'user_id' => $targetUserId,
                    'amount_cents' => $amount,
                    'note' => $note,
                ]);
                $feedback = 'Debit applied successfully.';
                $feedbackType = 'success';
            }
            $selectedUserId = $targetUserId;
        } catch (Throwable $e) {
            $feedback = 'Adjustment failed: ' . $e->getMessage();
            $feedbackType = 'error';
        }
    }
}

$searchedUser = null;
if (isset($_GET['username']) && $_GET['username'] !== '') {
    $username = trim((string) $_GET['username']);
    $stmt = $conn->prepare('SELECT id, username, email FROM users WHERE username = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $searchedUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($searchedUser) {
            $selectedUserId = (int) $searchedUser['id'];
        }
    }
}

$export = isset($_GET['export']) && $_GET['export'] === 'csv' && $selectedUserId > 0;
$ledgerEntries = [];
$balance = ['available_cents' => 0, 'pending_cents' => 0];
$userRecord = null;

if ($selectedUserId > 0) {
    $userStmt = $conn->prepare('SELECT id, username, email FROM users WHERE id = ? LIMIT 1');
    if ($userStmt) {
        $userStmt->bind_param('i', $selectedUserId);
        $userStmt->execute();
        $userRecord = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
    }

    if ($userRecord) {
        $balance = $walletService->getBalance($selectedUserId);
        $ledgerStmt = $conn->prepare('SELECT entry_type, sign, amount_cents, balance_after_cents, related_type, related_id, meta, created_at FROM wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 200');
        if ($ledgerStmt) {
            $ledgerStmt->bind_param('i', $selectedUserId);
            $ledgerStmt->execute();
            $result = $ledgerStmt->get_result();
            if ($export) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="wallet-ledger-user-' . $selectedUserId . '.csv"');
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Date', 'Type', 'Direction', 'Amount ($)', 'Balance After ($)', 'Reference', 'Metadata']);
                while ($row = $result->fetch_assoc()) {
                    $meta = $row['meta'] ? json_decode((string) $row['meta'], true) : null;
                    $reference = $row['related_type'];
                    if (!empty($row['related_id'])) {
                        $reference = trim($reference . '#' . $row['related_id']);
                    }
                    fputcsv($out, [
                        $row['created_at'],
                        $row['entry_type'],
                        $row['sign'],
                        number_format(((int) $row['amount_cents']) / 100, 2),
                        number_format(((int) $row['balance_after_cents']) / 100, 2),
                        $reference,
                        $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                    ]);
                }
                fclose($out);
                $ledgerStmt->close();
                exit;
            }

            while ($row = $result->fetch_assoc()) {
                $ledgerEntries[] = $row;
            }
            $ledgerStmt->close();
        }
    }
}

?>
<?php require __DIR__ . '/../includes/layout.php'; ?>
  <title>Admin Wallet Manager</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <main class="container admin-wallet">
    <h1>Admin Wallet Manager</h1>
    <p>Review balances, export ledgers, and run manual adjustments. All changes are logged.</p>

    <?php if ($feedback !== ''): ?>
      <div class="alert <?= $feedbackType === 'error' ? 'alert-danger' : 'alert-success' ?>"><?= htmlspecialchars($feedback) ?></div>
    <?php endif; ?>

    <section class="admin-wallet__search">
      <form method="get">
        <label for="username">Find user by username</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($_GET['username'] ?? '') ?>" placeholder="username">
        <button type="submit">Search</button>
      </form>
      <form method="get" class="admin-wallet__id-lookup">
        <label for="wallet-user-id">or jump to user ID</label>
        <input type="number" id="wallet-user-id" name="user" value="<?= $selectedUserId > 0 ? $selectedUserId : '' ?>" min="1">
        <button type="submit">Load</button>
      </form>
    </section>

    <?php if ($userRecord): ?>
      <section class="admin-wallet__overview">
        <h2>User Overview</h2>
        <p><strong><?= htmlspecialchars($userRecord['username']) ?></strong> (ID <?= (int) $userRecord['id'] ?>, <?= htmlspecialchars($userRecord['email']) ?>)</p>
        <dl class="wallet-balances">
          <div class="wallet-balances__item">
            <dt>Available</dt>
            <dd>$<?= htmlspecialchars(number_format($balance['available_cents'] / 100, 2)) ?></dd>
          </div>
          <div class="wallet-balances__item">
            <dt>Pending</dt>
            <dd>$<?= htmlspecialchars(number_format($balance['pending_cents'] / 100, 2)) ?></dd>
          </div>
        </dl>
        <p><a class="btn" href="?user=<?= (int) $userRecord['id'] ?>&amp;export=csv">Export CSV</a></p>
      </section>

      <section class="admin-wallet__adjust">
        <h2>Manual Adjustment</h2>
        <form method="post">
          <input type="hidden" name="user_id" value="<?= (int) $userRecord['id'] ?>">
          <label for="amount_cents">Amount (cents)</label>
          <input type="number" id="amount_cents" name="amount_cents" min="1" required>
          <label for="note">Reason / note</label>
          <input type="text" id="note" name="note" maxlength="255" placeholder="Short note for audit log">
          <div class="admin-wallet__actions">
            <button type="submit" name="action" value="credit" class="btn">Credit</button>
            <button type="submit" name="action" value="debit" class="btn btn-danger">Debit</button>
          </div>
        </form>
      </section>

      <section class="admin-wallet__ledger">
        <h2>Ledger</h2>
        <?php if (empty($ledgerEntries)): ?>
          <p>No ledger entries recorded.</p>
        <?php else: ?>
          <table class="wallet-history__table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Direction</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Reference</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ledgerEntries as $entry): ?>
                <?php
                  $reference = $entry['related_type'];
                  if (!empty($entry['related_id'])) {
                      $reference = trim($reference . '#' . $entry['related_id']);
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars($entry['created_at']) ?></td>
                  <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $entry['entry_type']))) ?></td>
                  <td><?= $entry['sign'] === '+' ? 'Credit' : 'Debit' ?></td>
                  <td>$<?= htmlspecialchars(number_format(((int) $entry['amount_cents']) / 100, 2)) ?></td>
                  <td>$<?= htmlspecialchars(number_format(((int) $entry['balance_after_cents']) / 100, 2)) ?></td>
                  <td><?= htmlspecialchars($reference ?: '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php elseif ($selectedUserId > 0): ?>
      <p class="alert alert-warning">No user found for ID <?= $selectedUserId ?>.</p>
    <?php endif; ?>
  </main>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
