<?php
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';

final class ChangeRequestsService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Persist a status change request for later moderation.
     */
    public function createStatusRequest(int $listingId, int $requesterId, string $requestedStatus, ?string $summary = null): ?int
    {
        if ($listingId <= 0 || $requesterId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO listing_change_requests (listing_id, requester_id, change_type, change_summary, requested_status) '
            . 'VALUES (?, ?, "status", ?, ?)' // change_type constrained to status for now
        );

        if ($stmt === false) {
            shop_log('change_request.error', [
                'listing_id' => $listingId,
                'requester_id' => $requesterId,
                'status' => $requestedStatus,
                'error' => $this->conn->error,
            ]);
            return null;
        }

        $stmt->bind_param('iiss', $listingId, $requesterId, $summary, $requestedStatus);
        if (!$stmt->execute()) {
            $stmt->close();
            shop_log('change_request.error', [
                'listing_id' => $listingId,
                'requester_id' => $requesterId,
                'status' => $requestedStatus,
                'error' => $stmt->error,
            ]);
            return null;
        }

        $id = (int) $stmt->insert_id;
        $stmt->close();

        shop_log('change_request.created', [
            'id' => $id,
            'listing_id' => $listingId,
            'requester_id' => $requesterId,
            'status' => $requestedStatus,
        ]);

        return $id;
    }

    /**
     * Mark pending requests for the listing as approved by the reviewer.
     */
    public function approveOpenRequests(int $listingId, int $reviewerId, string $status): int
    {
        return $this->resolveOpenRequests($listingId, $reviewerId, 'approved', $status);
    }

    /**
     * Mark pending requests for the listing as rejected by the reviewer.
     */
    public function rejectOpenRequests(int $listingId, int $reviewerId, string $note = ''): int
    {
        return $this->resolveOpenRequests($listingId, $reviewerId, 'rejected', null, $note);
    }

    /**
     * Cancel pending requests initiated by the requester.
     */
    public function cancelOpenRequests(int $listingId, int $requesterId): int
    {
        if ($listingId <= 0 || $requesterId <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare(
            'UPDATE listing_change_requests SET status = "cancelled", resolved_at = NOW(), updated_at = NOW() '
            . 'WHERE listing_id = ? AND requester_id = ? AND status = "pending"'
        );

        if ($stmt === false) {
            return 0;
        }

        $stmt->bind_param('ii', $listingId, $requesterId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            shop_log('change_request.cancelled', [
                'listing_id' => $listingId,
                'requester_id' => $requesterId,
                'count' => $affected,
            ]);
        }

        return $affected;
    }

    private function resolveOpenRequests(
        int $listingId,
        int $reviewerId,
        string $resolution,
        ?string $requestedStatus = null,
        string $note = ''
    ): int {
        if ($listingId <= 0 || $reviewerId <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare(
            'UPDATE listing_change_requests '
            . 'SET status = ?, reviewer_id = ?, resolved_at = NOW(), review_notes = ?, requested_status = COALESCE(requested_status, ?), updated_at = NOW() '
            . 'WHERE listing_id = ? AND status = "pending"'
        );

        if ($stmt === false) {
            return 0;
        }

        $stmt->bind_param('sissi', $resolution, $reviewerId, $note, $requestedStatus, $listingId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            shop_log('change_request.' . $resolution, [
                'listing_id' => $listingId,
                'reviewer_id' => $reviewerId,
                'status' => $requestedStatus,
                'count' => $affected,
            ]);
        }

        return $affected;
    }
}
