<?php
/*
 * Square Connection Diagnostics: surface configuration details and live connectivity checks
 * so admins can verify that Square credentials are valid and webhooks are flowing.
 */
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/SquareHttpClient.php';
require_once __DIR__ . '/../includes/square-migrations.php';

/** @var mysqli $conn */
$conn = require __DIR__ . '/../includes/db.php';

if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/../config.php';
}

ensure_admin('../dashboard.php');

square_run_migrations($conn);

$environment = strtolower(trim((string)($config['square_environment'] ?? 'sandbox')));
if ($environment === '') {
    $environment = 'sandbox';
}
$applicationId = trim((string)($config['square_application_id'] ?? ''));
$locationId = trim((string)($config['square_location_id'] ?? ''));
$accessToken = trim((string)($config['square_access_token'] ?? ''));

$maskedToken = 'Not configured';
if ($accessToken !== '') {
    $suffix = substr($accessToken, -4);
    if ($suffix === false) {
        $suffix = '';
    }
    $maskedToken = '****' . $suffix;
}

$squareVersion = SquareHttpClient::getSquareVersion();

$client = null;
$configError = '';
try {
    $client = new SquareHttpClient($config);
} catch (Throwable $e) {
    $configError = $e->getMessage();
}

$stateKey = 'square_core';
$lastWebhookAt = null;
if ($stmt = $conn->prepare('SELECT last_webhook_at FROM square_sync_state WHERE setting_key = ? LIMIT 1')) {
    $stmt->bind_param('s', $stateKey);
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        if ($row = $result->fetch_assoc()) {
            $lastWebhookAt = $row['last_webhook_at'] ?? null;
        }
        $result->free();
    }
    $stmt->close();
}

$lastWebhookEvent = null;
if ($stmt = $conn->prepare('SELECT event_type, received_at FROM square_processed_events ORDER BY received_at DESC LIMIT 1')) {
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        if ($row = $result->fetch_assoc()) {
            $lastWebhookEvent = [
                'event_type' => $row['event_type'] ?? null,
                'received_at' => $row['received_at'] ?? null,
            ];
        }
        $result->free();
    }
    $stmt->close();
}

$successStatuses = ['COMPLETED', 'APPROVED', 'CAPTURED', 'PAID', 'paid'];
$lastPayment = null;
if ($successStatuses) {
    $placeholders = implode(',', array_fill(0, count($successStatuses), '?'));
    $sql = 'SELECT payment_id, status, created_at FROM payments WHERE status IN (' . $placeholders . ') '
        . 'ORDER BY created_at DESC LIMIT 1';
    if ($stmt = $conn->prepare($sql)) {
        $types = str_repeat('s', count($successStatuses));
        $stmt->bind_param($types, ...$successStatuses);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            if ($row = $result->fetch_assoc()) {
                $lastPayment = [
                    'payment_id' => (string)($row['payment_id'] ?? ''),
                    'status' => (string)($row['status'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
}

if ($lastPayment === null) {
    if ($stmt = $conn->prepare('SELECT payment_id, status, created_at FROM payments ORDER BY created_at DESC LIMIT 1')) {
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            if ($row = $result->fetch_assoc()) {
                $lastPayment = [
                    'payment_id' => (string)($row['payment_id'] ?? ''),
                    'status' => (string)($row['status'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
}

$testResult = null;
$testError = '';
$apiErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_square_api'])) {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $testError = 'Invalid session token. Please refresh and try again.';
    } elseif ($client === null) {
        $testError = $configError !== '' ? $configError : 'Square configuration is incomplete.';
    } else {
        try {
            $response = $client->request('GET', '/v2/locations');
            $statusCode = (int)($response['statusCode'] ?? 0);
            $body = $response['body'] ?? null;

            $locations = [];
            if (is_array($body) && isset($body['locations']) && is_array($body['locations'])) {
                $locations = $body['locations'];
            }
            if (is_array($body) && isset($body['errors']) && is_array($body['errors'])) {
                foreach ($body['errors'] as $error) {
                    if (is_array($error)) {
                        $apiErrors[] = [
                            'category' => (string)($error['category'] ?? ''),
                            'code' => (string)($error['code'] ?? ''),
                            'detail' => (string)($error['detail'] ?? ''),
                        ];
                    }
                }
            }

            $firstLocation = null;
            if ($locations) {
                $first = $locations[0];
                if (is_array($first)) {
                    $firstLocation = [
                        'id' => (string)($first['id'] ?? ''),
                        'name' => (string)($first['name'] ?? ''),
                    ];
                }
            }

            $configuredLocationFound = null;
            if ($locationId !== '') {
                $configuredLocationFound = false;
                foreach ($locations as $location) {
                    if (is_array($location) && (string)($location['id'] ?? '') === $locationId) {
                        $configuredLocationFound = true;
                        break;
                    }
                }
            }

            $testResult = [
                'statusCode' => $statusCode,
                'locationCount' => count($locations),
                'firstLocation' => $firstLocation,
                'configuredLocationFound' => $configuredLocationFound,
            ];
        } catch (Throwable $e) {
            $testError = $e->getMessage();
        }
    }
}

$environmentLabel = strtoupper($environment);
$canTest = $client !== null && $accessToken !== '' && $applicationId !== '' && $locationId !== '';

?>
<?php require __DIR__ . '/../includes/layout.php'; ?>
  <title>Square Connection Diagnostics</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <main class="container admin-square-connection">
    <h1>Square Connection Diagnostics</h1>
    <p>Verify your Square credentials, webhook activity, and live API connectivity.</p>

    <?php if ($configError !== ''): ?>
      <div class="alert alert-danger">Configuration warning: <?= htmlspecialchars($configError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="card">
      <h2>Configuration</h2>
      <dl class="definition-list">
        <div class="definition-list__row">
          <dt>Environment</dt>
          <dd><?= htmlspecialchars($environmentLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
        </div>
        <div class="definition-list__row">
          <dt>Application ID</dt>
          <dd><?= $applicationId !== '' ? htmlspecialchars($applicationId, ENT_QUOTES, 'UTF-8') : 'Not configured'; ?></dd>
        </div>
        <div class="definition-list__row">
          <dt>Location ID</dt>
          <dd><?= $locationId !== '' ? htmlspecialchars($locationId, ENT_QUOTES, 'UTF-8') : 'Not configured'; ?></dd>
        </div>
        <div class="definition-list__row">
          <dt>Access Token</dt>
          <dd><?= htmlspecialchars($maskedToken, ENT_QUOTES, 'UTF-8'); ?></dd>
        </div>
        <div class="definition-list__row">
          <dt>Square-Version</dt>
          <dd><?= htmlspecialchars($squareVersion, ENT_QUOTES, 'UTF-8'); ?></dd>
        </div>
        <div class="definition-list__row">
          <dt>Ready for live test?</dt>
          <dd><?= $canTest ? 'Yes' : 'Incomplete — review credentials above.'; ?></dd>
        </div>
      </dl>
    </section>

    <section class="card">
      <h2>Operational Signals</h2>
      <ul>
        <li>
          <strong>Last webhook (sync state):</strong>
          <?= $lastWebhookAt ? htmlspecialchars($lastWebhookAt, ENT_QUOTES, 'UTF-8') : 'No webhook events recorded.'; ?>
        </li>
        <li>
          <strong>Latest processed webhook event:</strong>
          <?php if ($lastWebhookEvent): ?>
            <?= htmlspecialchars($lastWebhookEvent['event_type'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?>
            @ <?= htmlspecialchars($lastWebhookEvent['received_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
          <?php else: ?>
            None recorded.
          <?php endif; ?>
        </li>
        <li>
          <strong>Last successful payment:</strong>
          <?php if ($lastPayment && $lastPayment['payment_id'] !== ''): ?>
            <?= htmlspecialchars($lastPayment['payment_id'], ENT_QUOTES, 'UTF-8'); ?>
            (status <?= htmlspecialchars($lastPayment['status'], ENT_QUOTES, 'UTF-8'); ?>)
            @ <?= htmlspecialchars($lastPayment['created_at'], ENT_QUOTES, 'UTF-8'); ?>
          <?php elseif ($lastPayment): ?>
            Recorded <?= htmlspecialchars($lastPayment['created_at'], ENT_QUOTES, 'UTF-8'); ?>
            with status <?= htmlspecialchars($lastPayment['status'], ENT_QUOTES, 'UTF-8'); ?>
          <?php else: ?>
            No payments stored yet.
          <?php endif; ?>
        </li>
      </ul>
    </section>

    <section class="card">
      <h2>Test API Connectivity</h2>
      <form method="post" class="form-inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" name="test_square_api" value="1" class="btn" <?= $canTest ? '' : 'disabled'; ?>>Test API</button>
        <?php if (!$canTest): ?>
          <span class="form-note">Complete the configuration before running a live test.</span>
        <?php endif; ?>
      </form>

      <?php if ($testResult): ?>
        <div class="alert alert-success">
          Square responded with HTTP <?= (int)$testResult['statusCode']; ?>.
          <?php if ($testResult['firstLocation']): ?>
            First location: <code><?= htmlspecialchars($testResult['firstLocation']['id'], ENT_QUOTES, 'UTF-8'); ?></code>
            (<?= htmlspecialchars($testResult['firstLocation']['name'], ENT_QUOTES, 'UTF-8'); ?>).
          <?php elseif ($testResult['locationCount'] === 0): ?>
            No locations returned for this account.
          <?php endif; ?>
          <?php if ($testResult['configuredLocationFound'] === true): ?>
            Configured location ID matches a Square location.
          <?php elseif ($testResult['configuredLocationFound'] === false): ?>
            Configured location ID was <em>not</em> returned — double-check your settings.
          <?php endif; ?>
        </div>
      <?php elseif ($testError !== ''): ?>
        <div class="alert alert-danger">
          Square API test failed: <?= htmlspecialchars($testError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <?php if ($apiErrors): ?>
        <div class="alert alert-warning">
          <p>Square returned the following errors:</p>
          <ul>
            <?php foreach ($apiErrors as $error): ?>
              <li>
                <?php if ($error['code'] !== ''): ?>
                  <strong><?= htmlspecialchars($error['code'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php endif; ?>
                <?php if ($error['detail'] !== ''): ?>
                  — <?= htmlspecialchars($error['detail'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
                <?php if ($error['category'] !== ''): ?>
                  <span class="badge"><?= htmlspecialchars($error['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
