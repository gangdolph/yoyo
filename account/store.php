<?php
/*
 * Change: Legacy Store Manager entry point now performs a permanent redirect to the canonical Shop Manager dashboard.
 * Deprecated alias; do not remove until all links are updated.
 */
require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/authz.php';

ensure_seller();

header('Location: /shop-manager/index.php', true, 301);
header('Cache-Control: no-store, no-cache, must-revalidate');
exit;
