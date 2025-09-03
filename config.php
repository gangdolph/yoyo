<?php
// Copy this file to config.php and fill in real credentials.
// Never commit your actual config.php to version control.
return [
  'db_host' => '127.0.0.1', 
  'db_port' => 3306,
  'db_user' => 'skuzqsas_Developer',
  'db_pass' => '1zTh1z4n0k4yPAssw02d?!',
  'db_name' => 'skuzqsas_MainDB',

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
];
