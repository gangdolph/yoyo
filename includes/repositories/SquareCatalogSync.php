<?php
/*
 * Discovery note: Square catalog sync helper was wired to an obsolete config flag
 * and lacked documentation.
 * Change: Documented the helper and aligned enablement with the new SQUARE_SYNC
 * feature flags so the manager UI honours the toggle.
 */
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';

final class SquareCatalogSync
{
    private mysqli $conn;
    private bool $enabled;

    public function __construct(mysqli $conn, array $config)
    {
        $this->conn = $conn;
        $this->enabled = !empty($config['SQUARE_SYNC_ENABLED']) && $this->tableExists();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Queue a listing for synchronization with Square.
     *
     * @return array{success: bool, status: string, square_object_id: string}
     */
    public function queueListingSync(int $listingId, int $actorId): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'status' => 'disabled', 'square_object_id' => ''];
        }

        if ($listingId <= 0) {
            return ['success' => false, 'status' => 'invalid', 'square_object_id' => ''];
        }

        $objectId = $this->lookupCurrentSquareId($listingId) ?? ('listing#' . $listingId);

        $stmt = $this->conn->prepare(
            'INSERT INTO square_catalog_map (local_type, local_id, square_object_id, sync_status, last_synced_at) '
            . 'VALUES ("listing", ?, ?, "pending", NULL) '
            . 'ON DUPLICATE KEY UPDATE square_object_id = VALUES(square_object_id), sync_status = "pending", updated_at = NOW(), last_synced_at = NULL'
        );

        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare Square sync.');
        }

        $stmt->bind_param('is', $listingId, $objectId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Failed to queue Square sync: ' . $error);
        }
        $stmt->close();

        shop_log('square.sync_listing', [
            'listing_id' => $listingId,
            'actor_id' => $actorId,
            'square_object_id' => $objectId,
        ]);

        return ['success' => true, 'status' => 'pending', 'square_object_id' => $objectId];
    }

    /**
     * Fetch sync state for a set of listings.
     *
     * @param array<int> $listingIds
     * @return array<int, array{square_object_id: string|null, sync_status: string|null}>
     */
    public function listingSyncState(array $listingIds): array
    {
        if (!$this->enabled || !$listingIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
        $types = str_repeat('i', count($listingIds));

        $sql = 'SELECT local_id, square_object_id, sync_status FROM square_catalog_map '
             . 'WHERE local_type = "listing" AND local_id IN (' . $placeholders . ')';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param($types, ...$listingIds);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $state = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $state[(int) $row['local_id']] = [
                    'square_object_id' => $row['square_object_id'],
                    'sync_status' => $row['sync_status'],
                ];
            }
        }
        $stmt->close();

        return $state;
    }

    private function lookupCurrentSquareId(int $listingId): ?string
    {
        $stmt = $this->conn->prepare('SELECT square_object_id FROM square_catalog_map WHERE local_type = "listing" AND local_id = ? LIMIT 1');
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('i', $listingId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $stmt->bind_result($objectId);
        $hasRow = $stmt->fetch();
        $stmt->close();

        return $hasRow ? (string) $objectId : null;
    }

    private function tableExists(): bool
    {
        $result = $this->conn->query("SHOW TABLES LIKE 'square_catalog_map'");
        if ($result instanceof mysqli_result) {
            $exists = $result->num_rows > 0;
            $result->free();
            return $exists;
        }

        return false;
    }
}
