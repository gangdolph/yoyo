<?php

declare(strict_types=1);

require __DIR__ . '/../_debug_bootstrap.php';

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';

$token = $config['square_access_token'] ?? getenv('SQUARE_ACCESS_TOKEN') ?? '';
$token = trim((string)$token);
if ($token === '') {
    throw new RuntimeException('Square access token missing');
}

$env = $config['square_environment'] ?? getenv('SQUARE_ENVIRONMENT') ?? '';
$env = strtolower(trim((string)$env));
$env = $env === 'production' ? 'production' : 'sandbox';

$client = new \Square\SquareClient(
    $token,
    $env,
    [
        // Optional SDK options, e.g. 'timeout' => 30
    ]
);

return $client;
