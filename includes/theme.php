<?php
declare(strict_types=1);

require_once __DIR__ . '/theme_store.php';

if (defined('YOYO_THEME_BOOTSTRAPPED')) {
    return;
}

define('YOYO_THEME_BOOTSTRAPPED', true);

$collectionResult = yoyo_theme_store_load();
$collection = $collectionResult['collection'];

$defaultTheme = [];
if (!empty($collection['themes'])) {
    foreach ($collection['themes'] as $entry) {
        if (!is_array($entry) || !isset($entry['id'])) {
            continue;
        }
        if ($entry['id'] === $collection['defaultThemeId']) {
            $defaultTheme = isset($entry['theme']) && is_array($entry['theme']) ? $entry['theme'] : [];
            break;
        }
    }

    if (empty($defaultTheme) && isset($collection['themes'][0]['theme']) && is_array($collection['themes'][0]['theme'])) {
        $defaultTheme = $collection['themes'][0]['theme'];
    }
}

$encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

$themePayload = $defaultTheme ?: new stdClass();
$themeJson = json_encode($themePayload, $encodingOptions);

if ($themeJson === false) {
    $themeJson = '{}';
}

$collectionJson = json_encode($collection, $encodingOptions);
if ($collectionJson === false) {
    $collectionJson = 'null';
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

echo '<script>window.__THEME_COLLECTION__ = ' . $collectionJson . ';</script>' . PHP_EOL;
echo '<script>window.__THEME__ = Object.assign({}, ' . $themeJson . ', window.__THEME__ || {});</script>' . PHP_EOL;

foreach ($assets['js'] as $public => $file) {
    $src = yoyo_versioned_asset($public, $file);
    echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>' . PHP_EOL;
}
