<?php
/*
 * Wallet hub: surfaces store credit balance, ledger history, and exports for transparency.
 */
require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/require-auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/config.php';
}

if (empty($config['SHOW_WALLET'])) {
    if (!headers_sent()) {
        header('HTTP/1.1 404 Not Found');
    }
    exit('Wallet is currently unavailable.');
}

require_once __DIR__ . '/includes/WalletService.php';
$squareConfig = require __DIR__ . '/includes/square-config.php';
$applicationId = $squareConfig['application_id'];
$locationId = $squareConfig['location_id'];
$environment = $squareConfig['environment'];
$squareJs = $environment === 'production'
    ? 'https://web.squarecdn.com/v1/square.js'
    : 'https://sandbox.web.squarecdn.com/v1/square.js';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    require_auth();
}

$walletService = new WalletService($conn instanceof mysqli ? $conn : (function () use ($config) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(
        $config['db_host'] ?? '127.0.0.1',
        $config['db_user'] ?? '',
        $config['db_pass'] ?? '',
        $config['db_name'] ?? ''
    );
    $db->set_charset('utf8mb4');
    $db->query("SET sql_mode = ''");
    return $db;
})());

$balance = $walletService->getBalance($userId);

$memberStatus = 0;
$memberExpiresAt = null;
if ($stmt = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id = ?')) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $stmt->bind_result($memberStatus, $memberExpiresAt);
        $stmt->fetch();
    }
    $stmt->close();
}

$isMember = false;
if ($memberStatus) {
    $isMember = true;
    if ($memberExpiresAt) {
        $expiresTs = strtotime((string) $memberExpiresAt);
        if ($expiresTs !== false && $expiresTs <= time()) {
            $isMember = false;
        }
    }
}

$withdrawMinCents = (int) ($config['WITHDRAW_MIN_CENTS'] ?? $config['WALLET_WITHDRAW_MIN_CENTS'] ?? 100);
if ($withdrawMinCents < 0) {
    $withdrawMinCents = 0;
}
$withdrawMinDisplay = number_format($withdrawMinCents / 100, 2);
$withdrawMinInput = number_format($withdrawMinCents / 100, 2, '.', '');
$nonMemberFeePercent = (float) ($config['WITHDRAW_FEE_PERCENT_NON_MEMBER'] ?? 0.0);

try {
    $withdrawToken = bin2hex(random_bytes(16));
} catch (Throwable $tokenError) {
    $withdrawToken = hash('sha256', microtime(true) . '|' . $userId);
}
$_SESSION['wallet_withdraw_token'] = $withdrawToken;

$rangeDays = isset($_GET['range']) ? max(7, min(90, (int) $_GET['range'])) : 30;
$rangeLabel = $rangeDays === 30 ? '30 days' : ($rangeDays . ' days');
$since = (new DateTimeImmutable())->modify('-' . $rangeDays . ' days');

$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$ledgerStmt = $conn->prepare('SELECT entry_type, sign, amount_cents, balance_after_cents, related_type, related_id, meta, created_at FROM wallet_ledger WHERE user_id = ? AND created_at >= ? ORDER BY created_at DESC');
if ($ledgerStmt === false) {
    throw new RuntimeException('Unable to prepare ledger query.');
}
$sinceFormatted = $since->format('Y-m-d H:i:s');
$ledgerStmt->bind_param('is', $userId, $sinceFormatted);
$ledgerStmt->execute();
$ledgerResult = $ledgerStmt->get_result();

if ($export) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="wallet-ledger-' . $userId . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Type', 'Direction', 'Amount ($)', 'Balance After ($)', 'Reference', 'Metadata']);
    while ($row = $ledgerResult->fetch_assoc()) {
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

$ledgerEntries = [];
while ($row = $ledgerResult->fetch_assoc()) {
    $ledgerEntries[] = $row;
}
$ledgerStmt->close();

?>
<?php require __DIR__ . '/includes/layout.php'; ?>
  <title>Wallet (Store Credit)</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="<?= htmlspecialchars($squareJs); ?>"></script>
  <script src="assets/wallet-topup.js" defer></script>
</head>
<body>
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <main class="container wallet-page">
    <header class="wallet-page__header">
      <h1>Wallet (Store Credit)</h1>
      <p class="wallet-page__disclaimer">Funds are held as store credit and are not a bank account. Use within SkuzE only.</p>
      <?php
        $alerts = [];
        if (!empty($_GET['topup'])) {
            $alerts[] = ['class' => 'alert-success', 'role' => 'status', 'message' => 'Funds added to your wallet successfully.'];
        }
        if (!empty($_GET['withdraw'])) {
            $alerts[] = ['class' => 'alert-success', 'role' => 'status', 'message' => 'Withdrawal request submitted. We will notify you once it is processed.'];
        }
        if (!empty($_GET['error'])) {
            $alerts[] = ['class' => 'alert-danger', 'role' => 'alert', 'message' => 'We were unable to process that request. Please verify your details and try again.'];
        }
        $withdrawError = isset($_GET['withdraw_error']) ? (string) $_GET['withdraw_error'] : '';
        if ($withdrawError !== '') {
            $withdrawMessages = [
                'csrf' => 'The withdrawal form expired. Please try again.',
                'invalid' => 'Enter a valid withdrawal amount before submitting.',
                'min' => 'Withdrawal amount must meet the minimum requirement.',
                'insufficient' => 'Insufficient wallet balance for that withdrawal request.',
                'general' => 'We were unable to submit your withdrawal. Please try again.',
            ];
            $alerts[] = [
                'class' => 'alert-danger',
                'role' => 'alert',
                'message' => $withdrawMessages[$withdrawError] ?? 'We were unable to submit your withdrawal. Please try again.',
            ];
        }
        foreach ($alerts as $alert):
      ?>
        <div class="alert <?= htmlspecialchars($alert['class'], ENT_QUOTES, 'UTF-8'); ?>" role="<?= htmlspecialchars($alert['role'], ENT_QUOTES, 'UTF-8'); ?>">
          <?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endforeach; ?>
    </header>
    <section class="wallet-balances">
      <div class="wallet-balances__item">
        <h2>Available</h2>
        <p>$<?= htmlspecialchars(number_format($balance['available_cents'] / 100, 2)) ?></p>
      </div>
      <div class="wallet-balances__item">
        <h2>Pending / Escrow</h2>
        <p>$<?= htmlspecialchars(number_format($balance['pending_cents'] / 100, 2)) ?></p>
      </div>
    </section>
    <section class="wallet-topup">
      <h2>Add Funds</h2>
      <form id="wallet-topup-form" method="post" action="wallet_topup_process.php">
        <label for="topup-amount">Amount (USD)</label>
        <input type="number" id="topup-amount" name="amount" min="1" step="0.01" required>
        <div id="wallet-card-container" data-app-id="<?= htmlspecialchars($applicationId); ?>" data-location-id="<?= htmlspecialchars($locationId); ?>"></div>
        <input type="hidden" name="token" id="wallet-token">
        <button type="submit" class="btn">Add Funds</button>
      </form>
      <p class="wallet-page__disclaimer">Top-ups process through Square Payments; receipts appear in your ledger instantly.</p>
    </section>
    <section class="wallet-withdraw">
      <h2>Withdraw Funds</h2>
      <p class="wallet-page__disclaimer">
        <?php if ($isMember): ?>
          You are a Member. Withdrawal requests are fee-free.
        <?php else: ?>
          Members withdraw free; non-members pay <?= htmlspecialchars(number_format($nonMemberFeePercent, 2)); ?>%.
        <?php endif; ?>
      </p>
      <form method="post" action="wallet_withdraw_process.php" class="wallet-withdraw__form">
        <label for="withdraw-amount">Amount (USD)</label>
        <input type="number" id="withdraw-amount" name="amount" min="<?= htmlspecialchars($withdrawMinInput); ?>" step="0.01" required>
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="withdraw_token" value="<?= htmlspecialchars($withdrawToken); ?>">
        <button type="submit" class="btn">Request Withdrawal</button>
      </form>
      <p class="wallet-page__disclaimer">Minimum withdrawal: $<?= htmlspecialchars($withdrawMinDisplay); ?>. Requests are reviewed before payout.</p>
      <?php if (!$isMember): ?>
        <p class="wallet-page__disclaimer">Upgrade to a <a href="/member.php">Member plan</a> to remove withdrawal fees.</p>
      <?php endif; ?>
      <p class="wallet-page__disclaimer">Need details? Review the <a href="/policies/wallet.php">wallet policy</a>.</p>
    </section>
    <section class="wallet-history">
      <header class="wallet-history__header">
        <h2>Recent Activity (<?= htmlspecialchars($rangeLabel) ?>)</h2>
        <div class="wallet-history__actions">
          <form method="get" class="wallet-range">
            <label for="wallet-range">Range</label>
            <select id="wallet-range" name="range">
              <option value="30" <?= $rangeDays === 30 ? 'selected' : '' ?>>Last 30 days</option>
              <option value="60" <?= $rangeDays === 60 ? 'selected' : '' ?>>Last 60 days</option>
              <option value="90" <?= $rangeDays === 90 ? 'selected' : '' ?>>Last 90 days</option>
            </select>
            <button type="submit">Apply</button>
          </form>
          <a class="btn" href="?export=csv&amp;range=<?= $rangeDays ?>">Export CSV</a>
        </div>
      </header>
      <?php if (empty($ledgerEntries)): ?>
        <p>No wallet activity recorded for this period.</p>
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
                $meta = $entry['meta'] ? json_decode((string) $entry['meta'], true) : [];
              ?>
              <tr>
                <td><?= htmlspecialchars($entry['created_at']) ?></td>
                <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $entry['entry_type']))) ?></td>
                <td><?= $entry['sign'] === '+' ? 'Credit' : 'Debit' ?></td>
                <td>$<?= htmlspecialchars(number_format(((int) $entry['amount_cents']) / 100, 2)) ?></td>
                <td>$<?= htmlspecialchars(number_format(((int) $entry['balance_after_cents']) / 100, 2)) ?></td>
                <td>
                  <?= htmlspecialchars($reference ?: '-') ?>
                  <?php if (!empty($meta['pending_after'])): ?>
                    <div class="wallet-history__meta">Pending after: $<?= htmlspecialchars(number_format(((int) $meta['pending_after']) / 100, 2)) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
    <section class="wallet-help">
      <h2>Need Help?</h2>
      <p>Review the <a href="/policies/wallet.php">wallet policy</a> or <a href="/support.php">contact support</a> for questions.</p>
    </section>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
