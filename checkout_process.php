<?php
declare(strict_types=1);

require __DIR__ . '/_debug_bootstrap.php';

/**
 * /public_html/checkout_process.php
 * Direct HTTPS call to Square Payments API (NO SDK classes).
 * Expects POST:
 *   - token OR source_id  (from Web Payments SDK tokenize())
 *   - listing_id          (int) server computes price
 */

require __DIR__ . '/includes/auth.php';           // should establish $user_id (or from session)
$maybeDb = require __DIR__ . '/includes/db.php';  // may return mysqli OR set $conn/$mysqli globals
$config  = require __DIR__ . '/config.php';

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

  // Helper: redirect to cancel with short reason (and log details)
  $fail = function (string $reason, string $logDetail = '') {
    if ($logDetail !== '') error_log('[checkout_process] ' . $reason . ' :: ' . $logDetail);
    header('Location: /cancel.php?reason=' . urlencode($reason));
    exit;
  };

  // --- Validate base inputs ---
  if ($accessToken === '' || $locationId === '') {
    $fail('config_error', 'Missing access token or location id');
  }
  if ($sourceId === '') {
    $fail('missing_token', 'No token/source_id in POST');
  }
  if ($listing_id <= 0) {
    $fail('missing_listing', 'No listing_id in POST');
  }

  // --- Fetch price from DB (authoritative) ---
  $price = null; // decimal dollars
  $stmt = $mysqli->prepare('SELECT price FROM listings WHERE id = ? LIMIT 1');
  $stmt->bind_param('i', $listing_id);
  $stmt->execute();
  $stmt->bind_result($price);
  if (!$stmt->fetch()) {
    $stmt->close();
    $fail('listing_not_found', 'listing_id=' . $listing_id);
  }
  $stmt->close();

  // Convert dollars->cents safely
  $amount = (int)round(((float)$price) * 100);
  if ($amount <= 0) {
    $fail('invalid_amount', 'price=' . var_export($price, true));
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
    // Transport error (network/TLS/etc.)
    error_log('Square cURL error: ' . $err . ' body=' . $raw);
    $fail('gateway_error', 'cURL errno=' . $err);
  }

  $resp      = json_decode($raw, true);
  $status    = 'FAILED';
  $paymentId = null;

  if ($http >= 200 && $http < 300 && isset($resp['payment']['id'])) {
    $paymentId = (string)$resp['payment']['id'];
    $status    = strtoupper((string)($resp['payment']['status'] ?? 'COMPLETED'));

    // Optional: success breadcrumb
    error_log('[checkout_process] success env=' . $env . ' paymentId=' . $paymentId . ' status=' . $status);

    // Log payment (best-effort)
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

    header('Location: /success.php?id=' . urlencode($paymentId));
    exit;
  }

  // --- Failure from Square: extract first error code/detail (safe) ---
  $errCode = 'unknown';
  $errDetail = '';
  if (is_array($resp) && isset($resp['errors'][0])) {
    $errCode   = (string)($resp['errors'][0]['code']   ?? $errCode);
    $errDetail = (string)($resp['errors'][0]['detail'] ?? '');
  }

  // Log full failure for server diagnostics, but send a short reason to UI
  error_log('Square payment failed: HTTP ' . $http . ' ' . $raw);
  $short = strtolower(preg_replace('/[^A-Z0-9_]+/i', '_', $errCode)); // e.g., unauthorized → unauthorized
  if ($short === '' || $short === '_') $short = 'payment_failed';
  $fail($short, 'detail=' . $errDetail);

} catch (Throwable $e) {
  error_log('[checkout_process] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
  echo 'Payment processing error.';
  exit;
}
