<?php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

offers_require_post();
offers_validate_csrf_token($_POST['csrf_token'] ?? null);

$listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
$offerPrice = isset($_POST['offer_price']) ? (string) $_POST['offer_price'] : '';

if ($listingId <= 0) {
    offers_error('Select a valid listing to make an offer.');
}

try {
    $result = $offersService->createOffer($currentUserId, $listingId, $quantity, $offerPrice);
    offers_json_response([
        'success' => true,
        'offer' => $result,
        // TODO: Trigger notification/email to the listing owner.
    ]);
} catch (InvalidArgumentException | RuntimeException $e) {
    offers_error($e->getMessage());
} catch (Throwable $e) {
    error_log('[offers/create] Unexpected failure: ' . $e->getMessage());
    offers_error('Unable to submit the offer right now. Please try again.', 500);
}
