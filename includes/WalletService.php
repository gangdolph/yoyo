<?php
/**
 * WalletService centralises double-entry wallet accounting, holds, and refunds.
 * Added for marketplace escrow so buyers and sellers share a consistent ledger.
 * Maintenance: Hardened idempotent reads and null-safe ledger writes after wallet rollout QA.
 */
declare(strict_types=1);

require_once __DIR__ . '/PayoutProvider.php';

final class WalletService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Return the available and pending balances for the requested user.
     *
     * @return array{available_cents: int, pending_cents: int}
     */
    public function getBalance(int $userId): array
    {
        $stmt = $this->conn->prepare('SELECT available_cents, pending_cents FROM wallet_accounts WHERE user_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet lookup.');
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute wallet lookup.');
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc() ?: ['available_cents' => 0, 'pending_cents' => 0];
        $stmt->close();

        return [
            'available_cents' => (int) $row['available_cents'],
            'pending_cents' => (int) $row['pending_cents'],
        ];
    }

    /**
     * Top up a user's wallet from an external payment source.
     */
    public function topUp(
        int $userId,
        int $amountCents,
        string $idempotencyKey,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $meta = []
    ): array {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Top up amount must be positive.');
        }

        $ledgerKey = $this->scopedKey($idempotencyKey, $userId, 'topup');
        if ($this->fetchLedgerByKey($ledgerKey)) {
            return $this->getBalance($userId);
        }

        $this->conn->begin_transaction();

        try {
            $account = $this->lockAccount($userId);
            $available = $account['available_cents'] + $amountCents;
            $this->updateAccount($userId, $available, $account['pending_cents']);

            $this->insertLedger(
                $userId,
                'top_up',
                $amountCents,
                '+',
                $available,
                $ledgerKey,
                $relatedType,
                $relatedId,
                $meta + ['pending_after' => $account['pending_cents']]
            );

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $this->getBalance($userId);
    }

    /**
     * Debit removes funds from the available balance, primarily for adjustments.
     */
    public function debit(
        int $userId,
        int $amountCents,
        string $idempotencyKey,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $meta = [],
        string $entryType = 'debit'
    ): array {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        $ledgerKey = $this->scopedKey($idempotencyKey, $userId, $entryType . '-debit');
        if ($this->fetchLedgerByKey($ledgerKey)) {
            return $this->getBalance($userId);
        }

        $this->conn->begin_transaction();

        try {
            $account = $this->lockAccount($userId);
            if ($account['available_cents'] < $amountCents) {
                throw new RuntimeException('Insufficient wallet balance for debit.');
            }

            $available = $account['available_cents'] - $amountCents;
            $this->updateAccount($userId, $available, $account['pending_cents']);

            $this->insertLedger(
                $userId,
                $entryType,
                $amountCents,
                '-',
                $available,
                $ledgerKey,
                $relatedType,
                $relatedId,
                $meta + ['pending_after' => $account['pending_cents']]
            );

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $this->getBalance($userId);
    }

    /**
     * Credit adds funds to the available balance. Used for payouts and refunds.
     */
    public function credit(
        int $userId,
        int $amountCents,
        string $idempotencyKey,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $meta = [],
        string $entryType = 'credit'
    ): array {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        $ledgerKey = $this->scopedKey($idempotencyKey, $userId, $entryType . '-credit');
        if ($this->fetchLedgerByKey($ledgerKey)) {
            return $this->getBalance($userId);
        }

        $this->conn->begin_transaction();

        try {
            $account = $this->lockAccount($userId);
            $available = $account['available_cents'] + $amountCents;
            $this->updateAccount($userId, $available, $account['pending_cents']);

            $this->insertLedger(
                $userId,
                $entryType,
                $amountCents,
                '+',
                $available,
                $ledgerKey,
                $relatedType,
                $relatedId,
                $meta + ['pending_after' => $account['pending_cents']]
            );

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $this->getBalance($userId);
    }

    /**
     * Record a withdrawal request while debiting the user's available balance.
     *
     * @return array<string, mixed>
     */
    public function requestWithdrawal(int $userId, int $amountCents, int $feeCents, string $idempotencyKey): array
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be positive.');
        }

        if ($feeCents < 0) {
            throw new InvalidArgumentException('Withdrawal fee cannot be negative.');
        }

        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Withdrawal idempotency key is required.');
        }

        if (strlen($idempotencyKey) > 64) {
            throw new InvalidArgumentException('Withdrawal idempotency key is too long.');
        }

        $totalDebit = $amountCents + $feeCents;
        if ($totalDebit <= 0) {
            throw new InvalidArgumentException('Withdrawal total must be positive.');
        }

        $existing = $this->fetchWithdrawalByKey($idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        $this->conn->begin_transaction();

        try {
            $existingLocked = $this->fetchWithdrawalByKeyForUpdate($idempotencyKey);
            if ($existingLocked !== null) {
                $this->conn->commit();
                return $existingLocked;
            }

            $account = $this->lockAccount($userId);
            if ($account['available_cents'] < $totalDebit) {
                throw new RuntimeException('Insufficient wallet balance for withdrawal.');
            }

            $insert = $this->conn->prepare(
                'INSERT INTO wallet_withdrawals (user_id, amount_cents, fee_cents, idempotency_key) VALUES (?, ?, ?, ?)'
            );
            if ($insert === false) {
                throw new RuntimeException('Failed to prepare wallet withdrawal insert.');
            }

            $insert->bind_param('iiis', $userId, $amountCents, $feeCents, $idempotencyKey);
            if (!$insert->execute()) {
                $error = $insert->error;
                $insert->close();
                throw new RuntimeException('Failed to insert wallet withdrawal: ' . $error);
            }
            $insert->close();

            $withdrawalId = (int) $this->conn->insert_id;

            $available = $account['available_cents'] - $totalDebit;
            $this->updateAccount($userId, $available, $account['pending_cents']);

            $ledgerMeta = [
                'pending_after' => $account['pending_cents'],
                'amount_cents' => $amountCents,
                'fee_cents' => $feeCents,
                'withdrawal_id' => $withdrawalId,
            ];

            $this->insertLedger(
                $userId,
                'withdrawal_request',
                $totalDebit,
                '-',
                $available,
                $this->scopedKey($idempotencyKey, $userId, 'withdraw'),
                'wallet_withdrawal',
                $withdrawalId,
                $ledgerMeta
            );

            $this->conn->commit();

            return $this->fetchWithdrawalById($withdrawalId) ?? [
                'id' => $withdrawalId,
                'user_id' => $userId,
                'amount_cents' => $amountCents,
                'fee_cents' => $feeCents,
                'status' => 'pending',
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Place funds from a buyer into escrow for an order and reflect the seller's pending earnings.
     */
    public function holdForOrder(
        int $orderId,
        int $buyerId,
        int $sellerId,
        int $amountCents,
        string $idempotencyKey,
        array $meta = []
    ): array {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Hold amount must be positive.');
        }

        $buyerKey = $this->scopedKey($idempotencyKey, $buyerId, 'hold-buyer');
        if ($this->fetchLedgerByKey($buyerKey)) {
            return $this->fetchHold($orderId) ?? [];
        }

        $this->conn->begin_transaction();

        try {
            $existingHold = $this->fetchHoldForUpdate($orderId);
            if ($existingHold !== null) {
                if ($existingHold['status'] === 'held') {
                    $this->conn->commit();
                    return $existingHold;
                }
                throw new RuntimeException('Wallet hold already processed for order.');
            }

            $buyerAccount = $this->lockAccount($buyerId);
            if ($buyerAccount['available_cents'] < $amountCents) {
                throw new RuntimeException('Insufficient wallet balance for hold.');
            }

            $buyerAvailable = $buyerAccount['available_cents'] - $amountCents;
            $buyerPending = $buyerAccount['pending_cents'] + $amountCents;
            $this->updateAccount($buyerId, $buyerAvailable, $buyerPending);

            $this->insertLedger(
                $buyerId,
                'hold',
                $amountCents,
                '-',
                $buyerAvailable,
                $buyerKey,
                'order',
                $orderId,
                $meta + ['pending_after' => $buyerPending]
            );

            $sellerAccount = $this->lockAccount($sellerId);
            $sellerPending = $sellerAccount['pending_cents'] + $amountCents;
            $this->updateAccount($sellerId, $sellerAccount['available_cents'], $sellerPending);

            $this->insertLedger(
                $sellerId,
                'hold',
                $amountCents,
                '+',
                $sellerAccount['available_cents'],
                $this->scopedKey($idempotencyKey, $sellerId, 'hold-seller'),
                'order',
                $orderId,
                $meta + ['pending_after' => $sellerPending]
            );

            $insert = $this->conn->prepare('INSERT INTO wallet_holds (order_id, buyer_id, seller_id, amount_cents, status) VALUES (?, ?, ?, ?, "held")');
            if ($insert === false) {
                throw new RuntimeException('Failed to prepare wallet hold insert.');
            }
            $insert->bind_param('iiii', $orderId, $buyerId, $sellerId, $amountCents);
            if (!$insert->execute()) {
                $insert->close();
                throw new RuntimeException('Failed to insert wallet hold.');
            }
            $insert->close();

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $this->fetchHold($orderId) ?? [];
    }

    /**
     * Resolve an order hold. When $creditSeller is true the seller receives the funds; otherwise the buyer is refunded.
     */
    public function releaseHold(int $orderId, string $idempotencyKey, bool $creditSeller = true): array
    {
        $this->conn->begin_transaction();

        try {
            $hold = $this->fetchHoldForUpdate($orderId);
            if ($hold === null) {
                throw new RuntimeException('No wallet hold exists for order.');
            }

            if ($hold['status'] !== 'held') {
                $this->conn->commit();
                return $hold;
            }

            $amount = (int) $hold['amount_cents'];
            $buyerId = (int) $hold['buyer_id'];
            $sellerId = (int) $hold['seller_id'];

            $buyerAccount = $this->lockAccount($buyerId);
            $buyerPending = max(0, $buyerAccount['pending_cents'] - $amount);
            $buyerAvailable = $buyerAccount['available_cents'];
            if (!$creditSeller) {
                $buyerAvailable += $amount;
            }
            $this->updateAccount($buyerId, $buyerAvailable, $buyerPending);

            $this->insertLedger(
                $buyerId,
                'release',
                $amount,
                '-',
                $buyerAvailable,
                $this->scopedKey($idempotencyKey, $buyerId, 'release'),
                'order',
                $orderId,
                ['pending_after' => $buyerPending]
            );

            if (!$creditSeller) {
                $this->insertLedger(
                    $buyerId,
                    'refund',
                    $amount,
                    '+',
                    $buyerAvailable,
                    $this->scopedKey($idempotencyKey, $buyerId, 'refund'),
                    'order',
                    $orderId,
                    ['pending_after' => $buyerPending]
                );
            } else {
                $sellerAccount = $this->lockAccount($sellerId);
                $sellerPending = max(0, $sellerAccount['pending_cents'] - $amount);
                $sellerAvailable = $sellerAccount['available_cents'] + $amount;
                $this->updateAccount($sellerId, $sellerAvailable, $sellerPending);

                $this->insertLedger(
                    $sellerId,
                    'release',
                    $amount,
                    '-',
                    $sellerAvailable,
                    $this->scopedKey($idempotencyKey, $sellerId, 'release'),
                    'order',
                    $orderId,
                    ['pending_after' => $sellerPending]
                );

                $this->insertLedger(
                    $sellerId,
                    'credit',
                    $amount,
                    '+',
                    $sellerAvailable,
                    $this->scopedKey($idempotencyKey, $sellerId, 'credit'),
                    'order',
                    $orderId,
                    ['pending_after' => $sellerPending]
                );
            }

            $status = $creditSeller ? 'released' : 'cancelled';
            $update = $this->conn->prepare('UPDATE wallet_holds SET status = ?, released_at = NOW() WHERE order_id = ?');
            if ($update === false) {
                throw new RuntimeException('Failed to prepare wallet hold update.');
            }
            $update->bind_param('si', $status, $orderId);
            if (!$update->execute()) {
                $update->close();
                throw new RuntimeException('Failed to update wallet hold.');
            }
            $update->close();

            $this->conn->commit();

            return $this->fetchHold($orderId) ?? [];
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Explicit refund helper for administrative flows.
     */
    public function refundToWallet(
        int $userId,
        int $amountCents,
        string $idempotencyKey,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $meta = []
    ): array {
        return $this->credit($userId, $amountCents, $idempotencyKey, $relatedType, $relatedId, $meta, 'refund');
    }

    /**
     * Dispatch a withdrawal through an external payout provider.
     *
     * @param array<string, mixed> $payload
     * @return array{status:string, reference?:string, message?:string}
     */
    public function payoutWithProvider(PayoutProvider $provider, int $withdrawalId, array $payload = []): array
    {
        $payload['withdrawal_id'] = $withdrawalId;
        $result = $provider->payout($payload);
        $status = $result['status'] ?? 'processing';
        $reference = $result['reference'] ?? null;

        if ($this->tableExists('withdrawal_requests')) {
            $stmt = $this->conn->prepare('UPDATE withdrawal_requests SET status = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt !== false) {
                $stmt->bind_param('si', $status, $withdrawalId);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($this->tableExists('wallet_withdrawals')) {
            $stmt = $this->conn->prepare('UPDATE wallet_withdrawals SET status = ?, reference = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt !== false) {
                $stmt->bind_param('ssi', $status, $reference, $withdrawalId);
                $stmt->execute();
                $stmt->close();
            }
        }

        return $result;
    }

    /**
     * Record an audit entry for privileged actions.
     */
    public function logAudit(int $actorUserId, string $action, array $details = []): void
    {
        $stmt = $this->conn->prepare('INSERT INTO wallet_audit_log (actor_user_id, action, details) VALUES (?, ?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare audit log insert.');
        }

        $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($detailsJson === false) {
            $detailsJson = null;
        }

        $stmt->bind_param('iss', $actorUserId, $action, $detailsJson);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to insert audit log.');
        }
        $stmt->close();
    }

    /**
     * Obtain a wallet account row under row lock.
     *
     * @return array{available_cents: int, pending_cents: int}
     */
    private function lockAccount(int $userId): array
    {
        $this->ensureAccountExists($userId);

        $stmt = $this->conn->prepare('SELECT available_cents, pending_cents FROM wallet_accounts WHERE user_id = ? FOR UPDATE');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet account lock.');
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to lock wallet account.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('Wallet account missing after ensure.');
        }

        return [
            'available_cents' => (int) $row['available_cents'],
            'pending_cents' => (int) $row['pending_cents'],
        ];
    }

    /**
     * Insert or confirm a wallet account row exists.
     */
    private function ensureAccountExists(int $userId): void
    {
        $insert = $this->conn->prepare('INSERT IGNORE INTO wallet_accounts (user_id, available_cents, pending_cents) VALUES (?, 0, 0)');
        if ($insert === false) {
            throw new RuntimeException('Failed to prepare wallet account ensure.');
        }
        $insert->bind_param('i', $userId);
        $insert->execute();
        $insert->close();
    }

    /**
     * Update the wallet account balances.
     */
    private function updateAccount(int $userId, int $available, int $pending): void
    {
        $stmt = $this->conn->prepare('UPDATE wallet_accounts SET available_cents = ?, pending_cents = ? WHERE user_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet account update.');
        }

        $stmt->bind_param('iii', $available, $pending, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to update wallet account.');
        }
        $stmt->close();
    }

    /**
     * Persist a ledger entry.
     */
    private function insertLedger(
        int $userId,
        string $entryType,
        int $amountCents,
        string $sign,
        int $balanceAfter,
        string $idempotencyKey,
        ?string $relatedType,
        ?int $relatedId,
        array $meta
    ): void {
        $stmt = $this->conn->prepare(
            'INSERT INTO wallet_ledger (user_id, entry_type, amount_cents, sign, balance_after_cents, related_type, related_id, idempotency_key, meta) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet ledger insert.');
        }

        $metaJson = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($metaJson === false) {
            $metaJson = null;
        }

        $relatedTypeValue = $relatedType;
        $relatedIdValue = $relatedId ?? 0;
        $stmt->bind_param(
            'isisisiss',
            $userId,
            $entryType,
            $amountCents,
            $sign,
            $balanceAfter,
            $relatedTypeValue,
            $relatedIdValue,
            $idempotencyKey,
            $metaJson
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Failed to insert wallet ledger: ' . $error);
        }

        $stmt->close();
    }

    /**
     * Fetch an existing ledger entry by idempotency key.
     *
     * @return array|null
     */
    private function fetchLedgerByKey(string $idempotencyKey): ?array
    {
        $stmt = $this->conn->prepare('SELECT user_id, entry_type, amount_cents, sign, balance_after_cents, related_type, related_id, meta FROM wallet_ledger WHERE idempotency_key = ? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare ledger lookup.');
        }

        $stmt->bind_param('s', $idempotencyKey);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute ledger lookup.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $row : null;
    }

    /**
     * Fetch and lock a wallet hold row.
     */
    private function fetchHoldForUpdate(int $orderId): ?array
    {
        $stmt = $this->conn->prepare('SELECT order_id, buyer_id, seller_id, amount_cents, status, created_at, released_at FROM wallet_holds WHERE order_id = ? FOR UPDATE');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet hold lookup.');
        }

        $stmt->bind_param('i', $orderId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute wallet hold lookup.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Fetch a wallet hold without locking.
     */
    private function fetchHold(int $orderId): ?array
    {
        $stmt = $this->conn->prepare('SELECT order_id, buyer_id, seller_id, amount_cents, status, created_at, released_at FROM wallet_holds WHERE order_id = ? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet hold fetch.');
        }

        $stmt->bind_param('i', $orderId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute wallet hold fetch.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $escaped = $this->conn->real_escape_string($table);
        $query = sprintf("SHOW TABLES LIKE '%s'", $escaped);
        $result = $this->conn->query($query);
        $exists = $result !== false && $result->num_rows > 0;
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $cache[$table] = $exists;

        return $exists;
    }

    /**
     * Fetch a withdrawal by idempotency key without locking.
     *
     * @return array<string, mixed>|null
     */
    private function fetchWithdrawalByKey(string $idempotencyKey): ?array
    {
        if ($idempotencyKey === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT id, user_id, amount_cents, fee_cents, status, created_at, updated_at FROM wallet_withdrawals WHERE idempotency_key = ? LIMIT 1'
        );
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet withdrawal lookup.');
        }

        $stmt->bind_param('s', $idempotencyKey);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute wallet withdrawal lookup.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Fetch and lock a withdrawal by idempotency key.
     *
     * @return array<string, mixed>|null
     */
    private function fetchWithdrawalByKeyForUpdate(string $idempotencyKey): ?array
    {
        if ($idempotencyKey === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT id, user_id, amount_cents, fee_cents, status, created_at, updated_at FROM wallet_withdrawals WHERE idempotency_key = ? FOR UPDATE'
        );
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet withdrawal lock.');
        }

        $stmt->bind_param('s', $idempotencyKey);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to lock wallet withdrawal.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Fetch a withdrawal by identifier.
     *
     * @return array<string, mixed>|null
     */
    private function fetchWithdrawalById(int $withdrawalId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, user_id, amount_cents, fee_cents, status, created_at, updated_at FROM wallet_withdrawals WHERE id = ? LIMIT 1'
        );
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare wallet withdrawal fetch.');
        }

        $stmt->bind_param('i', $withdrawalId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute wallet withdrawal fetch.');
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Create a namespaced idempotency key that satisfies the schema constraint.
     */
    private function scopedKey(string $baseKey, int $userId, string $suffix): string
    {
        $raw = $baseKey . '|' . $userId . '|' . $suffix;
        if (strlen($raw) <= 64) {
            return $raw;
        }

        return substr(hash('sha256', $raw), 0, 64);
    }
}

