<?php
define('YOYO_SKIP_DB_BOOTSTRAP', true);
require __DIR__ . '/../includes/orders.php';

$sql = _orders_build_select_sql('direction');
if (strpos($sql, 'LEFT JOIN products') === false) {
    throw new Exception('Orders query must left join products to allow missing inventory records.');
}

$row = [
    'id' => 42,
    'shipping_status' => 'pending',
    'tracking_number' => null,
    'delivery_method' => 'mail',
    'notes' => null,
    'placed_at' => '2024-01-01 00:00:00',
    'fulfillment_user_id' => 7,
    'payment_id' => 55,
    'payment_status' => 'COMPLETED',
    'payment_amount' => 1299,
    'payment_reference' => 'PAYMENT_REF',
    'payment_created_at' => '2024-01-01 00:01:00',
    'payment_user_id' => 18,
    'listing_id' => 9,
    'listing_title' => 'Standalone Listing',
    'listing_owner_id' => 12,
    'listing_price' => '12.99',
    'listing_status' => 'active',
    'product_sku' => null,
    'product_title' => null,
    'product_quantity' => null,
    'product_reorder_threshold' => null,
    'product_is_official' => null,
    'seller_username' => 'sellerUser',
    'buyer_username' => 'buyerUser',
    'direction' => 'buy',
];

$order = _orders_normalize_row($row);
if ($order['listing']['title'] !== 'Standalone Listing') {
    throw new Exception('Listing title should remain available even without a product record.');
}
if ($order['product']['sku'] !== null) {
    throw new Exception('Product SKU should remain null when no product record is available.');
}
if ($order['product']['quantity'] !== null) {
    throw new Exception('Product quantity should be null when inventory information is missing.');
}
if ($order['product']['is_official'] !== null) {
    throw new Exception('Official flag should be null when no product record exists.');
}
if ($order['payment']['amount'] !== 1299) {
    throw new Exception('Payment amount should be preserved during normalization.');
}

echo "Order without product test passed\n";

