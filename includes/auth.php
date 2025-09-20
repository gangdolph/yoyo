<?php
declare(strict_types=1);

if (!function_exists('auth_refresh_session_roles')) {
    /**
     * Normalise the role set available to the current session.
     *
     * @param array<string, mixed> $context
     *
     * @return array<int, string>
     */
    function auth_refresh_session_roles(array $context = []): array
    {
        $role = strtolower((string) ($context['role'] ?? ($_SESSION['user_role'] ?? 'user')));
        $status = strtolower((string) ($context['status'] ?? ($_SESSION['status'] ?? '')));
        $vipStatus = $context['vip_status'] ?? ($_SESSION['vip_status'] ?? null);
        $vipExpiresAt = $context['vip_expires_at'] ?? ($_SESSION['vip_expires_at'] ?? null);
        $accountType = strtolower((string) ($context['account_type'] ?? ($_SESSION['account_type'] ?? '')));
        $isAdminFlag = !empty($context['is_admin']) || !empty($_SESSION['is_admin']);

        $roles = ['user'];

        if ($role !== '') {
            $roles[] = $role;
        }

        if ($isAdminFlag) {
            $roles[] = 'admin';
        }

        if ($role === 'admin' || $isAdminFlag) {
            $roles[] = 'skuze_official';
            $roles[] = 'seller';
        }

        if ($role === 'skuze_official') {
            $roles[] = 'skuze_official';
            $roles[] = 'seller';
        }

        if ($accountType === 'business') {
            $roles[] = 'seller';
        }

        $vipActive = false;
        if ($vipStatus !== null) {
            $vipActive = (int) $vipStatus === 1;
            if ($vipActive && $vipExpiresAt) {
                $expiresTs = strtotime((string) $vipExpiresAt);
                if ($expiresTs !== false) {
                    $vipActive = $expiresTs > time();
                }
            }
        }

        if ($vipActive) {
            $roles[] = 'seller';
        }

        if (in_array($status, ['seller', 'vip', 'merchant', 'vendor'], true)) {
            $roles[] = 'seller';
        }

        $roles = array_values(array_unique(array_map('strtolower', $roles)));
        $_SESSION['auth_roles'] = $roles;

        return $roles;
    }
}

if (!defined('AUTH_BOOTSTRAPPED')) {
    define('AUTH_BOOTSTRAPPED', true);

    $sessionStatus = session_status();

    if ($sessionStatus === PHP_SESSION_ACTIVE) {
        // Session is already active, so nothing else to bootstrap here.
    } elseif ($sessionStatus === PHP_SESSION_NONE) {
        if (headers_sent($sentFile, $sentLine)) {
            trigger_error(
                sprintf('Unable to start session because headers were sent in %s on line %d.', $sentFile, $sentLine),
                E_USER_WARNING
            );
            return;
        }

        session_start();
    } else {
        // Sessions are disabled; nothing to do.
        return;
    }

    $db = require __DIR__ . '/db.php';

    if (!isset($_SESSION['user_id'])) {
        // Redirect to the login page at the site root
        header('Location: /login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    auth_sync_user_context($db, $userId);

    // Optional: update last_active for online tracking
    if ($db instanceof mysqli) {
        $db->query('UPDATE users SET last_active = NOW() WHERE id = ' . $userId);
    }
}

/**
 * Ensure the session carries fresh role/status details for the authenticated user.
 */
function auth_sync_user_context(mysqli $conn, int $userId): void
{
    $role = 'user';
    $status = $_SESSION['status'] ?? null;
    $vipStatus = $_SESSION['vip_status'] ?? null;
    $vipExpiresAt = $_SESSION['vip_expires_at'] ?? null;
    $accountType = $_SESSION['account_type'] ?? null;

    $columns = ['role', 'status'];

    if (auth_users_table_has_column($conn, 'vip_status')) {
        $columns[] = 'vip_status';
    }
    if (auth_users_table_has_column($conn, 'vip_expires_at')) {
        $columns[] = 'vip_expires_at';
    }
    if (auth_users_table_has_column($conn, 'account_type')) {
        $columns[] = 'account_type';
    }

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM users WHERE id = ?';

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            if ($result = $stmt->get_result()) {
                if ($row = $result->fetch_assoc()) {
                    if (isset($row['role'])) {
                        $role = $row['role'] ?: 'user';
                    }
                    if (isset($row['status']) && $row['status'] !== null) {
                        $status = $row['status'];
                    }
                    if (array_key_exists('vip_status', $row)) {
                        $vipStatus = $row['vip_status'];
                        $_SESSION['vip_status'] = $vipStatus;
                    }
                    if (array_key_exists('vip_expires_at', $row)) {
                        $vipExpiresAt = $row['vip_expires_at'];
                        $_SESSION['vip_expires_at'] = $vipExpiresAt;
                    }
                    if (array_key_exists('account_type', $row)) {
                        $accountType = $row['account_type'];
                        $_SESSION['account_type'] = $accountType;
                    }
                }
                $result->free();
            }
        }
        $stmt->close();
    }

    $_SESSION['user_role'] = $role;
    if ($status !== null) {
        $_SESSION['status'] = $status;
    }

    // Maintain backwards compatibility for legacy checks until they are replaced.
    $_SESSION['is_admin'] = $role === 'admin' ? 1 : 0;

    auth_refresh_session_roles([
        'role' => $role,
        'status' => $status,
        'vip_status' => $vipStatus,
        'vip_expires_at' => $vipExpiresAt,
        'account_type' => $accountType,
        'is_admin' => $_SESSION['is_admin'] ?? 0,
    ]);
}

/**
 * Determine if the requested column exists on the users table.
 */
function auth_users_table_has_column(mysqli $conn, string $column): bool
{
    static $columnCache = [];

    if (array_key_exists($column, $columnCache)) {
        return $columnCache[$column];
    }

    $escaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM users LIKE '" . $escaped . "'");
    if ($result instanceof mysqli_result) {
        $columnCache[$column] = $result->num_rows > 0;
        $result->free();
    } else {
        $columnCache[$column] = false;
    }

    return $columnCache[$column];
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): string
    {
        return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : 'guest';
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return in_array('admin', auth_current_roles(), true);
    }
}

if (!function_exists('is_skuze_official')) {
    function is_skuze_official(): bool
    {
        return in_array('skuze_official', auth_current_roles(), true);
    }
}

if (!function_exists('auth_current_roles')) {
    /**
     * Return the normalised role set for the active session.
     *
     * @return array<int, string>
     */
    function auth_current_roles(): array
    {
        if (isset($_SESSION['auth_roles']) && is_array($_SESSION['auth_roles'])) {
            return array_values(array_unique(array_map('strtolower', $_SESSION['auth_roles'])));
        }

        return auth_refresh_session_roles();
    }
}
