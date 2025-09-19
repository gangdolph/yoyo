<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_admin'])) {
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
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$storageDir = dirname(__DIR__) . '/data';
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to create data directory.']);
        exit;
    }
}

$filePath = $storageDir . '/theme.json';
$jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
$encoded = json_encode($decoded, $jsonOptions);
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

echo json_encode(['status' => 'ok']);
