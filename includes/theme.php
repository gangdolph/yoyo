<?php
declare(strict_types=1);

if (defined('YOYO_THEME_BOOTSTRAPPED')) {
    return;
}

define('YOYO_THEME_BOOTSTRAPPED', true);

$themePath = dirname(__DIR__) . '/data/theme.json';
$themeData = [];

if (is_file($themePath) && is_readable($themePath)) {
    $themeJson = file_get_contents($themePath);
    if ($themeJson !== false) {
        $decoded = json_decode($themeJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $themeData = $decoded;
        }
    }
}

$themePayload = $themeData ?: new stdClass();
$encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
$themeJson = json_encode($themePayload, $encodingOptions);

if ($themeJson === false) {
    $themeJson = '{}';
}

/**
 * Resolve an asset URL with a cache-busting query string.
 */
if (!function_exists('yoyo_versioned_asset')) {
    function yoyo_versioned_asset(string $publicPath, string $filePath): string
    {
        $version = is_file($filePath) ? (string) filemtime($filePath) : (string) time();
        return $publicPath . '?v=' . rawurlencode($version);
    }
}

$assets = [
    'css' => [
        '/assets/css/theme.css' => __DIR__ . '/../assets/css/theme.css',
        '/assets/css/effects.css' => __DIR__ . '/../assets/css/effects.css',
    ],
    'js' => [
        '/assets/js/theme.js' => __DIR__ . '/../assets/js/theme.js',
    ],
];

foreach ($assets['css'] as $public => $file) {
    $href = yoyo_versioned_asset($public, $file);
    echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
}

echo '<script>window.__THEME__ = Object.assign({}, ' . $themeJson . ', window.__THEME__ || {});</script>' . PHP_EOL;

foreach ($assets['js'] as $public => $file) {
    $src = yoyo_versioned_asset($public, $file);
    echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>' . PHP_EOL;
}
