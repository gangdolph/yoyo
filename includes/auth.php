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
        header("Location: /login.php");
        exit;
    }

    // Optional: update last_active for online tracking
    if ($db instanceof mysqli) {
        $db->query("UPDATE users SET last_active = NOW() WHERE id = " . intval($_SESSION['user_id']));
    }
}
?>
