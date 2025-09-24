<?php
/*
 * Wallet top-up handler: charges via Square and credits the user's wallet ledger.
 */
require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/require-auth.php';

$maybeDb = require __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/config.php';

if (empty($config['SHOW_WALLET'])) {
    header('Location: /wallet.php');
    exit;
}

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

require_once __DIR__ . '/includes/SquareHttpClient.php';
require_once __DIR__ . '/includes/square-log.php';
require_once __DIR__ . '/includes/WalletService.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    require_auth();
}

$token = isset($_POST['token']) ? trim((string) $_POST['token']) : '';
$amountInput = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;

if ($token === '' || $amountInput <= 0) {
    header('Location: /wallet.php?error=invalid');
    exit;
}

$amountCents = (int) round($amountInput * 100);
if ($amountCents <= 0) {
    header('Location: /wallet.php?error=invalid');
    exit;
}

$squareClient = new SquareHttpClient($config);
$currency = strtoupper((string) ($config['CURRENCY'] ?? 'USD'));
$locationId = $squareClient->getLocationId();
$idempotencyKey = bin2hex(random_bytes(16));

$payload = [
    'idempotency_key' => $idempotencyKey,
    'source_id' => $token,
    'location_id' => $locationId,
    'amount_money' => [
        'amount' => $amountCents,
        'currency' => $currency,
    ],
    'note' => 'Wallet top-up for user ' . $userId,
];

try {
    $response = $squareClient->request('POST', '/v2/payments', $payload, $idempotencyKey);
} catch (Throwable $requestError) {
    square_log('square.wallet_topup_failed', [
        'user_id' => $userId,
        'error' => $requestError->getMessage(),
    ]);
    header('Location: /wallet.php?error=payment');
    exit;
}

$httpStatus = $response['statusCode'] ?? 500;
$body = $response['body'] ?? [];

if ($httpStatus < 200 || $httpStatus >= 300 || !isset($body['payment']['id'])) {
    square_log('square.wallet_topup_error', [
        'user_id' => $userId,
        'status' => $httpStatus,
        'body' => $body,
    ]);
    header('Location: /wallet.php?error=payment');
    exit;
}

$paymentId = (string) $body['payment']['id'];

try {
    $walletService = new WalletService($db);
    $walletService->topUp(
        $userId,
        $amountCents,
        'wallet-topup-' . $paymentId,
        'square_payment',
        $paymentId,
        ['idempotency_key' => $idempotencyKey]
    );
} catch (Throwable $walletError) {
    error_log('[wallet_topup_process] ledger update failed: ' . $walletError->getMessage());
    header('Location: /wallet.php?error=wallet');
    exit;
}

header('Location: /wallet.php?topup=1');
exit;
