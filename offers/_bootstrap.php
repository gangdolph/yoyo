<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PurchaseOffersService.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit a JSON response.
 *
 * @param array<string,mixed> $payload
 */
function offers_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function offers_error(string $message, int $status = 400): void
{
    offers_json_response([
        'success' => false,
        'error' => $message,
    ], $status);
}

function offers_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        offers_error('Unsupported method.', 405);
    }
}

function offers_validate_csrf_token(?string $token): void
{
    if (!validate_token($token ?? '')) {
        offers_error('Invalid request token.', 419);
    }
}

$offersService = new PurchaseOffersService($conn);
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    offers_error('Authentication required.', 401);
}
