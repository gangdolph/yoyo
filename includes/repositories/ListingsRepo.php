<?php
/*
 * Discovery note: Listings repository only handled status changes and tag updates.
 * Change: Added critical field editing with moderation flows plus richer owner pagination metadata.
 */
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';
require_once __DIR__ . '/ChangeRequestsService.php';
require_once __DIR__ . '/../tags.php';

final class ListingsRepo
{
    private const STATUSES = ['draft','pending','approved','live','closed','delisted'];

    private mysqli $conn;
    private ChangeRequestsService $changeRequests;
    /** @var array<int, bool> */
    private array $brandCache = [];
    /** @var array<int, array{id: int, brand_id: int, name: string}> */
    private array $modelCache = [];
    private ?bool $hasOriginalPriceColumn = null;
    /** @var array<string, bool> */
    private array $columnExistenceCache = [];

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
        $originalPriceInput = $data['original_price'] ?? null;
        $brandInput = $data['brand_id'] ?? null;
        $modelInput = $data['model_id'] ?? null;

        $brandId = $this->normalizeNullableId($brandInput);
        $modelId = $this->normalizeNullableId($modelInput);
        [$brandId, $modelId] = $this->normalizeBrandModel($brandId, $modelId);

        $normalisedPrice = number_format((float) $price, 2, '.', '');
        $price = $normalisedPrice;

        if ($originalPriceInput !== null && $originalPriceInput !== '') {
            $originalPrice = number_format((float) $originalPriceInput, 2, '.', '');
        } else {
            $originalPrice = $normalisedPrice;
        }

        if ($title === '' || $description === '' || $condition === '' || $price === '') {
            return ['success' => false, 'error' => 'Missing required fields.'];
        }

        $status = $autoApprove ? 'approved' : 'pending';
        $requiresReview = !$autoApprove;

        $hasOriginalPriceColumn = $this->hasOriginalPriceColumn();

        $this->conn->begin_transaction();
        try {
            $sql = 'INSERT INTO listings (owner_id, product_sku, brand_id, model_id, title, description, `condition`, price';
            $values = 'VALUES (?, ?, ?, ?, ?, ?, ?, ?';

            $bindings = [
                ['type' => 'i', 'value' => $ownerId],
                ['type' => 's', 'value' => $productSku],
                ['type' => 'i', 'value' => $brandId],
                ['type' => 'i', 'value' => $modelId],
                ['type' => 's', 'value' => $title],
                ['type' => 's', 'value' => $description],
                ['type' => 's', 'value' => $condition],
                ['type' => 's', 'value' => $price],
            ];

            if ($hasOriginalPriceColumn) {
                $sql .= ', original_price';
                $values .= ', ?';
                $bindings[] = ['type' => 's', 'value' => $originalPrice];
            }

            $sql .= ', quantity, reserved_qty, category, tags, image, status, pickup_only) ';
            $values .= ', ?, 0, ?, ?, ?, ?, ?)';

            $bindings[] = ['type' => 'i', 'value' => $quantity];
            $bindings[] = ['type' => 's', 'value' => $category];
            $bindings[] = ['type' => 's', 'value' => $tagsStorage];
            $bindings[] = ['type' => 's', 'value' => $image];
            $bindings[] = ['type' => 's', 'value' => $status];
            $bindings[] = ['type' => 'i', 'value' => $pickupOnly];

            $sql .= $values;

            $stmt = $this->conn->prepare($sql);

            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare listing creation.');
            }

            $types = '';
            $params = [];
            foreach ($bindings as $index => $binding) {
                $types .= $binding['type'];
                $params[$index] = &$bindings[$index]['value'];
            }

            $stmt->bind_param($types, ...$params);

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
     * Update core listing fields, falling back to moderation when required.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, changed: bool, requires_review?: bool}
     */
    public function updateDetails(int $listingId, int $actorId, array $data, bool $isAdmin, bool $isOfficial): array
    {
        if ($listingId <= 0 || $actorId <= 0) {
            return ['success' => false, 'changed' => false];
        }

        $this->conn->begin_transaction();
        try {
            $listing = $this->fetchListingDetails($listingId, true);
            if (!$listing) {
                throw new RuntimeException('Listing could not be found.');
            }

            $ownerId = (int) $listing['owner_id'];
            $status = strtolower((string) $listing['status']);
            $isOwner = $ownerId === $actorId;

            $originalCeilingValue = $listing['original_price'] ?? $listing['price'];
            $originalCeiling = number_format((float) $originalCeilingValue, 2, '.', '');
            $originalCeilingFloat = (float) $originalCeiling;

            if (!$isOwner && !$isAdmin && !$isOfficial) {
                throw new RuntimeException('You do not have permission to edit this listing.');
            }

            $changes = [];

            if (array_key_exists('title', $data)) {
                $title = trim((string) $data['title']);
                if ($title !== '' && $title !== (string) $listing['title']) {
                    $changes['title'] = $title;
                }
            }

            if (array_key_exists('price', $data)) {
                $priceInput = trim((string) $data['price']);
                if ($priceInput !== '') {
                    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $priceInput)) {
                        throw new RuntimeException('Prices must be numeric with up to two decimals.');
                    }
                    $normalisedPrice = number_format((float) $priceInput, 2, '.', '');
                    $currentPrice = number_format((float) $listing['price'], 2, '.', '');
                    $requestedPrice = (float) $normalisedPrice;
                    if ($requestedPrice > $originalCeilingFloat + 0.00001) {
                        throw new RuntimeException(
                            sprintf(
                                'Price cannot exceed the original listing price of $%s.',
                                number_format($originalCeilingFloat, 2, '.', '')
                            )
                        );
                    }
                    if ($normalisedPrice !== $currentPrice) {
                        $changes['price'] = $normalisedPrice;
                    }
                }
            }

            if (array_key_exists('sale_price', $data)) {
                $salePriceInput = trim((string) $data['sale_price']);
                $currentSalePrice = $listing['sale_price'];
                if ($salePriceInput === '') {
                    if ($currentSalePrice !== null) {
                        $changes['sale_price'] = null;
                    }
                } else {
                    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $salePriceInput)) {
                        throw new RuntimeException('Sale price must be numeric with up to two decimals.');
                    }
                    $normalisedSalePrice = number_format((float) $salePriceInput, 2, '.', '');
                    $requestedSalePrice = (float) $normalisedSalePrice;
                    if ($requestedSalePrice > $originalCeilingFloat + 0.00001) {
                        throw new RuntimeException(
                            sprintf(
                                'Sale price cannot exceed the original listing price of $%s.',
                                number_format($originalCeilingFloat, 2, '.', '')
                            )
                        );
                    }
                    $currentSalePriceNormalised = $currentSalePrice === null
                        ? null
                        : number_format((float) $currentSalePrice, 2, '.', '');
                    if ($currentSalePriceNormalised !== $normalisedSalePrice) {
                        $changes['sale_price'] = $normalisedSalePrice;
                    }
                }
            }

            if (array_key_exists('quantity', $data)) {
                $quantityInput = trim((string) $data['quantity']);
                if ($quantityInput !== '') {
                    if (!preg_match('/^\d+$/', $quantityInput)) {
                        throw new RuntimeException('Quantity must be a whole number.');
                    }
                    $nextQuantity = max(0, (int) $quantityInput);
                    $currentQuantity = isset($listing['quantity']) ? (int) $listing['quantity'] : null;
                    if ($currentQuantity === null || $nextQuantity !== $currentQuantity) {
                        $changes['quantity'] = $nextQuantity;
                    }
                }
            }

            $currentBrandId = isset($listing['brand_id']) ? (int) $listing['brand_id'] : null;
            $currentModelId = isset($listing['model_id']) ? (int) $listing['model_id'] : null;
            $brandProvided = array_key_exists('brand_id', $data);
            $modelProvided = array_key_exists('model_id', $data);
            if ($brandProvided || $modelProvided) {
                $brandCandidate = $brandProvided ? $this->normalizeNullableId($data['brand_id']) : $currentBrandId;
                $modelCandidate = $modelProvided ? $this->normalizeNullableId($data['model_id']) : $currentModelId;
                [$nextBrandId, $nextModelId] = $this->normalizeBrandModel($brandCandidate, $modelCandidate);
                if ($nextBrandId !== $currentBrandId) {
                    $changes['brand_id'] = $nextBrandId;
                }
                if ($nextModelId !== $currentModelId) {
                    $changes['model_id'] = $nextModelId;
                }
            }

            if ($changes === []) {
                $this->conn->rollback();
                return ['success' => true, 'changed' => false];
            }

            if ($status === 'live' && !$isAdmin && !$isOfficial) {
                $this->conn->rollback();
                $summary = 'Critical update requested: ' . implode(', ', array_keys($changes));
                $this->changeRequests->createCriticalUpdateRequest($listingId, $actorId, $changes, $summary);

                return ['success' => true, 'changed' => false, 'requires_review' => true];
            }

            $set = [];
            $types = '';
            $params = [];

            if (isset($changes['title'])) {
                $set[] = 'title = ?';
                $types .= 's';
                $params[] = $changes['title'];
            }

            if (isset($changes['price'])) {
                $set[] = 'price = ?';
                $types .= 's';
                $params[] = $changes['price'];
            }

            if (array_key_exists('sale_price', $changes)) {
                $set[] = 'sale_price = ?';
                $types .= 's';
                $params[] = $changes['sale_price'];
            }

            if (array_key_exists('quantity', $changes)) {
                $set[] = 'quantity = ?';
                $types .= 'i';
                $params[] = $changes['quantity'];
            }

            if (array_key_exists('brand_id', $changes)) {
                if ($changes['brand_id'] === null) {
                    $set[] = 'brand_id = NULL';
                } else {
                    $set[] = 'brand_id = ?';
                    $types .= 'i';
                    $params[] = $changes['brand_id'];
                }
            }

            if (array_key_exists('model_id', $changes)) {
                if ($changes['model_id'] === null) {
                    $set[] = 'model_id = NULL';
                } else {
                    $set[] = 'model_id = ?';
                    $types .= 'i';
                    $params[] = $changes['model_id'];
                }
            }

            $set[] = 'updated_at = NOW()';
            $sql = 'UPDATE listings SET ' . implode(', ', $set) . ' WHERE id = ?';
            $types .= 'i';
            $params[] = $listingId;

            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare listing update.');
            }

            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to update the listing.');
            }

            $changed = $stmt->affected_rows > 0;
            $stmt->close();

            if ($changed && array_key_exists('quantity', $changes)) {
                $guard = $this->conn->prepare('UPDATE listings SET reserved_qty = LEAST(reserved_qty, quantity) WHERE id = ?');
                if ($guard !== false) {
                    $guard->bind_param('i', $listingId);
                    $guard->execute();
                    $guard->close();
                }
            }

            $this->conn->commit();

            if ($changed) {
                shop_log('listing.details_updated', [
                    'listing_id' => $listingId,
                    'actor_id' => $actorId,
                    'fields' => array_keys($changes),
                ]);
            }

            return ['success' => true, 'changed' => $changed];
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('listing.update_error', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
     * Approve and apply a pending change request.
     */
    public function approveChangeRequest(int $requestId, int $reviewerId): void
    {
        if ($requestId <= 0 || $reviewerId <= 0) {
            throw new RuntimeException('Invalid change request.');
        }

        $request = $this->changeRequests->fetchRequest($requestId);
        if (!$request) {
            throw new RuntimeException('Change request not found.');
        }

        if (($request['status'] ?? 'pending') !== 'pending') {
            throw new RuntimeException('Change request has already been processed.');
        }

        $listingId = (int) $request['listing_id'];
        $type = (string) ($request['change_type'] ?? 'status');

        if ($type === 'critical_update') {
            $payload = $request['payload'] ?? null;
            $changes = [];
            if ($payload !== null && $payload !== '') {
                $decoded = json_decode((string) $payload, true);
                if (is_array($decoded)) {
                    $changes = $decoded;
                }
            }

            if ($changes === []) {
                $this->changeRequests->markSingleRequest($requestId, 'approved', $reviewerId);

                return;
            }

            $result = $this->updateDetails($listingId, $reviewerId, $changes, true, true);
            if (empty($result['success'])) {
                throw new RuntimeException('Failed to apply the requested listing changes.');
            }

            $this->changeRequests->markSingleRequest($requestId, 'approved', $reviewerId);
            shop_log('listing.change_request_approved', [
                'request_id' => $requestId,
                'listing_id' => $listingId,
                'reviewer_id' => $reviewerId,
                'fields' => array_keys($changes),
            ]);

            return;
        }

        if ($type === 'status') {
            $requestedStatus = (string) ($request['requested_status'] ?? 'approved');
            $this->updateStatus($listingId, $reviewerId, $requestedStatus, true);
            $this->changeRequests->markSingleRequest($requestId, 'approved', $reviewerId, $requestedStatus);

            shop_log('listing.change_request_approved', [
                'request_id' => $requestId,
                'listing_id' => $listingId,
                'reviewer_id' => $reviewerId,
                'status' => $requestedStatus,
            ]);

            return;
        }

        $this->changeRequests->markSingleRequest($requestId, 'approved', $reviewerId);
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

        $originalPriceSelect = $this->hasOriginalPriceColumn()
            ? 'l.original_price'
            : 'l.price AS original_price';

        $select = 'SELECT l.id, l.title, l.price, ' . $originalPriceSelect . ', l.sale_price, l.category, l.tags, l.image, l.brand_id, l.model_id, sb.name AS brand_name, sm.name AS model_name, l.status, l.pickup_only, l.created_at, '
            . 'l.updated_at, l.quantity, l.reserved_qty, l.is_official_listing, '
            . '(SELECT COUNT(*) FROM listing_change_requests r WHERE r.listing_id = l.id AND r.status = "pending") '
            . 'AS pending_change_count, '
            . '(SELECT MAX(r.change_summary) FROM listing_change_requests r WHERE r.listing_id = l.id '
            . 'AND r.status = "pending") AS pending_change_summary';

        if ($withSyncState) {
            $select .= ', scm.sync_status AS square_sync_status, scm.square_object_id AS square_object_id';
        }

        $select .= ' FROM listings l';

        $select .= ' LEFT JOIN service_brands sb ON sb.id = l.brand_id';
        $select .= ' LEFT JOIN service_models sm ON sm.id = l.model_id';

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
                            'original_price' => $row['original_price'],
                            'sale_price' => $row['sale_price'],
                            'category' => $row['category'],
                            'tags' => tags_from_storage($row['tags'] ?? null),
                            'status' => (string) $row['status'],
                            'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : null,
                            'model_id' => isset($row['model_id']) ? (int) $row['model_id'] : null,
                            'brand_name' => $row['brand_name'] ?? null,
                            'model_name' => $row['model_name'] ?? null,
                            'pickup_only' => (bool) $row['pickup_only'],
                            'created_at' => $row['created_at'],
                            'updated_at' => $row['updated_at'],
                            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                            'reserved_qty' => isset($row['reserved_qty']) ? (int) $row['reserved_qty'] : null,
                            'image' => $row['image'],
                            'square_sync_status' => $row['square_sync_status'] ?? null,
                            'square_object_id' => $row['square_object_id'] ?? null,
                            'is_official_listing' => !empty($row['is_official_listing']),
                            'pending_change' => ((int) ($row['pending_change_count'] ?? 0)) > 0,
                            'pending_change_summary' => $row['pending_change_summary'] ?? null,
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

        $sql = 'SELECT id, owner_id, status, quantity, reserved_qty, product_sku, brand_id, model_id FROM listings WHERE id = ?';
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
     * Fetch listing details for critical updates.
     *
     * @return array<string, mixed>|null
     */
    private function fetchListingDetails(int $listingId, bool $forUpdate = false): ?array
    {
        $originalPriceSelect = $this->hasOriginalPriceColumn()
            ? 'original_price'
            : 'price AS original_price';

        $sql = 'SELECT id, owner_id, status, title, price, ' . $originalPriceSelect . ', sale_price, quantity, reserved_qty, brand_id, model_id FROM listings WHERE id = ?';
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

    private function hasOriginalPriceColumn(): bool
    {
        if ($this->hasOriginalPriceColumn === null) {
            $this->hasOriginalPriceColumn = $this->columnExists('listings', 'original_price');
        }

        return $this->hasOriginalPriceColumn;
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = strtolower($table) . '.' . strtolower($column);
        if (array_key_exists($cacheKey, $this->columnExistenceCache)) {
            return $this->columnExistenceCache[$cacheKey];
        }

        $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare column existence check.');
        }

        $stmt->bind_param('ss', $table, $column);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute column existence check.');
        }

        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        $this->columnExistenceCache[$cacheKey] = $exists;

        return $exists;
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

    private function normalizeNullableId($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (!ctype_digit($trimmed)) {
                if (!is_numeric($trimmed)) {
                    return null;
                }
                $trimmed = (string) (int) $trimmed;
            }
            $intVal = (int) $trimmed;

            return $intVal > 0 ? $intVal : null;
        }

        if (is_numeric($value)) {
            $intVal = (int) $value;
            return $intVal > 0 ? $intVal : null;
        }

        return null;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function normalizeBrandModel(?int $brandId, ?int $modelId): array
    {
        if ($brandId !== null) {
            $this->assertBrandExists($brandId);
        }

        if ($modelId !== null) {
            $model = $this->loadModel($modelId);
            if ($model === null) {
                throw new RuntimeException('Selected model is invalid.');
            }
            $modelBrand = (int) $model['brand_id'];
            if ($brandId !== null && $brandId !== $modelBrand) {
                throw new RuntimeException('Selected model does not match the chosen brand.');
            }
            $brandId = $modelBrand;
        }

        return [$brandId, $modelId];
    }

    private function assertBrandExists(int $brandId): void
    {
        if (isset($this->brandCache[$brandId])) {
            return;
        }

        $stmt = $this->conn->prepare('SELECT id FROM service_brands WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to validate brand.');
        }

        $stmt->bind_param('i', $brandId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to validate brand.');
        }

        $stmt->bind_result($foundId);
        $hasRow = $stmt->fetch();
        $stmt->close();

        if (!$hasRow) {
            throw new RuntimeException('Selected brand is invalid.');
        }

        $this->brandCache[$brandId] = true;
    }

    private function loadModel(int $modelId): ?array
    {
        if (isset($this->modelCache[$modelId])) {
            return $this->modelCache[$modelId];
        }

        $stmt = $this->conn->prepare('SELECT id, brand_id, name FROM service_models WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to validate model.');
        }

        $stmt->bind_param('i', $modelId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to validate model.');
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return null;
        }

        $model = [
            'id' => (int) $row['id'],
            'brand_id' => (int) $row['brand_id'],
            'name' => (string) $row['name'],
        ];

        $this->modelCache[$modelId] = $model;
        $this->brandCache[$model['brand_id']] = true;

        return $model;
    }
}
