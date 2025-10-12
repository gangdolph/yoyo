<?php
/*
 * Discovery note: Checkout relied on bespoke cURL calls straight to Square Payments without order scaffolding.
 * Change: Use the shared Square HTTP client with optional order creation behind USE_SQUARE_ORDERS.
 * Change: Added wallet settlement path so buyers can pay via store credit with escrow holds.
 * Fix: Permit wallet-only payments to skip Square token requirements while keeping card flows strict.
 */
declare(strict_types=1);

require __DIR__ . '/_debug_bootstrap.php';

/**
 * /public_html/checkout_process.php
 * Direct HTTPS call to Square Payments API (NO SDK classes).
 * Expects POST:
 *   - token OR source_id  (from Web Payments SDK tokenize())
 *   - listing_id          (int) server computes price
 */

require_once __DIR__ . '/includes/require-auth.php';
$maybeDb = require __DIR__ . '/includes/db.php';  // may return mysqli OR set $conn/$mysqli
$config  = require __DIR__ . '/config.php';
if (!class_exists('InventoryService')) {
    require_once __DIR__ . '/includes/repositories/InventoryService.php';
}
require_once __DIR__ . '/includes/repositories/OrdersService.php';
require_once __DIR__ . '/includes/SquareHttpClient.php';
require_once __DIR__ . '/includes/OrderService.php';
require_once __DIR__ . '/includes/WalletService.php';

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
  $inventoryService = new InventoryService($db);
  $walletService = new WalletService($db);
  $ordersService = new OrdersService($db, $inventoryService, $walletService);
  $buyerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
  $reservationQty = 0;
  $orderQuantity = 1;

  $squareClient = new SquareHttpClient($config);
  $squareOrderService = new OrderService($squareClient);
  $env         = $squareClient->getEnvironment();
  $locationId  = $squareClient->getLocationId();
  $currency    = strtoupper((string)($config['CURRENCY'] ?? 'USD'));
  $useSquareOrders = !empty($config['USE_SQUARE_ORDERS']);

  // Inputs
  $sourceId = '';
  if (isset($_POST['token']) && is_string($_POST['token'])) {
    $sourceId = trim($_POST['token']);
  } elseif (isset($_POST['source_id']) && is_string($_POST['source_id'])) {
    $sourceId = trim($_POST['source_id']);
  }
  $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
  $postedQuantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
  if ($postedQuantity <= 0) {
    $postedQuantity = 1;
  }
  $reservationToken = isset($_POST['reservation_token']) && is_string($_POST['reservation_token'])
    ? trim($_POST['reservation_token'])
    : '';
  $coupon_code = isset($_POST['coupon_code']) && is_string($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
  $walletAllowed = !empty($config['SHOW_WALLET']) && !empty($_POST['wallet_allowed']);
  $paymentMethod = isset($_POST['payment_method']) && is_string($_POST['payment_method'])
    ? strtolower(trim($_POST['payment_method']))
    : 'card';
  $useWallet = $walletAllowed && $paymentMethod === 'wallet';

  // Helper: redirect to cancel with short reason + log
  $fail = function (string $reason, string $logDetail = '') use (&$reservationQty, $inventoryService, $listing_id, $buyerId, $reservationToken) {
    if ($reservationQty > 0) {
      try {
        $inventoryService->releaseListing($listing_id, $reservationQty, $buyerId);
      } catch (Throwable $releaseError) {
        error_log('[checkout_process] release_failed listing_id=' . $listing_id . ' :: ' . $releaseError->getMessage());
      }
      if ($reservationToken !== '' && isset($_SESSION['reservation_tokens'][$reservationToken])) {
        $_SESSION['reservation_tokens'][$reservationToken]['reserved'] = false;
      }
      $reservationQty = 0;
    }
    if ($logDetail !== '') error_log('[checkout_process] ' . $reason . ' :: ' . $logDetail);
    header('Location: /cancel.php?reason=' . urlencode($reason));
    exit;
  };

  if (!$useWallet && $sourceId === '') {
    $fail('missing_token', 'no token/source_id in POST');
  }
  if ($listing_id <= 0) {
    $fail('missing_listing', 'missing or invalid listing_id');
  }

  /* --------------------------- Server-side pricing ----------------------------- */
    $basePrice = null; // decimal dollars
    $salePrice = null;
    $sku = null;
    $stock = null;
    $productOfficial = 0;
    $productLine = 0;
    $listingOfficial = 0;
    $listingQuantity = 1;
    $listingReserved = 0;
    $listingStatus = 'draft';
    $stmt = $db->prepare('SELECT l.price, l.sale_price, l.product_sku, p.stock, p.is_skuze_official, p.is_skuze_product, l.is_official_listing, l.quantity, l.reserved_qty, l.status, l.owner_id FROM listings l LEFT JOIN products p ON l.product_sku = p.sku WHERE l.id = ? LIMIT 1');
    $stmt->bind_param('i', $listing_id);
    $stmt->execute();
    $stmt->bind_result($basePrice, $salePrice, $sku, $stock, $productOfficial, $productLine, $listingOfficial, $listingQuantityRow, $listingReservedRow, $listingStatus, $sellerId);
    if (!$stmt->fetch()) {
      $stmt->close();
      $fail('listing_not_found', 'listing_id=' . $listing_id);
    }
    $stmt->close();

    if ($basePrice === null) {
      $fail('invalid_listing', 'missing base price for listing_id=' . $listing_id);
    }

    $basePrice = (float)$basePrice;
    $salePrice = ($salePrice !== null && $salePrice !== '') ? (float)$salePrice : null;
    $stock = ($stock !== null) ? (int)$stock : null;
    $productOfficial = (int) $productOfficial;
    $productLine = (int) $productLine;
    $listingOfficial = (int) $listingOfficial;
    $listingQuantity = $listingQuantityRow !== null ? (int) $listingQuantityRow : 1;
    $listingReserved = $listingReservedRow !== null ? (int) $listingReservedRow : 0;
    $listingStatus = (string) $listingStatus;
    $sellerId = (int) $sellerId;

    $available = max(0, $listingQuantity - $listingReserved);
    $orderQuantity = max(1, $postedQuantity);
    if (isset($_SESSION['checkout_quantities'][$listing_id])) {
      $orderQuantity = max(1, (int) $_SESSION['checkout_quantities'][$listing_id]);
    }

    $reservationState = null;
    if ($reservationToken !== '' && isset($_SESSION['reservation_tokens'][$reservationToken])
        && is_array($_SESSION['reservation_tokens'][$reservationToken])
        && (int) ($_SESSION['reservation_tokens'][$reservationToken]['listing_id'] ?? 0) === $listing_id) {
      $reservationState = $_SESSION['reservation_tokens'][$reservationToken];
      $tokenQuantity = (int) ($reservationState['quantity'] ?? 0);
      if ($tokenQuantity > 0) {
        $orderQuantity = $tokenQuantity;
      }
    }

    if ($stock !== null && $stock <= 0) {
      $fail('out_of_stock', 'sku=' . (string)$sku);
    }

    if (!in_array($listingStatus, ['approved', 'live'], true)) {
      $fail('listing_not_available', 'listing_status=' . $listingStatus);
    }

    if ($available <= 0) {
      $fail('out_of_stock', 'listing_reservations');
    }

    $_SESSION['checkout_notices'][$listing_id] = '';
    if ($orderQuantity > $available) {
      $orderQuantity = $available;
      $_SESSION['checkout_notices'][$listing_id] = 'Only ' . $available . ' available. Quantity adjusted.';
    }

    if ($orderQuantity <= 0) {
      $fail('out_of_stock', 'quantity_zero');
    }

    $_SESSION['checkout_quantities'][$listing_id] = $orderQuantity;
    if ($reservationToken !== '' && isset($_SESSION['reservation_tokens'][$reservationToken])) {
      $_SESSION['reservation_tokens'][$reservationToken]['quantity'] = $orderQuantity;
    }

    $unitPrice = $salePrice !== null ? $salePrice : $basePrice;
    $unitDiscount = 0.0;
    if ($coupon_code !== '') {
      $stmt = $db->prepare('SELECT discount_type, discount_value FROM coupons WHERE listing_id = ? AND code = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
      $stmt->bind_param('is', $listing_id, $coupon_code);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($c = $res->fetch_assoc()) {
        if ($c['discount_type'] === 'percentage') {
          $unitDiscount = $unitPrice * ((float)$c['discount_value'] / 100);
        } else {
          $unitDiscount = (float)$c['discount_value'];
        }
        if ($unitDiscount > $unitPrice) {
          $unitDiscount = $unitPrice;
        }
      }
      $stmt->close();
    }
    $unitNetPrice = $unitPrice - $unitDiscount;
    if ($unitNetPrice < 0) {
      $unitNetPrice = 0;
    }

    $alreadyReserved = $reservationState
      && !empty($reservationState['reserved'])
      && (int) ($reservationState['quantity'] ?? 0) === $orderQuantity;

    if (!$alreadyReserved) {
      try {
        $inventoryService->reserveListing($listing_id, $orderQuantity, $buyerId);
      } catch (Throwable $reservationError) {
        $fail('out_of_stock', 'reservation_failed listing_id=' . $listing_id);
      }
    }

    if ($reservationToken !== '' && isset($_SESSION['reservation_tokens'][$reservationToken])) {
      $_SESSION['reservation_tokens'][$reservationToken]['reserved'] = true;
      $_SESSION['reservation_tokens'][$reservationToken]['quantity'] = $orderQuantity;
    }
    $reservationQty = $orderQuantity;

  $amount = (int)round(((float)$unitNetPrice) * $orderQuantity * 100); // cents
  if ($amount <= 0) {
    $fail('invalid_amount', 'price=' . var_export($unitNetPrice * $orderQuantity, true));
  }

  // Reconfirm wallet usage after computing amounts (balance checks may disable it below)
  if ($useWallet) {
    try {
      $walletBalance = $walletService->getBalance($buyerId);
      if ((int) $walletBalance['available_cents'] < $amount) {
        $useWallet = false;
      }
    } catch (Throwable $walletCheckError) {
      error_log('[checkout_process] wallet preflight failed: ' . $walletCheckError->getMessage());
      $useWallet = false;
    }
  }

  if ($useWallet) {
    $paymentReference = 'wallet-' . bin2hex(random_bytes(8));
    $paymentDbId = 0;
    try {
      $stmt = $db->prepare('INSERT INTO payments (user_id, listing_id, amount, payment_id, status) VALUES (?,?,?,?,?)');
      if ($stmt === false) {
        throw new RuntimeException('Failed to prepare wallet payment insert.');
      }
      $status = 'WALLET_HELD';
      $stmt->bind_param('iiiss', $buyerId, $listing_id, $amount, $paymentReference, $status);
      if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Failed to log wallet payment.');
      }
      $paymentDbId = (int) $stmt->insert_id;
      $stmt->close();

      $ship = $_SESSION['shipping'][$listing_id] ?? [];
      $isOfficialOrder = ($productOfficial === 1 || $productLine === 1 || $listingOfficial === 1) ? 1 : 0;
      $shippingProfileId = isset($ship['profile_id']) ? (int) $ship['profile_id'] : 0;
      $shipAddress = (string) ($ship['address'] ?? '');
      $shipMethod = (string) ($ship['method'] ?? '');
      $shipNotes = (string) ($ship['notes'] ?? '');
      $shippingSnapshot = json_encode([
        'address' => $shipAddress,
        'delivery_method' => $shipMethod,
        'notes' => $shipNotes,
      ], JSON_UNESCAPED_UNICODE);
      if ($shippingSnapshot === false) {
        $shippingSnapshot = null;
      }

      try {
        $orderResult = $ordersService->createFulfillment(
          $paymentDbId,
          $buyerId,
          $listing_id,
          [
            'address' => $shipAddress,
            'delivery_method' => $shipMethod,
            'notes' => $shipNotes,
            'snapshot' => $shippingSnapshot,
          ],
          [
            'sku' => $sku,
            'is_official_order' => $isOfficialOrder,
            'shipping_profile_id' => $shippingProfileId,
            'quantity' => $orderQuantity,
          ]
        );
        $reservationQty = 0;
      } catch (Throwable $orderError) {
        throw $orderError;
      }

      $walletService->holdForOrder($orderResult['order_id'], $buyerId, $sellerId, $amount, 'wallet-hold-' . $orderResult['order_id']);

      unset($_SESSION['shipping'][$listing_id]);
      unset($_SESSION['checkout_quantities'][$listing_id], $_SESSION['checkout_notices'][$listing_id]);
      if ($reservationToken !== '') {
        unset($_SESSION['reservation_tokens'][$reservationToken]);
      }
      header('Location: /success.php?id=' . urlencode($paymentReference) . '&wallet=1');
      exit;
    } catch (Throwable $walletFlowError) {
      error_log('[checkout_process] wallet settlement failed: ' . $walletFlowError->getMessage());
      if ($reservationQty > 0) {
        try {
          $inventoryService->releaseListing($listing_id, $reservationQty, $buyerId);
        } catch (Throwable $releaseError) {
          error_log('[checkout_process] wallet release_failed listing_id=' . $listing_id . ' :: ' . $releaseError->getMessage());
        }
        $reservationQty = 0;
      }
      $fail('wallet_error', 'wallet_flow_failed');
    }
  }

  /* --------------------- Square REST via shared HTTP client -------------------- */
  $paymentIdempotencyKey = bin2hex(random_bytes(16));
  $payload = [
    'idempotency_key' => $paymentIdempotencyKey,
    'source_id'       => $sourceId,
    'location_id'     => $locationId,
    'amount_money'    => [
      'amount'   => $amount,
      'currency' => $currency,
    ],
    // 'note' => 'Order #' . $listing_id,
    // 'autocomplete' => true,
  ];

  if ($useSquareOrders) {
    try {
      $quantity = $reservationQty > 0 ? $reservationQty : 1;
      $lineItems = [[
        'name' => 'Listing #' . $listing_id,
        'quantity' => (string)$quantity,
        'base_price_money' => [
          'amount' => $amount,
          'currency' => $currency,
        ],
      ]];

      $orderId = $squareOrderService->createOrder($lineItems, [], $locationId);
      $payload['order_id'] = $orderId;
    } catch (Throwable $orderCreationError) {
      square_log('square.order_create_failed', [
        'listing_id' => $listing_id,
        'error' => $orderCreationError->getMessage(),
      ]);
      $fail('order_error', 'order_create_failed');
    }
  }

  try {
    $response = $squareClient->request('POST', '/v2/payments', $payload, $paymentIdempotencyKey);
  } catch (Throwable $paymentRequestError) {
    square_log('square.payment_request_failed', [
      'listing_id' => $listing_id,
      'error' => $paymentRequestError->getMessage(),
    ]);
    $fail('gateway_error', 'square_request_failed');
  }

  $raw  = $response['raw'];
  $http = $response['statusCode'];
  $resp = $response['body'];

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
      $ship = $_SESSION['shipping'][$listing_id] ?? [];
      $isOfficialOrder = ($productOfficial === 1 || $productLine === 1 || $listingOfficial === 1) ? 1 : 0;
      $shippingProfileId = isset($ship['profile_id']) ? (int) $ship['profile_id'] : 0;
      $shipAddress = (string) ($ship['address'] ?? '');
      $shipMethod = (string) ($ship['method'] ?? '');
      $shipNotes = (string) ($ship['notes'] ?? '');
      $shippingSnapshot = json_encode([
        'address' => $shipAddress,
        'delivery_method' => $shipMethod,
        'notes' => $shipNotes,
      ], JSON_UNESCAPED_UNICODE);
      if ($shippingSnapshot === false) {
        $shippingSnapshot = null;
      }

      try {
        $ordersService->createFulfillment(
          $paymentDbId,
          $user_id,
          $listing_id,
          [
            'address' => $shipAddress,
            'delivery_method' => $shipMethod,
            'notes' => $shipNotes,
            'snapshot' => $shippingSnapshot,
          ],
          [
            'sku' => $sku,
            'is_official_order' => $isOfficialOrder,
            'shipping_profile_id' => $shippingProfileId,
            'quantity' => $orderQuantity,
          ]
        );
        $reservationQty = 0;
      } catch (Throwable $orderError) {
        if ($reservationQty > 0) {
          try {
            $inventoryService->releaseListing($listing_id, $reservationQty, $buyerId);
          } catch (Throwable $releaseError) {
            error_log('[checkout_process] release_failed listing_id=' . $listing_id . ' :: ' . $releaseError->getMessage());
          }
          $reservationQty = 0;
        }
        throw $orderError;
      }

      unset($_SESSION['shipping'][$listing_id]);
      unset($_SESSION['checkout_quantities'][$listing_id], $_SESSION['checkout_notices'][$listing_id]);
      if ($reservationToken !== '') {
        unset($_SESSION['reservation_tokens'][$reservationToken]);
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

} catch (SquareConfigException $configError) {
  if (isset($reservationQty) && $reservationQty > 0 && isset($inventoryService, $listing_id, $buyerId)) {
    try {
      $inventoryService->releaseListing($listing_id, $reservationQty, $buyerId);
    } catch (Throwable $releaseError) {
      error_log('[checkout_process] release_failed listing_id=' . $listing_id . ' :: ' . $releaseError->getMessage());
    }
  }
  square_log('square.config_exception', [
    'error' => $configError->getMessage(),
  ]);
  if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
  echo 'Square configuration error.';
  exit;
} catch (Throwable $e) {
  if (isset($reservationQty) && $reservationQty > 0 && isset($inventoryService, $listing_id, $buyerId)) {
    try {
      $inventoryService->releaseListing($listing_id, $reservationQty, $buyerId);
    } catch (Throwable $releaseError) {
      error_log('[checkout_process] release_failed listing_id=' . $listing_id . ' :: ' . $releaseError->getMessage());
    }
  }
  error_log('[checkout_process] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
  echo 'Payment processing error.';
  exit;
}
