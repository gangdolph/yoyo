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

require __DIR__ . '/includes/auth.php';
$maybeDb = require __DIR__ . '/includes/db.php';  // may return mysqli OR set $conn/$mysqli
$config  = require __DIR__ . '/config.php';

try {
  /* --------------------------- Resolve mysqli handle --------------------------- */
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

  /* ------------------------------ Config/inputs -------------------------------- */
  $env         = strtolower(trim($config['square_environment'] ?? 'sandbox'));
  $accessToken = trim((string)($config['square_access_token'] ?? ''));
  $locationId  = trim((string)($config['square_location_id'] ?? ''));
  $currency    = strtoupper((string)($config['CURRENCY'] ?? 'USD'));

  // Inputs
  $sourceId = '';
  if (isset($_POST['token']) && is_string($_POST['token'])) {
    $sourceId = trim($_POST['token']);
  } elseif (isset($_POST['source_id']) && is_string($_POST['source_id'])) {
    $sourceId = trim($_POST['source_id']);
  }
  $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
  $coupon_code = isset($_POST['coupon_code']) && is_string($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';

  // Helper: redirect to cancel with short reason + log
  $fail = function (string $reason, string $logDetail = '') {
    if ($logDetail !== '') error_log('[checkout_process] ' . $reason . ' :: ' . $logDetail);
    header('Location: /cancel.php?reason=' . urlencode($reason));
    exit;
  };

  if ($accessToken === '' || $locationId === '') {
    $fail('config_error', 'missing access token or location id');
  }
  if ($sourceId === '') {
    $fail('missing_token', 'no token/source_id in POST');
  }
  if ($listing_id <= 0) {
    $fail('missing_listing', 'missing or invalid listing_id');
  }

  /* --------------------------- Server-side pricing ----------------------------- */
    $price = null; // decimal dollars
    $salePrice = null;
    $sku = null;
    $stock = 0;
    $stmt = $db->prepare('SELECT l.price, l.sale_price, l.product_sku, p.quantity FROM listings l JOIN products p ON l.product_sku = p.sku WHERE l.id = ? LIMIT 1');
    $stmt->bind_param('i', $listing_id);
    $stmt->execute();
    $stmt->bind_result($price, $salePrice, $sku, $stock);
    if (!$stmt->fetch()) {
      $stmt->close();
      $fail('listing_not_found', 'listing_id=' . $listing_id);
    }
    $stmt->close();
    $price = ($salePrice !== null) ? $salePrice : $price;
    $discount = 0.0;
    if ($coupon_code !== '') {
      $stmt = $db->prepare('SELECT discount_type, discount_value FROM coupons WHERE listing_id = ? AND code = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
      $stmt->bind_param('is', $listing_id, $coupon_code);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($c = $res->fetch_assoc()) {
        if ($c['discount_type'] === 'percentage') {
          $discount = $price * ((float)$c['discount_value'] / 100);
        } else {
          $discount = (float)$c['discount_value'];
        }
        if ($discount > $price) {
          $discount = $price;
        }
      }
      $stmt->close();
    }
    $price -= $discount;
    if ($price < 0) {
      $price = 0;
    }
    if ($stock <= 0) {
      $fail('out_of_stock', 'sku=' . $sku);
    }

  $amount = (int)round(((float)$price) * 100); // cents
  if ($amount <= 0) {
    $fail('invalid_amount', 'price=' . var_export($price, true));
  }

  /* --------------------------- Square REST via cURL ---------------------------- */
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
    $fail('gateway_error', 'curl_errno=' . $err);
  }

  $resp      = json_decode($raw, true);
  $status    = 'FAILED';
  $paymentId = null;

  if ($http >= 200 && $http < 300 && isset($resp['payment']['id'])) {
    $paymentId = (string)$resp['payment']['id'];
    $status    = strtoupper((string)($resp['payment']['status'] ?? 'COMPLETED'));

    // Log breadcrumb
    error_log('[checkout_process] success env=' . $env . ' paymentId=' . $paymentId . ' status=' . $status);

    // Log payment (best-effort)
    try {
      if (!isset($user_id)) {
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
      }
      if ($stmt = $db->prepare('INSERT INTO payments (user_id, listing_id, amount, payment_id, status) VALUES (?,?,?,?,?)')) {
        $stmt->bind_param('iiiss', $user_id, $listing_id, $amount, $paymentId, $status);
        $stmt->execute();
        $paymentDbId = $db->insert_id;
        $stmt->close();
      }
      if (isset($_SESSION['shipping'][$listing_id])) {
        $ship = $_SESSION['shipping'][$listing_id];
        $tracking = null;
        $orderStatus = 'pending';
          if ($stmt = $db->prepare('INSERT INTO order_fulfillments (payment_id, user_id, listing_id, sku, shipping_address, delivery_method, notes, tracking_number, status) VALUES (?,?,?,?,?,?,?,?,?)')) {
            $stmt->bind_param('iiissssss', $paymentDbId, $user_id, $listing_id, $sku, $ship['address'], $ship['method'], $ship['notes'], $tracking, $orderStatus);
            $stmt->execute();
            $stmt->close();
          }
          if ($stmt = $db->prepare('UPDATE products SET quantity = quantity - 1 WHERE sku = ? AND quantity > 0')) {
            $stmt->bind_param('s', $sku);
            $stmt->execute();
            $stmt->close();
          }
        unset($_SESSION['shipping'][$listing_id]);
      }
    } catch (\Throwable $e) {
      error_log('Payment log insert failed: ' . $e->getMessage());
    }

    header('Location: /success.php?id=' . urlencode($paymentId));
    exit;
  }

  // Failure from Square: map first error code to a short reason
  $errCode = 'payment_failed';
  $errDetail = '';
  if (is_array($resp) && isset($resp['errors'][0])) {
    $errCode   = (string)($resp['errors'][0]['code']   ?? $errCode);
    $errDetail = (string)($resp['errors'][0]['detail'] ?? '');
  }
  error_log('Square payment failed: HTTP ' . $http . ' ' . $raw);
  $short = strtolower(preg_replace('/[^A-Z0-9_]+/i', '_', $errCode));
  if ($short === '' || $short === '_') $short = 'payment_failed';
  $fail($short, 'detail=' . $errDetail);

} catch (Throwable $e) {
  error_log('[checkout_process] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
  echo 'Payment processing error.';
  exit;
}
