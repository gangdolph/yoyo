<?php
$walletsActive = $activeTab === 'wallets';
$walletPanelId = 'shop-manager-panel-wallets';
$walletTabId = 'shop-manager-tab-wallets';
$queue = $walletRequests ?? [];
?>
<section
  id="<?= htmlspecialchars($walletPanelId, ENT_QUOTES, 'UTF-8'); ?>"
  class="store__panel<?= $walletsActive ? ' is-active' : ''; ?>"
  role="tabpanel"
  aria-labelledby="<?= htmlspecialchars($walletTabId, ENT_QUOTES, 'UTF-8'); ?>"
  data-manager-panel="wallets"
  <?= $walletsActive ? '' : 'hidden'; ?>
>
  <header class="manager-panel__header">
    <div>
      <h3 class="manager-panel__title">Wallet Withdrawals</h3>
      <p class="manager-panel__description">Review pending withdrawal requests and coordinate payouts.</p>
    </div>
  </header>

  <?php if (!feature_wallets_enabled()): ?>
    <p class="notice">Wallets are disabled. Enable the FEATURE_WALLETS flag to use this queue.</p>
    <?php return; ?>
  <?php endif; ?>

  <?php if (empty($queue)): ?>
    <p class="notice">No withdrawal requests found.</p>
  <?php else: ?>
    <div class="table-wrapper">
      <table class="store-table">
        <thead>
          <tr>
            <th scope="col">User</th>
            <th scope="col">Amount</th>
            <th scope="col">Status</th>
            <th scope="col">Provider</th>
            <th scope="col">Requested</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($queue as $row): ?>
            <?php
              $amount = isset($row['amount_cents']) ? ((int) $row['amount_cents']) / 100 : 0;
              $provider = $row['provider'] ?? ($row['reference'] ?? 'manual');
              $createdAt = $row['created_at'] ?? '—';
              $username = $row['username'] ?? ('User #' . ((int) ($row['user_id'] ?? 0)));
              $status = strtoupper((string) ($row['status'] ?? 'unknown'));
            ?>
            <tr>
              <td><?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8'); ?></td>
              <td>$<?= number_format($amount, 2); ?></td>
              <td><span class="badge"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?= htmlspecialchars((string) $provider, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars((string) $createdAt, ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="hint">Use the payout provider integration in WalletService to mark requests as paid when funds are released.</p>
</section>
