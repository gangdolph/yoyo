<?php
require_once __DIR__ . '/includes/require-auth.php';
require_once __DIR__ . '/includes/authz.php';

ensure_seller();

$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'listings';
$allowed = ['listings', 'inventory', 'orders', 'shipping', 'settings'];
$targetTab = in_array(strtolower($tab), $allowed, true) ? strtolower($tab) : 'listings';

header('Location: /shop-manager/index.php?tab=' . urlencode($targetTab));
exit;
