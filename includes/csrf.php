<?php
if (session_status() === PHP_SESSION_NONE) {
    if (headers_sent($sentFile, $sentLine)) {
        trigger_error(
            sprintf('Unable to start session because headers were sent in %s on line %d.', $sentFile, $sentLine),
            E_USER_WARNING
        );
    } else {
        session_start();
    }
}

function generate_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}
?>
