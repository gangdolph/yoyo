<?php
/*
 * Discovery note: Config already combines database, SMTP, and Square keys.
 * Change: Added Square feature toggles earlier, introduced a manager UI flag for phased rollout,
 *         and now expose the webhook signature key for securing inbound events.
 * Change: Introduced non-secret policy display defaults for public transparency messaging.
 */
// Copy this file to config.php and fill in real credentials.
// Never commit your actual config.php to version control.
return [
  'db_host'   => 'localhost',
  'db_port'   => 3306,
  'db_socket' => null,
  'db_user'   => 'skuzqsas_Developer',
  'db_pass'   => '1zTh1z4n0k4yPAssw02d?!',
  'db_name'   => 'skuzqsas_MainDB',

  'smtp_host' => 'skuze.tech',
  'smtp_user' => 'owner@skuze.tech',
  'smtp_pass' => '1zTh1z4n0k4yPAssw02d?!',
  'smtp_port' => 465,

  // Google AdSense configuration
  'adsense_client' => 'ca-pub-XXXXXXXXXXXXXXXX',
  'adsense_slot' => '1234567890',

  // Square payment configuration
  // These can also be supplied via env vars:
  // SQUARE_APPLICATION_ID, SQUARE_LOCATION_ID, SQUARE_ACCESS_TOKEN, SQUARE_ENVIRONMENT
  'square_application_id' => 'sandbox-sq0idb-LBdSpI5-PgJvxpaCuuSMDw',
  'square_location_id' => 'LTXYAJY9GR996',
  'square_access_token' => 'EAAAly0J7zt7XMl2qEIGK_WmWB_i4v-KYU9tDWKPxPO0VcpwNNqIu89DDjMddFO_',
  'square_environment' => 'sandbox', // or 'production'
  'square_webhook_signature_key' => '',

  // Square feature flags
  'USE_SQUARE_ORDERS' => false,
  'SQUARE_SYNC_ENABLED' => false,
  'SQUARE_SYNC_DIRECTION' => 'pull', // 'pull'|'push'|'two_way'
  'SHOP_MANAGER_V1_ENABLED' => false,
  'SHOW_WALLET' => true,

  // Public transparency defaults
  'FEES_PERCENT' => 2.0,
  'FEES_FIXED_CENTS' => 0,
  'FEE_SHOW_BREAKDOWN' => true,
  'AUCTION_SOFT_CLOSE_SECS' => 120,
  'AUCTION_MIN_INCREMENT_TABLE' => [
    '0-99.99' => 1.00,
    '100-499.99' => 2.50,
    '500+' => 5.00,
  ],
  'WITHDRAW_FEE_PERCENT_NON_MEMBER' => 1.5,
  'WITHDRAW_MIN_CENTS' => 100,
  'MEMBER_ROLE_NAME' => 'member',
  'WALLET_WITHDRAW_MIN_CENTS' => 100,
  'WALLET_HOLD_HOURS' => 24,
];
