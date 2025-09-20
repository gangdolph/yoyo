<?php
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';
require_once __DIR__ . '/ChangeRequestsService.php';
require_once __DIR__ . '/../tags.php';

final class ListingsRepo
{
    private const STATUSES = ['draft','pending','approved','live','closed','delisted'];

    private mysqli $conn;
    private ChangeRequestsService $changeRequests;

    /**
     * Define allowed transitions between listing statuses.
     *
     * @var array<string, array<int, string>>
     */
    private array $transitions = [
        'draft' => ['pending', 'approved', 'live', 'delisted'],
        'pending' => ['approved', 'delisted', 'draft'],
        'approved' => ['live', 'closed', 'delisted'],
        'live' => ['closed', 'delisted'],
        'closed' => ['live', 'delisted'],
        'delisted' => ['draft', 'pending', 'live'],
    ];

    public function __construct(mysqli $conn, ?ChangeRequestsService $changeRequests = null)
    {
        $this->conn = $conn;
        $this->changeRequests = $changeRequests ?? new ChangeRequestsService($conn);
    }

    /**
     * Create a new listing owned by the user.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, listing_id?: int, status?: string, requires_review?: bool, error?: string}
     */
    public function create(int $ownerId, array $data, bool $autoApprove = false): array
    {
        if ($ownerId <= 0) {
            return ['success' => false, 'error' => 'Invalid owner.'];
        }

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $condition = trim((string) ($data['condition'] ?? ''));
        $price = (string) ($data['price'] ?? '0');
        $category = $data['category'] ?? null;
        $tagsStorage = $data['tags'] ?? null;
        $image = $data['image'] ?? null;
        $pickupOnly = !empty($data['pickup_only']) ? 1 : 0;
        $productSku = $data['product_sku'] ?? null;
        $quantity = isset($data['quantity']) ? max(1, (int) $data['quantity']) : 1;

        if ($title === '' || $description === '' || $condition === '' || $price === '') {
            return ['success' => false, 'error' => 'Missing required fields.'];
        }

        $status = $autoApprove ? 'approved' : 'pending';
        $requiresReview = !$autoApprove;

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO listings (owner_id, product_sku, title, description, `condition`, price, quantity, reserved_qty, category, tags, image, status, pickup_only) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)'
            );

            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare listing creation.');
            }

            $stmt->bind_param(
                'isssssisissi',
                $ownerId,
                $productSku,
                $title,
                $description,
                $condition,
                $price,
                $quantity,
                $category,
                $tagsStorage,
                $image,
                $status,
                $pickupOnly
            );

            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to save the listing.');
            }

            $listingId = (int) $stmt->insert_id;
            $stmt->close();

            if ($requiresReview) {
                $this->changeRequests->createStatusRequest(
                    $listingId,
                    $ownerId,
                    'approved',
                    'Listing created and awaiting approval.'
                );
            }

            $this->conn->commit();

            shop_log('listing.created', [
                'listing_id' => $listingId,
                'owner_id' => $ownerId,
                'status' => $status,
            ]);

            return [
                'success' => true,
                'listing_id' => $listingId,
                'status' => $status,
                'requires_review' => $requiresReview,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('listing.create_error', [
                'owner_id' => $ownerId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update stored tags for a listing.
     *
     * @return array{success: bool, changed: bool}
     */
    public function updateTags(int $listingId, int $actorId, array $tags): array
    {
        if ($listingId <= 0 || $actorId <= 0) {
            return ['success' => false, 'changed' => false];
        }

        $listing = $this->fetchListing($listingId);
        if (!$listing || (int) $listing['owner_id'] !== $actorId) {
            return ['success' => false, 'changed' => false];
        }

        $storage = tags_to_storage($tags);
        $sql = $storage === null
            ? 'UPDATE listings SET tags = NULL, updated_at = NOW() WHERE id = ?'
            : 'UPDATE listings SET tags = ?, updated_at = NOW() WHERE id = ?';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return ['success' => false, 'changed' => false];
        }

        if ($storage === null) {
            $stmt->bind_param('i', $listingId);
        } else {
            $stmt->bind_param('si', $storage, $listingId);
        }

        $success = $stmt->execute();
        $changed = $success && $stmt->affected_rows > 0;
        $stmt->close();

        if ($changed) {
            shop_log('listing.tags_updated', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
            ]);
        }

        return ['success' => $success, 'changed' => $changed];
    }

    /**
     * Update the listing status, enforcing moderation when necessary.
     *
     * @return array{success: bool, changed: bool, status?: string, requires_review?: bool}
     */
    public function updateStatus(int $listingId, int $actorId, string $status, bool $isAdmin = false): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            return ['success' => false, 'changed' => false];
        }

        $listing = $this->fetchListing($listingId, true);
        if (!$listing) {
            return ['success' => false, 'changed' => false];
        }

        $ownerId = (int) $listing['owner_id'];
        $currentStatus = (string) $listing['status'];
        $isOwner = $ownerId === $actorId;

        if (!$isOwner && !$isAdmin) {
            return ['success' => false, 'changed' => false];
        }

        $allowed = $this->transitions[$currentStatus] ?? [];
        if ($currentStatus === $status) {
            return ['success' => true, 'changed' => false, 'status' => $currentStatus];
        }

        if (!in_array($status, $allowed, true)) {
            return ['success' => false, 'changed' => false];
        }

        if ($this->statusRequiresApproval($status) && !$isAdmin) {
            $this->changeRequests->createStatusRequest(
                $listingId,
                $actorId,
                $status,
                'Seller requested status change.'
            );

            shop_log('listing.status_requested', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'target_status' => $status,
            ]);

            return ['success' => true, 'changed' => false, 'requires_review' => true];
        }

        $stmt = $this->conn->prepare('UPDATE listings SET status = ?, updated_at = NOW() WHERE id = ?');
        if ($stmt === false) {
            return ['success' => false, 'changed' => false];
        }

        $stmt->bind_param('si', $status, $listingId);
        $success = $stmt->execute();
        $changed = $success && $stmt->affected_rows > 0;
        $stmt->close();

        if ($success) {
            $this->changeRequests->approveOpenRequests($listingId, $actorId, $status);
            shop_log('listing.status_updated', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'status' => $status,
                'changed' => $changed,
            ]);
        }

        return ['success' => $success, 'changed' => $changed, 'status' => $status];
    }

    /**
     * Delete a listing owned by the actor.
     */
    public function delete(int $listingId, int $actorId, bool $isAdmin = false): array
    {
        if ($listingId <= 0 || $actorId <= 0) {
            return ['success' => false, 'changed' => false];
        }

        $listing = $this->fetchListing($listingId);
        if (!$listing) {
            return ['success' => false, 'changed' => false];
        }

        $ownerId = (int) $listing['owner_id'];
        if ($ownerId !== $actorId && !$isAdmin) {
            return ['success' => false, 'changed' => false];
        }

        $stmt = $this->conn->prepare('DELETE FROM listings WHERE id = ?');
        if ($stmt === false) {
            return ['success' => false, 'changed' => false];
        }

        $stmt->bind_param('i', $listingId);
        $success = $stmt->execute();
        $changed = $success && $stmt->affected_rows > 0;
        $stmt->close();

        if ($changed) {
            $this->changeRequests->cancelOpenRequests($listingId, $ownerId);
            shop_log('listing.deleted', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'is_admin' => $isAdmin,
            ]);
        }

        return ['success' => $success, 'changed' => $changed];
    }

    /**
     * Paginate listings for the owner.
     *
     * @return array{items: array<int, array<string, mixed>>, filters: array<string, mixed>, pagination: array<string, int>}
     */
    public function paginateForOwner(int $ownerId, array $filters = [], bool $withSyncState = false): array
    {
        $sanitizedStatus = $this->sanitizeStatus($filters['status'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 10)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $types = 'i';
        $params = [$ownerId];
        $where = 'WHERE l.owner_id = ?';

        if ($sanitizedStatus !== '') {
            $where .= ' AND l.status = ?';
            $types .= 's';
            $params[] = $sanitizedStatus;
        }

        if ($search !== '') {
            $where .= ' AND l.title LIKE ?';
            $types .= 's';
            $params[] = '%' . $search . '%';
        }

        $countSql = 'SELECT COUNT(*) FROM listings l ' . $where;
        $total = 0;
        if ($stmt = $this->conn->prepare($countSql)) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $stmt->bind_result($totalCount);
                if ($stmt->fetch()) {
                    $total = (int) $totalCount;
                }
            }
            $stmt->close();
        }

        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;
        $items = [];

        $select = 'SELECT l.id, l.title, l.price, l.category, l.tags, l.image, l.status, l.pickup_only, l.created_at, '
            . 'l.updated_at, l.quantity, l.reserved_qty';

        if ($withSyncState) {
            $select .= ', scm.sync_status AS square_sync_status, scm.square_object_id AS square_object_id';
        }

        $select .= ' FROM listings l';

        if ($withSyncState) {
            $select .= ' LEFT JOIN square_catalog_map scm ON scm.local_type = "listing" AND scm.local_id = l.id';
        }

        $select .= ' ' . $where . ' ORDER BY l.created_at DESC LIMIT ? OFFSET ?';

        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$perPage, $offset]);

        if ($stmt = $this->conn->prepare($select)) {
            $stmt->bind_param($listTypes, ...$listParams);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result instanceof mysqli_result) {
                    while ($row = $result->fetch_assoc()) {
                        $items[] = [
                            'id' => (int) $row['id'],
                            'title' => (string) $row['title'],
                            'price' => $row['price'],
                            'category' => $row['category'],
                            'tags' => tags_from_storage($row['tags'] ?? null),
                            'status' => (string) $row['status'],
                            'pickup_only' => (bool) $row['pickup_only'],
                            'created_at' => $row['created_at'],
                            'updated_at' => $row['updated_at'],
                            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                            'reserved_qty' => isset($row['reserved_qty']) ? (int) $row['reserved_qty'] : null,
                            'image' => $row['image'],
                            'square_sync_status' => $row['square_sync_status'] ?? null,
                            'square_object_id' => $row['square_object_id'] ?? null,
                        ];
                    }
                }
            }
            $stmt->close();
        }

        return [
            'items' => $items,
            'filters' => [
                'status' => $sanitizedStatus,
                'search' => $search,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }

    /**
     * Fetch a single listing record.
     *
     * @return array<string, mixed>|null
     */
    public function fetchListing(int $listingId, bool $forUpdate = false): ?array
    {
        if ($listingId <= 0) {
            return null;
        }

        $sql = 'SELECT id, owner_id, status, quantity, reserved_qty, product_sku FROM listings WHERE id = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('i', $listingId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Expose permitted statuses for UIs.
     *
     * @return array<int, string>
     */
    public function allowedStatuses(): array
    {
        return self::STATUSES;
    }

    private function statusRequiresApproval(string $status): bool
    {
        return in_array($status, ['approved', 'live'], true);
    }

    private function sanitizeStatus($status): string
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, self::STATUSES, true) ? $status : '';
    }
}
