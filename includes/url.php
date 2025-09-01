<?php
/**
 * Generate the base URL for the application.
 *
 * The scheme is detected using X-Forwarded-Proto and HTTPS/port checks
 * with a final enforcement to https.
 * The host is taken from X-Forwarded-Host or HTTP_HOST.
 *
 * @param string $path Optional path to append.
 * @return string
 */
function base_url(string $path = ''): string
{
    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $scheme = 'https';
    }

    // Always enforce https
    $scheme = 'https';

    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    $base = $scheme . '://' . $host;

    if ($path !== '') {
        $base .= '/' . ltrim($path, '/');
    }

    return $base;
}
