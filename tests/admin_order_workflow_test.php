<?php
define('YOYO_SKIP_DB_BOOTSTRAP', true);
require __DIR__ . '/../includes/orders.php';

$options = order_fulfillment_status_options();
$expectedKeys = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
foreach ($expectedKeys as $key) {
    if (!array_key_exists($key, $options)) {
        throw new Exception("Missing expected status option: $key");
    }
}

if ($options['shipped'] !== 'Shipped') {
    throw new Exception('Status label for shipped should be human readable.');
}

if (order_admin_compute_inventory_delta(0, true, 'cancelled') !== 1) {
    throw new Exception('Auto restock should add one unit when cancelling.');
}

if (order_admin_compute_inventory_delta(2, false, 'processing') !== 2) {
    throw new Exception('Manual adjustments should pass through when auto restock is disabled.');
}

if (order_admin_compute_inventory_delta(-3, true, 'processing') !== -3) {
    throw new Exception('Auto restock should only apply when cancelling.');
}

if (order_admin_compute_inventory_delta(5, true, 'cancelled', 2) !== 7) {
    throw new Exception('Custom restock multiplier should be applied when provided.');
}

echo "Admin order workflow tests passed\n";
