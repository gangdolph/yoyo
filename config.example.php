<?php
// Copy this file to config.php and fill in real credentials.
// Never commit your actual config.php to version control.
return [
  'db_host' => 'localhost',
  'db_user' => 'your_db_user',
  'db_pass' => 'your_db_password',
  'db_name' => 'skuze_site',

  'smtp_host' => 'mail.example.com',
  'smtp_user' => 'user@example.com',
  'smtp_pass' => 'your_email_password',
  'smtp_port' => 465,

  // Google AdSense configuration
  'adsense_client' => 'ca-pub-XXXXXXXXXXXXXXXX',
  'adsense_slot' => '1234567890',

  // Square payment configuration
  // These can also be supplied via env vars:
  // SQUARE_APPLICATION_ID, SQUARE_LOCATION_ID, SQUARE_ACCESS_TOKEN, SQUARE_ENVIRONMENT
  'square_application_id' => 'sandbox-sq0idb-xxxxxxxxxxxxxxxxxxxxxx',
  'square_location_id' => 'LXXXXXXXXXXXX',
  'square_access_token' => 'EAAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
  'square_environment' => 'sandbox', // or 'production'
];
