<?php
require __DIR__ . '/_debug_bootstrap.php';
declare(strict_types=1);

/**
 * /public_html/checkout_process.php
 * Direct HTTPS call to Square Payments API (NO SDK classes).
 * Expects POST:
 *   - token OR source_id  (from Web Payments SDK tokenize())
 *   - listing_id          (int) server computes price
 */

require __DIR__ . '/includes/auth.php';  // should establish $user_id (or from session)
$mysqli = require __DIR__ . '/includes/db.php'; // returns mysqli
$config = require __DIR__ . '/config.php';

try {
// --- Config ---
$env         = strtolower(trim($config['square_environment'] ?? 'sandbox'));
$accessToken = trim((string)($config['square_access_token'] ?? ''));
$locationId  = trim((string)($config['square_location_id'] ?? ''));
$currency    = strtoupper((string)($config['CURRENCY'] ?? 'USD'));

// --- Inputs ---
$sourceId = '';
if (isset($_POST['token']) && is_string($_POST['token'])) {
  $sourceId = trim($_POST['token']);
} elseif (isset($_POST['source_id']) && is_string($_POST['source_id'])) {
  $sourceId = trim($_POST['source_id']);
}
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

// --- Validate base inputs ---
if ($accessToken === '' || $locationId === '') {
  http_response_code(500);
  exit('Square configuration missing (access token or location id).');
}
if ($sourceId === '' || $listing_id <= 0) {
  http_response_code(400);
  exit('Invalid payment data.');
}

// --- Fetch price from DB (authoritative) ---
$price = null; // decimal dollars
$stmt = $mysqli->prepare('SELECT price FROM listings WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$stmt->bind_result($price);
if (!$stmt->fetch()) {
  $stmt->close();
  header('Location: /cancel.php');
  exit;
}
$stmt->close();

// Convert dollars->cents safely
$amount = (int)round(((float)$price) * 100);
if ($amount <= 0) {
  header('Location: /cancel.php');
  exit;
}

// --- Square API base ---
$base = ($env === 'production')
  ? 'https://connect.squareup.com'
  : 'https://connect.squareupsandbox.com';

// --- Build payload ---
$payload = [
  'idempotency_key' => bin2hex(random_bytes(16)),
  'source_id'       => $sourceId,
  'location_id'     => $locationId,
  'amount_money'    => [
    'amount'   => $amount,   // integer cents
    'currency' => $currency,
  ],
 
  // 'note' => 'Order #' . $listing_id,
  // 'autocomplete' => true,
];

// --- cURL call to /v2/payments ---
$ch = curl_init($base . '/v2/payments');
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 30,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/json',
    'Square-Version: 2024-08-15',
    'Authorization: Bearer ' . $accessToken,
  ],
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
]);

$raw  = curl_exec($ch);
$err  = curl_errno($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
  error_log('Square cURL error: ' . $err . ' body=' . $raw);
  http_response_code(502);
  exit('Payment gateway error.');
}

$resp     = json_decode($raw, true);
$status   = 'FAILED';
$paymentId = null;

if ($http >= 200 && $http < 300 && isset($resp['payment']['id'])) {
  $paymentId = (string)$resp['payment']['id'];
  $status    = strtoupper((string)($resp['payment']['status'] ?? 'COMPLETED'));
}

// Log payment ---
try {
  if (!isset($user_id)) {
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
  }
  if ($stmt = $mysqli->prepare('INSERT INTO payments (user_id, listing_id, amount, payment_id, status) VALUES (?,?,?,?,?)')) {
    $stmt->bind_param('iiiss', $user_id, $listing_id, $amount, $paymentId, $status);
    $stmt->execute();
    $stmt->close();
  }
} catch (\Throwable $e) {
  error_log('Payment log insert failed: ' . $e->getMessage());
}

// --- Final redirect ---
if ($status === 'COMPLETED' || $status === 'APPROVED' || $status === 'AUTHORIZED') {
  header('Location: /success.php');
  exit;
}

error_log('Square payment failed: HTTP ' . $http . ' ' . $raw);
header('Location: /cancel.php');
exit;
} catch (Throwable $e) {
  error_log('[checkout_process] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
  echo 'Payment processing error.';
  exit;
}
