<?php

declare(strict_types=1);

if (defined('APP_RUNTIME_BOOTSTRAPPED')) {
    return;
}

define('APP_RUNTIME_BOOTSTRAPPED', true);

$rootDir = dirname(__DIR__);
$debugBootstrap = $rootDir . '/_debug_bootstrap.php';
if (is_file($debugBootstrap)) {
    require_once $debugBootstrap;
}

require_once __DIR__ . '/auth.php';
auth_bootstrap(false);

try {
    if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
        $db = require __DIR__ . '/db.php';
        if ($db instanceof mysqli) {
            $GLOBALS['conn'] = $db;
            if (!isset($conn) || !($conn instanceof mysqli)) {
                /** @var mysqli $db */
                $conn = $db;
            }
        }
    }
} catch (Throwable $bootstrapError) {
    error_log('[bootstrap] Database bootstrap failed: ' . $bootstrapError->getMessage());
}

require_once __DIR__ . '/authz.php';
require_once __DIR__ . '/user.php';
