<?php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

offers_require_post();
offers_validate_csrf_token($_POST['csrf_token'] ?? null);

$offerId = isset($_POST['offer_id']) ? (int) $_POST['offer_id'] : 0;
if ($offerId <= 0) {
    offers_error('Select an offer to cancel.');
}

try {
    $result = $offersService->cancelOffer($currentUserId, $offerId);
    offers_json_response([
        'success' => true,
        'offer' => $result,
        // TODO: Trigger notification/email to the other party about the cancellation.
    ]);
} catch (InvalidArgumentException | RuntimeException $e) {
    offers_error($e->getMessage());
} catch (Throwable $e) {
    error_log('[offers/cancel] Unexpected failure: ' . $e->getMessage());
    offers_error('Unable to cancel the offer right now. Please try again.', 500);
}
