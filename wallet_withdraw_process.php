<?php
require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/require-auth.php';
require_once __DIR__ . '/includes/csrf.php';

$config = require __DIR__ . '/config.php';

if (empty($config['SHOW_WALLET'])) {
    header('Location: /wallet.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    require_auth();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validate_token($csrfToken)) {
    $_SESSION['wallet_withdraw_token'] = null;
    header('Location: /wallet.php?withdraw_error=csrf');
    exit;
}

$providedToken = isset($_POST['withdraw_token']) ? trim((string) $_POST['withdraw_token']) : '';
$sessionToken = isset($_SESSION['wallet_withdraw_token']) ? (string) $_SESSION['wallet_withdraw_token'] : '';
if ($providedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
    $_SESSION['wallet_withdraw_token'] = null;
    header('Location: /wallet.php?withdraw_error=csrf');
    exit;
}

$amountInput = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;
if ($amountInput <= 0) {
    $_SESSION['wallet_withdraw_token'] = null;
    header('Location: /wallet.php?withdraw_error=invalid');
    exit;
}

$amountCents = (int) round($amountInput * 100);
$minimumCents = (int) ($config['WITHDRAW_MIN_CENTS'] ?? $config['WALLET_WITHDRAW_MIN_CENTS'] ?? 100);
if ($amountCents < $minimumCents) {
    $_SESSION['wallet_withdraw_token'] = null;
    header('Location: /wallet.php?withdraw_error=min');
    exit;
}

$maybeDb = require __DIR__ . '/includes/db.php';
$db = $maybeDb instanceof mysqli ? $maybeDb : ($conn ?? null);
if (!$db instanceof mysqli) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(
        $config['db_host'] ?? '127.0.0.1',
        $config['db_user'] ?? '',
        $config['db_pass'] ?? '',
        $config['db_name'] ?? ''
    );
    $db->set_charset('utf8mb4');
    $db->query("SET sql_mode = ''");
}

$memberStatus = 0;
$memberExpiresAt = null;
if ($stmt = $db->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id = ?')) {
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

$feePercent = (float) ($config['WITHDRAW_FEE_PERCENT_NON_MEMBER'] ?? 0.0);
$feeCents = $isMember ? 0 : (int) round($amountCents * $feePercent / 100);
if ($feeCents < 0) {
    $feeCents = 0;
}

require_once __DIR__ . '/includes/WalletService.php';

$walletService = new WalletService($db);
$idempotencyKey = 'wallet-withdraw-' . $providedToken;

try {
    $walletService->requestWithdrawal($userId, $amountCents, $feeCents, $idempotencyKey);
} catch (RuntimeException $runtimeError) {
    $message = $runtimeError->getMessage();
    if (stripos($message, 'Insufficient wallet balance') !== false) {
        $_SESSION['wallet_withdraw_token'] = null;
        header('Location: /wallet.php?withdraw_error=insufficient');
        exit;
    }
    error_log('[wallet_withdraw_process] runtime error: ' . $message);
    $_SESSION['wallet_withdraw_token'] = null;
    header('Location: /wallet.php?withdraw_error=general');
    exit;
} catch (Throwable $error) {
    error_log('[wallet_withdraw_process] unexpected error: ' . $error->getMessage());
    $_SESSION['wallet_withdraw_token'] = null;
    header('Location: /wallet.php?withdraw_error=general');
    exit;
}

try {
    $_SESSION['wallet_withdraw_token'] = bin2hex(random_bytes(16));
} catch (Throwable $tokenRefreshError) {
    $_SESSION['wallet_withdraw_token'] = hash('sha256', microtime(true) . '|' . $userId);
}

header('Location: /wallet.php?withdraw=1');
exit;
