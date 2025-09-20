<?php
declare(strict_types=1);

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

    if ($stmt = $conn->prepare('SELECT role, status FROM users WHERE id = ?')) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $stmt->bind_result($roleValue, $statusValue);
            if ($stmt->fetch()) {
                $role = $roleValue ?: 'user';
                $status = $statusValue ?: $status;
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
        return current_user_role() === 'admin';
    }
}

if (!function_exists('is_skuze_official')) {
    function is_skuze_official(): bool
    {
        $role = current_user_role();

        return $role === 'skuze_official' || $role === 'admin';
    }
}
