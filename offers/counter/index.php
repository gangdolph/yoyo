<?php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

offers_require_post();
offers_validate_csrf_token($_POST['csrf_token'] ?? null);

$offerId = isset($_POST['offer_id']) ? (int) $_POST['offer_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
$offerPrice = isset($_POST['offer_price']) ? (string) $_POST['offer_price'] : '';

if ($offerId <= 0) {
    offers_error('Select an offer to counter.');
}

try {
    $result = $offersService->counterOffer($currentUserId, $offerId, $quantity, $offerPrice);
    offers_json_response([
        'success' => true,
        'offer' => $result,
        // TODO: Trigger notification/email to the other party.
    ]);
} catch (InvalidArgumentException | RuntimeException $e) {
    offers_error($e->getMessage());
} catch (Throwable $e) {
    error_log('[offers/counter] Unexpected failure: ' . $e->getMessage());
    offers_error('Unable to counter the offer right now. Please try again.', 500);
}
