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
  /* -------------------------------------------------------
   * Resolve mysqli handle robustly ($db)
   * ----------------------------------------------------- */
  $db = null;
  if ($maybeDb instanceof mysqli) {
    $db = $maybeDb;
  } elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
  } elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
  } else {
    // Fallback: construct from config.php
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $host = trim($config['db_host'] ?? '127.0.0.1');
    $port = (int)($config['db_port'] ?? 3306);
    if (strpos($host, ':') !== false) {
      [$h, $p] = explode(':', $host, 2);
      if ($h !== '') $host = $h;
      if (ctype_digit($p ?? '')) $port = (int)$p;
    }
    $db = new mysqli(
      $host,
      $config['db_user'] ?? '',
      $config['db_pass'] ?? '',
      $config['db_name'] ?? '',
      $port
    );
    $db->set_charset('utf8mb4');
    $db->query("SET sql_mode = ''");
  }

  /* -------------------------------------------------------
   * Config and inputs
   * ----------------------------------------------------- */
  $env         = strtolower(trim($config['square_environment'] ?? 'sandbox'));
  $accessToken = trim((string)($config['square_access_token'] ?? ''));
  $locationId  = trim((string)($config['square_location_id'] ?? ''));
  $currency    = strtoupper((string)($config['CURRENCY'] ?? 'USD'));

  $sourceId = '';
  if (isset($_POST['token']) && is_string($_POST['token'])) {
    $sourceId = trim($_POST['token']);
  } elseif (isset($_POST['source_id']) && is_string($_POST['source_id'])) {
    $sourceId = trim($_POST['source_id']);
  }
  $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

  if ($accessToken === '' || $locationId === '') {
    http_response_code(500);
    exit('Square configuration missing (access token or location id).');
  }
  if ($sourceId === '' || $listing_id <= 0) {
    http_response_code(400);
    exit('Invalid payment data.');
  }

  /* -------------------------------------------------------
   * Server-side price lookup (authoritative)
   * ----------------------------------------------------- */
  $price = null; // decimal dollars
  $stmt = $db->prepare('SELECT price FROM listings WHERE id = ? LIMIT 1');
  $stmt->bind_param('i', $listing_id);
  $stmt->execute();
  $stmt->bind_result($price);
  if (!$stmt->fetch()) {
    $stmt->close();
    error_log('[checkout_process] listing not found id=' . $listing_id);
    header('Location: /cancel.php');
    exit;
  }
  $stmt->close();

  $amount = (int)round(((float)$price) * 100); // cents
  if ($amount <= 0) {
    error_log('[checkout_process] invalid amount computed=' . $amount . ' from price=' . $price);
    header('Location: /cancel.php');
    exit;
  }

  /* -------------------------------------------------------
   * Square REST call via cURL (no SDK)
   * ----------------------------------------------------- */
  $base = ($env === 'production')
    ? 'https://connect.squareup.com'
    : 'https://connect.squareupsandbox.com';

  $payload = [
    'idempotency_key' => bin2hex(random_bytes(16)),
    'source_id'       => $sourceId,
    'location_id'     => $locationId,
    'amount_money'    => [
      'amount'   => $amount,
      'currency' => $currency,
    ],
    // 'note' => 'Order #' . $listing_id,
    // 'autocomplete' => true,
  ];

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

  $resp      = json_decode($raw, true);
  $status    = 'FAILED';
  $paymentId = null;

  if ($http >= 200 && $http < 300 && isset($resp['payment']['id'])) {
    $paymentId = (string)$resp['payment']['id'];
    $status    = strtoupper((string)($resp['payment']['status'] ?? 'COMPLETED'));
  } else {
    if (isset($resp['errors'])) {
      error_log('Square payment errors: ' . json_encode($resp['errors'], JSON_UNESCAPED_SLASHES));
    }
    error_log('Square payment failed: HTTP ' . $http . ' ' . $raw);
  }

  /* -------------------------------------------------------
   * Log payment (best-effort)
   * ----------------------------------------------------- */
  try {
    if (!isset($user_id)) {
      $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }
    if ($stmt = $db->prepare('INSERT INTO payments (user_id, listing_id, amount, payment_id, status) VALUES (?,?,?,?,?)')) {
      $stmt->bind_param('iiiss', $user_id, $listing_id, $amount, $paymentId, $status);
      $stmt->execute();
      $stmt->close();
    }
  } catch (\Throwable $e) {
    error_log('Payment log insert failed: ' . $e->getMessage());
  }

  /* -------------------------------------------------------
   * Redirect by outcome
   * ----------------------------------------------------- */
  if ($status === 'COMPLETED' || $status === 'APPROVED' || $status === 'AUTHORIZED') {
    header('Location: /success.php');
    exit;
  }

  header('Location: /cancel.php');
  exit;

} catch (Throwable $e) {
  error_log('[checkout_process] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
  echo 'Payment processing error.';
  exit;
}
