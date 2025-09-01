<?php
/**
 * Square-specific configuration.
 *
 * This file provides the SQUARE_ACCESS_TOKEN constant required for
 * communicating with Square's APIs and may optionally define SQUARE_ENV
 * (e.g., 'production' or 'sandbox').
 *
 * Real credentials should not be committed to version control. Instead, set
 * the relevant environment variables or edit this file locally without
 * committing.
 */

if (!defined('SQUARE_ACCESS_TOKEN')) {
    // Prefer an environment variable but fall back to a placeholder.
    define('SQUARE_ACCESS_TOKEN', getenv('SQUARE_ACCESS_TOKEN') ?: 'YOUR_SQUARE_ACCESS_TOKEN');
}

// Optionally define SQUARE_ENV if provided via environment variable.
if (!defined('SQUARE_ENV')) {
    $env = getenv('SQUARE_ENV');
    if ($env !== false && $env !== '') {
        define('SQUARE_ENV', $env);
    }
}

?>

