<?php
require __DIR__ . '/_debug_bootstrap.php';
require_once __DIR__ . '/includes/require-auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/SquareHttpClient.php';
require_once __DIR__ . '/includes/square-log.php';

if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/config.php';
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    require_auth();
}

if (!validate_token($_POST['csrf_token'] ?? '')) {
    header('Location: /member.php?error=invalid');
    exit;
}

$purchaseToken = isset($_POST['purchase_token']) ? trim((string) $_POST['purchase_token']) : '';
$sessionToken = isset($_SESSION['member_purchase_token']) ? (string) $_SESSION['member_purchase_token'] : '';
if ($purchaseToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $purchaseToken)) {
    header('Location: /member.php?error=invalid');
    exit;
}

$plans = [
    'month' => [
        'label' => '1 Month',
        'amount_cents' => 2499,
        'interval' => 'P1M',
    ],
    'year' => [
        'label' => '1 Year',
        'amount_cents' => 24999,
        'interval' => 'P1Y',
    ],
];

$planKey = isset($_POST['plan']) ? strtolower(trim((string) $_POST['plan'])) : '';
if ($planKey === '' || !isset($plans[$planKey])) {
    header('Location: /member.php?error=amount');
    exit;
}

$plan = $plans[$planKey];
$amountCents = (int) $plan['amount_cents'];
if ($amountCents <= 0) {
    header('Location: /member.php?error=amount');
    exit;
}

$currency = strtoupper((string) ($_POST['currency'] ?? ($config['CURRENCY'] ?? 'USD')));
$token = isset($_POST['token']) ? trim((string) $_POST['token']) : '';
if ($token === '') {
    header('Location: /member.php?error=payment');
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

$squareClient = new SquareHttpClient($config);
$idempotencyKey = 'member-purchase-' . $purchaseToken;
$payload = [
    'idempotency_key' => $idempotencyKey,
    'source_id' => $token,
    'location_id' => $squareClient->getLocationId(),
    'amount_money' => [
        'amount' => $amountCents,
        'currency' => $currency,
    ],
    'note' => 'Membership purchase for user ' . $userId,
];

try {
    $response = $squareClient->request('POST', '/v2/payments', $payload, $idempotencyKey);
} catch (Throwable $squareError) {
    square_log('square.membership_payment_failed', [
        'user_id' => $userId,
        'error' => $squareError->getMessage(),
    ]);
    header('Location: /member.php?error=payment');
    exit;
}

$statusCode = (int) ($response['statusCode'] ?? 500);
$body = $response['body'] ?? [];
$payment = is_array($body) && isset($body['payment']) ? $body['payment'] : null;
if ($statusCode < 200 || $statusCode >= 300 || !is_array($payment)) {
    square_log('square.membership_payment_error', [
        'user_id' => $userId,
        'status' => $statusCode,
        'body' => $body,
    ]);
    header('Location: /member.php?error=payment');
    exit;
}

$paymentId = (string) ($payment['id'] ?? '');
$paymentStatus = strtoupper((string) ($payment['status'] ?? ''));
$paidAmount = (int) ($payment['amount_money']['amount'] ?? 0);
$paidCurrency = strtoupper((string) ($payment['amount_money']['currency'] ?? ''));

if ($paymentStatus !== 'COMPLETED' || $paidAmount !== $amountCents || $paidCurrency !== $currency) {
    square_log('square.membership_payment_mismatch', [
        'user_id' => $userId,
        'payment_id' => $paymentId,
        'status' => $paymentStatus,
        'expected_amount' => $amountCents,
        'actual_amount' => $paidAmount,
        'expected_currency' => $currency,
        'actual_currency' => $paidCurrency,
    ]);
    header('Location: /member.php?error=payment');
    exit;
}

$currentStatus = 0;
$currentExpires = null;
$db->begin_transaction();
try {
    if ($stmt = $db->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id = ? FOR UPDATE')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($currentStatus, $currentExpires);
        $stmt->fetch();
        $stmt->close();
    }

    $baseTime = new DateTimeImmutable('now');
    if ($currentStatus && $currentExpires) {
        $expiresTs = strtotime((string) $currentExpires);
        if ($expiresTs !== false && $expiresTs > time()) {
            $baseTime = new DateTimeImmutable((string) $currentExpires);
        }
    }

    $interval = new DateInterval($plan['interval']);
    $newExpiry = $baseTime->add($interval);
    $newExpiryFormatted = $newExpiry->format('Y-m-d H:i:s');

    if ($stmt = $db->prepare('UPDATE users SET vip_status = 1, vip_expires_at = ? WHERE id = ?')) {
        $stmt->bind_param('si', $newExpiryFormatted, $userId);
        $stmt->execute();
        $stmt->close();
    }

    $db->commit();
} catch (Throwable $updateError) {
    $db->rollback();
    square_log('square.membership_grant_failed', [
        'user_id' => $userId,
        'error' => $updateError->getMessage(),
    ]);
    header('Location: /member.php?error=invalid');
    exit;
}

$_SESSION['vip_status'] = 1;
$_SESSION['vip_expires_at'] = $newExpiryFormatted;
$_SESSION['member_status'] = 1;
$_SESSION['member_expires_at'] = $newExpiryFormatted;
$memberRoleName = strtolower((string) ($config['MEMBER_ROLE_NAME'] ?? 'member'));
if ($memberRoleName === '') {
    $memberRoleName = 'member';
}
if (!isset($_SESSION['status']) || $_SESSION['status'] === 'vip') {
    $_SESSION['status'] = $memberRoleName;
}
if (function_exists('auth_refresh_session_roles')) {
    auth_refresh_session_roles();
}

square_log('square.membership_granted', [
    'user_id' => $userId,
    'payment_id' => $paymentId,
    'amount_cents' => $amountCents,
    'plan' => $planKey,
]);

try {
    $_SESSION['member_purchase_token'] = bin2hex(random_bytes(16));
} catch (Throwable $tokenRefreshError) {
    $_SESSION['member_purchase_token'] = hash('sha256', microtime(true) . '|' . $userId);
}

header('Location: /member.php?success=1');
exit;
