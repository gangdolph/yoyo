<?php
declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/theme_store.php';

header('Content-Type: application/json');

if (!authz_has_role('admin')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit;
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Request body is empty.']);
    exit;
}

$decoded = json_decode($rawBody, true);
$validation = yoyo_theme_store_validate_submission($decoded);

if (!empty($validation['errors'])) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Theme collection validation failed.',
        'errors' => $validation['errors'],
    ]);
    exit;
}

$collection = $validation['collection'];

$storageDir = dirname(yoyo_theme_store_path());
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to create data directory.']);
        exit;
    }
}

$filePath = yoyo_theme_store_path();
$jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
$encoded = json_encode($collection, $jsonOptions);
if ($encoded === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to encode theme data.']);
    exit;
}

$encoded .= PHP_EOL;
if (file_put_contents($filePath, $encoded, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write theme file.']);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'collection' => $collection,
]);
