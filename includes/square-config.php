<?php
/**
 * Square application configuration helper.
 *
 * Loads application ID, location ID, and environment from the main config
 * file if available or from environment variables as a fallback.
 */

$configPath = __DIR__ . '/../config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
    $applicationId = $config['square_application_id'] ?? getenv('SQUARE_APPLICATION_ID') ?: 'YOUR_APPLICATION_ID';
    $locationId    = $config['square_location_id'] ?? getenv('SQUARE_LOCATION_ID') ?: 'YOUR_LOCATION_ID';
    $environment   = $config['square_environment'] ?? getenv('SQUARE_ENVIRONMENT') ?: 'sandbox';
} else {
    $applicationId = getenv('SQUARE_APPLICATION_ID') ?: 'YOUR_APPLICATION_ID';
    $locationId    = getenv('SQUARE_LOCATION_ID') ?: 'YOUR_LOCATION_ID';
    $environment   = getenv('SQUARE_ENVIRONMENT') ?: 'sandbox';
}

return [
    'application_id' => $applicationId,
    'location_id'    => $locationId,
    'environment'    => strtolower($environment) === 'production' ? 'production' : 'sandbox',
];
