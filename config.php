<?php
/*
 * Unified configuration file for SkuzE
 * Combines database, SMTP, AdSense, Square, wallet, auction, and feature toggle settings.
 * 
 * Discovery note:
 * - Combines the older example file and live developer configuration.
 * - Includes new manager UI rollout flags, wallet/withdrawal defaults, Square sync toggles,
 *   and public transparency defaults for displaying fees and policy info site-wide.
 *
 * SECURITY:
 * - Never commit this file to version control with live credentials.
 * - Keep sandbox and production credentials separate.
 */

return [
  // === Database configuration ===
  'db_host'   => 'localhost',
  'db_port'   => 3306,
  'db_socket' => null,
  'db_user'   => 'skuzqsas_Developer',
  'db_pass'   => '1zTh1z4n0k4yPAssw02d?!',
  'db_name'   => 'skuzqsas_MainDB',

  // === SMTP / Email configuration ===
  'smtp_host' => 'skuze.tech',
  'smtp_user' => 'owner@skuze.tech',
  'smtp_pass' => '1zTh1z4n0k4yPAssw02d?!',
  'smtp_port' => 465,

  // === Google AdSense configuration ===
  'adsense_client' => 'ca-pub-XXXXXXXXXXXXXXXX',
  'adsense_slot'   => '1234567890',

  // === Square payment configuration ===
  // Can also be supplied via environment variables:
  // SQUARE_APPLICATION_ID, SQUARE_LOCATION_ID, SQUARE_ACCESS_TOKEN, SQUARE_ENVIRONMENT
  'square_application_id' => 'sandbox-sq0idb-LBdSpI5-PgJvxpaCuuSMDw',
  'square_location_id'    => 'LTXYAJY9GR996',
  'square_access_token'   => 'EAAAly0J7zt7XMl2qEIGK_WmWB_i4v-KYU9tDWKPxPO0VcpwNNqIu89DDjMddFO_',
  'square_environment'    => 'sandbox', // or 'production'
  'square_webhook_signature_key' => '',

  // === Square feature toggles ===
  'USE_SQUARE_ORDERS'     => true,
  'SQUARE_SYNC_ENABLED'   => true,
  'SQUARE_SYNC_DIRECTION' => 'pull', // 'pull'|'push'|'two_way'

  // === Manager workspace rollout ===
  'SHOP_MANAGER_V1_ENABLED' => true,  // enables the new Shop Manager interface

  // === Core commerce feature toggles ===
  'FEATURE_TAXONOMY'           => true,  // Enables brand/model taxonomy for listings and products
  'FEATURE_ORDER_CENTRALIZATION'=> true,  // Centralized order and fulfillment handling
  'FEATURE_WALLETS'            => true,  // Enables internal user wallet system

  // === Wallet & Withdraw defaults ===
  'SHOW_WALLET'                 => true,
  'WITHDRAW_FEE_PERCENT_NON_MEMBER' => 1.5,   // 1.5% for non-members
  'WITHDRAW_MIN_CENTS'              => 100,   // $1.00 minimum withdrawal
  'MEMBER_ROLE_NAME'                => 'member',
  'WALLET_WITHDRAW_MIN_CENTS'       => 100,   // $1.00 minimum withdrawal for wallet
  'WALLET_HOLD_HOURS'               => 24,    // Funds on hold for 24h before release

  // === Fee transparency and public display settings ===
  'FEES_PERCENT'       => 2.0,  // base transaction fee percentage
  'FEES_FIXED_CENTS'   => 0,    // flat per-transaction fee
  'FEE_SHOW_BREAKDOWN' => true, // show fee breakdown on checkout and account pages

  // === Auction system configuration ===
  'AUCTION_SOFT_CLOSE_SECS' => 120, // extend close time if bid is placed near end
  'AUCTION_MIN_INCREMENT_TABLE' => [
    '0-99.99'   => 1.00,
    '100-499.99'=> 2.50,
    '500+'      => 5.00,
  ],
];
