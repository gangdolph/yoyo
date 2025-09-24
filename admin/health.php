<?php
/*
 * Discovery note: Admin area lacked a diagnostics view despite handling support, listings, and trades.
 * Change: Added a Square Health stub that surfaces sync/webhook timestamps and recent errors for admins.
 */
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/db.php';
require_once __DIR__ . '/../includes/square-migrations.php';

ensure_admin('../dashboard.php');

square_run_migrations($conn);

$stateKey = 'square_core';
$state = [
    'last_synced_at' => null,
    'last_webhook_at' => null,
    'sync_direction' => 'pull',
];

if ($stmt = $conn->prepare('SELECT last_synced_at, last_webhook_at, sync_direction FROM square_sync_state WHERE setting_key = ? LIMIT 1')) {
    $stmt->bind_param('s', $stateKey);
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        if ($row = $result->fetch_assoc()) {
            $state['last_synced_at'] = $row['last_synced_at'];
            $state['last_webhook_at'] = $row['last_webhook_at'];
            $state['sync_direction'] = $row['sync_direction'] ?: 'pull';
        }
        $result->free();
    }
    $stmt->close();
}

$errors = [];
if ($stmt = $conn->prepare('SELECT error_code, message, context, created_at FROM square_sync_errors ORDER BY created_at DESC LIMIT 5')) {
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        while ($row = $result->fetch_assoc()) {
            $context = $row['context'];
            $decoded = null;
            if (is_string($context) && $context !== '') {
                $decoded = json_decode($context, true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                    $context = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $errors[] = [
                'error_code' => $row['error_code'],
                'message' => $row['message'],
                'context' => $context,
                'created_at' => $row['created_at'],
            ];
        }
        $result->free();
    }
    $stmt->close();
}

$lastSync = $state['last_synced_at'] ? date('Y-m-d H:i:s', strtotime((string) $state['last_synced_at'])) : 'Never';
$lastWebhook = $state['last_webhook_at'] ? date('Y-m-d H:i:s', strtotime((string) $state['last_webhook_at'])) : 'Never';
$direction = $state['sync_direction'] ?: 'pull';
?>
<?php require '../includes/layout.php'; ?>
  <title>Square Health</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <main class="admin-health">
    <h2>Square Integration Health</h2>
    <section class="health-card">
      <h3>Sync Overview</h3>
      <ul>
        <li><strong>Sync Direction:</strong> <?= htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?></li>
        <li><strong>Last Sync:</strong> <?= htmlspecialchars($lastSync, ENT_QUOTES, 'UTF-8'); ?></li>
        <li><strong>Last Webhook:</strong> <?= htmlspecialchars($lastWebhook, ENT_QUOTES, 'UTF-8'); ?></li>
      </ul>
    </section>
    <section class="health-card">
      <h3>Recent Errors</h3>
      <?php if (!$errors): ?>
        <p>No Square errors recorded.</p>
      <?php else: ?>
        <ol class="health-errors">
          <?php foreach ($errors as $error): ?>
            <li>
              <div><strong><?= htmlspecialchars($error['created_at'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
              <?php if (!empty($error['error_code'])): ?>
                <div>Code: <code><?= htmlspecialchars($error['error_code'], ENT_QUOTES, 'UTF-8'); ?></code></div>
              <?php endif; ?>
              <div><?= nl2br(htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8')); ?></div>
              <?php if (!empty($error['context'])): ?>
                <pre><?= htmlspecialchars((string) $error['context'], ENT_QUOTES, 'UTF-8'); ?></pre>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
  </main>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
