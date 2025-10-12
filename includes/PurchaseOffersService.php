<?php
/**
 * PurchaseOffersService manages marketplace offers/counteroffers for fixed-price listings.
 */
declare(strict_types=1);

require_once __DIR__ . '/repositories/ShopLogger.php';

final class PurchaseOffersService
{
    private const LISTING_ACTIVE_STATUSES = ['approved', 'live'];
    private const STATUS_PENDING_SELLER = 'pending_seller';
    private const STATUS_PENDING_BUYER = 'pending_buyer';
    private const STATUS_ACCEPTED = 'accepted';
    private const STATUS_DECLINED = 'declined';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_EXPIRED = 'expired';

    private mysqli $conn;
    private ?bool $hasListingOriginalPriceColumn = null;
    /** @var array<string, bool> */
    private array $columnExistenceCache = [];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Fetch offer history for a listing and return rows visible to the actor.
     *
     * @return array{offers: array<int, array<string, mixed>>, seller_id: int, actor_role: string}
     */
    public function listOffersForListing(int $listingId, int $actorId): array
    {
        $context = [
            'offers' => [],
            'seller_id' => 0,
            'actor_role' => 'viewer',
        ];

        if ($listingId <= 0) {
            return $context;
        }

        $listingStmt = $this->conn->prepare('SELECT id, owner_id FROM listings WHERE id = ? LIMIT 1');
        if ($listingStmt === false) {
            return $context;
        }

        $listingStmt->bind_param('i', $listingId);
        if (!$listingStmt->execute()) {
            $listingStmt->close();
            return $context;
        }

        $listingResult = $listingStmt->get_result();
        $listing = $listingResult->fetch_assoc();
        $listingStmt->close();

        if (!$listing) {
            return $context;
        }

        $sellerId = (int) $listing['owner_id'];
        $context['seller_id'] = $sellerId;
        if ($actorId === $sellerId) {
            $context['actor_role'] = 'seller';
        }

        $sql = 'SELECT '
            . 'po.id, po.listing_id, po.buyer_id, po.seller_id, po.quantity, po.offered_price, '
            . 'po.status, po.message, po.created_at, po.updated_at, po.expires_at, '
            . 'buyer.display_name AS buyer_display_name, buyer.username AS buyer_username, '
            . 'seller.display_name AS seller_display_name, seller.username AS seller_username '
            . 'FROM purchase_offers po '
            . 'JOIN users buyer ON buyer.id = po.buyer_id '
            . 'JOIN users seller ON seller.id = po.seller_id '
            . 'WHERE po.listing_id = ? '
            . 'ORDER BY po.created_at DESC '
            . 'LIMIT 50';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return $context;
        }

        $stmt->bind_param('i', $listingId);
        if (!$stmt->execute()) {
            $stmt->close();
            return $context;
        }

        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        $offers = [];
        foreach ($rows as $row) {
            $offerId = (int) $row['id'];
            $buyerId = (int) $row['buyer_id'];
            $offerSellerId = (int) $row['seller_id'];
            $status = (string) $row['status'];
            $quantity = (int) $row['quantity'];
            $price = (float) $row['offered_price'];
            $total = round($price * $quantity, 2);

            $isSeller = $actorId === $offerSellerId;
            $isBuyer = $actorId === $buyerId;

            if ($actorId <= 0) {
                continue;
            }

            if (!$isSeller && !$isBuyer) {
                continue;
            }

            if ($isSeller) {
                $context['actor_role'] = 'seller';
            } elseif ($context['actor_role'] !== 'seller') {
                $context['actor_role'] = $isBuyer ? 'buyer' : 'viewer';
            }

            $statusLabel = $this->statusLabel($status);
            $createdAtRaw = (string) ($row['created_at'] ?? '');
            $createdAtTime = $createdAtRaw !== '' ? strtotime($createdAtRaw) : false;
            $createdAtDisplay = $createdAtTime ? date('M j, Y g:i a', $createdAtTime) : '—';
            $message = trim((string) ($row['message'] ?? ''));

            $awaitingRole = $this->awaitingRoleForStatus($status);
            $isOpen = $awaitingRole !== null;
            $canActorAcceptOrDecline = (
                ($status === self::STATUS_PENDING_SELLER && $isSeller)
                || ($status === self::STATUS_PENDING_BUYER && $isBuyer)
            );
            $canActorCancel = (
                ($status === self::STATUS_PENDING_SELLER && $isBuyer)
                || ($status === self::STATUS_PENDING_BUYER && $isSeller)
            );

            $buyerDisplay = $this->participantDisplay($row, 'buyer', $buyerId, $actorId);
            $sellerDisplay = $this->participantDisplay($row, 'seller', $offerSellerId, $actorId);
            $lastActorDisplay = $status === self::STATUS_PENDING_BUYER
                ? $sellerDisplay
                : $buyerDisplay;

            $offer = [
                'id' => $offerId,
                'quantity' => $quantity,
                'offered_price' => number_format($price, 2, '.', ''),
                'offered_price_display' => number_format($price, 2),
                'total_display' => number_format($total, 2),
                'status' => $status,
                'status_label' => $statusLabel,
                'created_at_iso' => $createdAtRaw,
                'created_at_display' => $createdAtDisplay,
                'message' => $message,
                'buyer_id' => $buyerId,
                'buyer_display' => $buyerDisplay,
                'seller_id' => $offerSellerId,
                'seller_display' => $sellerDisplay,
                'initiator_display' => $lastActorDisplay,
                'can_accept' => $isOpen && $canActorAcceptOrDecline,
                'can_decline' => $isOpen && $canActorAcceptOrDecline,
                'can_cancel' => $isOpen && $canActorCancel,
                'is_counter' => $status === self::STATUS_PENDING_BUYER,
                'awaiting_role' => $awaitingRole,
            ];

            $offers[] = $offer;
        }

        $context['offers'] = $offers;

        return $context;
    }

    /**
     * Create a new offer initiated by the buyer.
     *
     * @return array{offer_id: int, status: string}
     */
    public function createOffer(int $actorId, int $listingId, int $quantity, string $offerPriceInput): array
    {
        if ($actorId <= 0) {
            throw new RuntimeException('Authentication required.');
        }

        $quantity = $this->normaliseQuantity($quantity);
        $price = $this->normalisePrice($offerPriceInput);

        $this->conn->begin_transaction();

        try {
            $listing = $this->fetchListing($listingId, true);
            if (!$listing) {
                throw new RuntimeException('Listing not found.');
            }

            $sellerId = (int) $listing['owner_id'];
            if ($sellerId === $actorId) {
                throw new RuntimeException('You cannot make an offer on your own listing.');
            }

            $this->assertListingAcceptsOffers($listing);

            $available = $this->availableQuantity($listing);
            if ($available <= 0) {
                throw new RuntimeException('This listing is out of stock.');
            }

            if ($quantity > $available) {
                throw new RuntimeException(sprintf('Only %d in stock for this listing.', $available));
            }

            $ceiling = $this->priceCeiling($listing);
            if ($price > $ceiling) {
                throw new RuntimeException('Offer price cannot exceed the original listing price.');
            }

            $stmt = $this->conn->prepare(
                'INSERT INTO purchase_offers (listing_id, buyer_id, seller_id, quantity, offered_price, message, status) '
                . 'VALUES (?,?,?,?,?,?,?)'
            );
            if ($stmt === false) {
                throw new RuntimeException('Failed to record the offer.');
            }

            $priceString = $this->formatPrice($price);
            $message = '';
            $status = self::STATUS_PENDING_SELLER;
            $stmt->bind_param('iiiisss', $listingId, $actorId, $sellerId, $quantity, $priceString, $message, $status);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to record the offer.');
            }

            $offerId = (int) $stmt->insert_id;
            $stmt->close();

            $this->conn->commit();

            shop_log('purchase_offer.created', [
                'offer_id' => $offerId,
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'quantity' => $quantity,
                'offered_price' => $priceString,
                'status' => $status,
            ]);

            return [
                'offer_id' => $offerId,
                'status' => $status,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();

            if (!$e instanceof RuntimeException && !$e instanceof InvalidArgumentException) {
                shop_log('purchase_offer.create_error', [
                    'listing_id' => $listingId,
                    'actor_id' => $actorId,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Counter an existing open offer.
     *
     * @return array{offer_id: int, status: string}
     */
    public function counterOffer(int $actorId, int $offerId, int $quantity, string $offerPriceInput): array
    {
        if ($actorId <= 0) {
            throw new RuntimeException('Authentication required.');
        }

        $quantity = $this->normaliseQuantity($quantity);
        $price = $this->normalisePrice($offerPriceInput);

        $this->conn->begin_transaction();

        try {
            $offer = $this->fetchOfferWithListing($offerId, true);
            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            $status = (string) $offer['offer_status'];
            if (!in_array($status, [self::STATUS_PENDING_SELLER, self::STATUS_PENDING_BUYER], true)) {
                throw new RuntimeException('Only open offers can be countered.');
            }

            $sellerId = (int) $offer['seller_id'];
            $buyerId = (int) $offer['buyer_id'];

            if ($actorId !== $sellerId && $actorId !== $buyerId) {
                throw new RuntimeException('You are not part of this negotiation.');
            }

            $isSeller = $actorId === $sellerId;
            $isBuyer = $actorId === $buyerId;

            if ($status === self::STATUS_PENDING_SELLER && !$isSeller) {
                throw new RuntimeException('Only the seller can counter right now.');
            }

            if ($status === self::STATUS_PENDING_BUYER && !$isBuyer) {
                throw new RuntimeException('Only the buyer can counter right now.');
            }

            $this->assertListingAcceptsOffers($offer, 'listing_status');

            $available = $this->availableQuantity([
                'quantity' => $offer['listing_quantity'],
                'reserved_qty' => $offer['listing_reserved_qty'],
            ]);
            if ($available <= 0) {
                throw new RuntimeException('This listing is out of stock.');
            }

            if ($quantity > $available) {
                throw new RuntimeException(sprintf('Only %d in stock for this listing.', $available));
            }

            $ceiling = $this->priceCeiling([
                'original_price' => $offer['listing_original_price'],
                'price' => $offer['listing_price'],
            ]);
            if ($price > $ceiling) {
                throw new RuntimeException('Offer price cannot exceed the original listing price.');
            }

            $stmt = $this->conn->prepare(
                'UPDATE purchase_offers SET quantity = ?, offered_price = ?, message = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );
            if ($stmt === false) {
                throw new RuntimeException('Failed to record the counteroffer.');
            }

            $priceString = $this->formatPrice($price);
            $nextStatus = $status === self::STATUS_PENDING_SELLER
                ? self::STATUS_PENDING_BUYER
                : self::STATUS_PENDING_SELLER;
            $message = (string) ($offer['message'] ?? '');
            $stmt->bind_param('isssi', $quantity, $priceString, $message, $nextStatus, $offerId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to record the counteroffer.');
            }

            $stmt->close();

            $this->conn->commit();

            shop_log('purchase_offer.countered', [
                'offer_id' => $offerId,
                'listing_id' => $offer['listing_id'],
                'actor_id' => $actorId,
                'quantity' => $quantity,
                'offered_price' => $priceString,
                'status' => $nextStatus,
            ]);

            return [
                'offer_id' => $offerId,
                'status' => $nextStatus,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();

            if (!$e instanceof RuntimeException && !$e instanceof InvalidArgumentException) {
                shop_log('purchase_offer.counter_error', [
                    'offer_id' => $offerId,
                    'actor_id' => $actorId,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Accept an open offer.
     */
    public function acceptOffer(int $actorId, int $offerId): array
    {
        return $this->closeOffer($actorId, $offerId, self::STATUS_ACCEPTED);
    }

    /**
     * Decline an open offer.
     */
    public function declineOffer(int $actorId, int $offerId): array
    {
        return $this->closeOffer($actorId, $offerId, self::STATUS_DECLINED);
    }

    /**
     * Cancel an open offer created by the actor.
     */
    public function cancelOffer(int $actorId, int $offerId): array
    {
        if ($actorId <= 0) {
            throw new RuntimeException('Authentication required.');
        }

        $this->conn->begin_transaction();

        try {
            $offer = $this->fetchOfferWithListing($offerId, true);
            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            $status = (string) $offer['offer_status'];
            if (!in_array($status, [self::STATUS_PENDING_SELLER, self::STATUS_PENDING_BUYER], true)) {
                throw new RuntimeException('Only open offers can be cancelled.');
            }

            $sellerId = (int) $offer['seller_id'];
            $buyerId = (int) $offer['buyer_id'];
            $isSeller = $actorId === $sellerId;
            $isBuyer = $actorId === $buyerId;

            if (
                ($status === self::STATUS_PENDING_SELLER && !$isBuyer)
                || ($status === self::STATUS_PENDING_BUYER && !$isSeller)
            ) {
                throw new RuntimeException('You can only cancel your outstanding offer.');
            }

            $cancelledStatus = self::STATUS_CANCELLED;
            $stmt = $this->conn->prepare('UPDATE purchase_offers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to cancel the offer.');
            }

            $stmt->bind_param('si', $cancelledStatus, $offerId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to cancel the offer.');
            }
            $stmt->close();

            $this->conn->commit();

            shop_log('purchase_offer.cancelled', [
                'offer_id' => $offerId,
                'listing_id' => $offer['listing_id'],
                'actor_id' => $actorId,
                'status' => $cancelledStatus,
            ]);

            return [
                'offer_id' => $offerId,
                'status' => $cancelledStatus,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();

            if (!$e instanceof RuntimeException && !$e instanceof InvalidArgumentException) {
                shop_log('purchase_offer.cancel_error', [
                    'offer_id' => $offerId,
                    'actor_id' => $actorId,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Close an open offer with the provided terminal status.
     *
     * @return array{offer_id: int, status: string}
     */
    private function closeOffer(int $actorId, int $offerId, string $status): array
    {
        if ($actorId <= 0) {
            throw new RuntimeException('Authentication required.');
        }

        if (!in_array($status, [self::STATUS_ACCEPTED, self::STATUS_DECLINED], true)) {
            throw new InvalidArgumentException('Unsupported status change.');
        }

        $this->conn->begin_transaction();

        try {
            $offer = $this->fetchOfferWithListing($offerId, true);
            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            $currentStatus = (string) $offer['offer_status'];
            if (!in_array($currentStatus, [self::STATUS_PENDING_SELLER, self::STATUS_PENDING_BUYER], true)) {
                throw new RuntimeException('This offer is no longer open.');
            }

            $sellerId = (int) $offer['seller_id'];
            $buyerId = (int) $offer['buyer_id'];

            $isSeller = $actorId === $sellerId;
            $isBuyer = $actorId === $buyerId;

            if (
                ($currentStatus === self::STATUS_PENDING_SELLER && !$isSeller)
                || ($currentStatus === self::STATUS_PENDING_BUYER && !$isBuyer)
            ) {
                throw new RuntimeException('You are not part of this negotiation.');
            }

            $stmt = $this->conn->prepare('UPDATE purchase_offers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to update the offer.');
            }

            $stmt->bind_param('si', $status, $offerId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to update the offer.');
            }
            $stmt->close();

            $this->conn->commit();

            shop_log('purchase_offer.' . $status, [
                'offer_id' => $offerId,
                'listing_id' => $offer['listing_id'],
                'actor_id' => $actorId,
                'status' => $status,
            ]);

            return [
                'offer_id' => $offerId,
                'status' => $status,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();

            if (!$e instanceof RuntimeException && !$e instanceof InvalidArgumentException) {
                shop_log('purchase_offer.close_error', [
                    'offer_id' => $offerId,
                    'actor_id' => $actorId,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Load a listing row.
     *
     * @return array<string, mixed>|null
     */
    private function fetchListing(int $listingId, bool $forUpdate = false): ?array
    {
        $originalPriceSelect = $this->hasListingOriginalPriceColumn()
            ? 'original_price'
            : 'price AS original_price';

        $sql = 'SELECT id, owner_id, status, quantity, reserved_qty, ' . $originalPriceSelect . ', price FROM listings WHERE id = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to load listing.');
        }

        $stmt->bind_param('i', $listingId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to load listing.');
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc() ?: null;
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Load an offer joined to its listing context.
     *
     * @return array<string, mixed>|null
     */
    private function fetchOfferWithListing(int $offerId, bool $forUpdate = false): ?array
    {
        $originalPriceSelect = $this->hasListingOriginalPriceColumn()
            ? 'l.original_price AS listing_original_price'
            : 'l.price AS listing_original_price';

        $sql = 'SELECT '
            . 'po.id, po.listing_id, po.buyer_id, po.seller_id, po.quantity AS offer_quantity, '
            . 'po.offered_price, po.status AS offer_status, po.message, po.expires_at, po.created_at, '
            . 'l.owner_id AS listing_owner_id, l.status AS listing_status, '
            . 'l.quantity AS listing_quantity, l.reserved_qty AS listing_reserved_qty, '
            . $originalPriceSelect . ', l.price AS listing_price '
            . 'FROM purchase_offers po '
            . 'JOIN listings l ON l.id = po.listing_id '
            . 'WHERE po.id = ?';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to load offer.');
        }

        $stmt->bind_param('i', $offerId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to load offer.');
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc() ?: null;
        $stmt->close();

        return $row ?: null;
    }

    private function hasListingOriginalPriceColumn(): bool
    {
        if ($this->hasListingOriginalPriceColumn === null) {
            $this->hasListingOriginalPriceColumn = $this->columnExists('listings', 'original_price');
        }

        return $this->hasListingOriginalPriceColumn;
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

    private function statusLabel(string $status): string
    {
        $labels = [
            self::STATUS_PENDING_SELLER => 'Waiting on Seller',
            self::STATUS_PENDING_BUYER => 'Waiting on Buyer',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_DECLINED => 'Declined',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Expired',
        ];

        if (isset($labels[$status])) {
            return $labels[$status];
        }

        $status = str_replace('_', ' ', $status);

        return ucfirst($status);
    }

    private function awaitingRoleForStatus(string $status): ?string
    {
        return match ($status) {
            self::STATUS_PENDING_SELLER => 'seller',
            self::STATUS_PENDING_BUYER => 'buyer',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function participantDisplay(array $row, string $role, int $participantId, int $actorId): string
    {
        if ($participantId <= 0) {
            return 'Unknown';
        }

        if ($participantId === $actorId) {
            return 'You';
        }

        if ($role === 'buyer') {
            $display = (string) ($row['buyer_display_name'] ?? '');
            $username = (string) ($row['buyer_username'] ?? '');
        } else {
            $display = (string) ($row['seller_display_name'] ?? '');
            $username = (string) ($row['seller_username'] ?? '');
        }

        if ($display !== '') {
            return $display;
        }

        if ($username !== '') {
            return $username;
        }

        return 'User #' . $participantId;
    }

    /**
     * Ensure the listing is in an offerable state.
     */
    private function assertListingAcceptsOffers(array $listing, string $statusKey = 'status'): void
    {
        $status = strtolower((string) ($listing[$statusKey] ?? ''));
        if (!in_array($status, self::LISTING_ACTIVE_STATUSES, true)) {
            throw new RuntimeException('This listing is not currently accepting offers.');
        }
    }

    /**
     * Calculate the available quantity for the listing.
     */
    private function availableQuantity(array $listing): int
    {
        $quantity = isset($listing['quantity']) ? (int) $listing['quantity'] : 0;
        $reserved = isset($listing['reserved_qty']) ? (int) $listing['reserved_qty'] : 0;
        $available = $quantity - $reserved;

        return $available > 0 ? $available : 0;
    }

    /**
     * Determine the offer ceiling for the listing.
     */
    private function priceCeiling(array $listing): float
    {
        $ceiling = $listing['original_price'] ?? ($listing['price'] ?? 0.0);
        return (float) $ceiling;
    }

    /**
     * Normalise the provided quantity.
     */
    private function normaliseQuantity(int $quantity): int
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        return $quantity;
    }

    /**
     * Normalise a money value to cents precision.
     */
    private function normalisePrice(string $price): float
    {
        $price = trim($price);
        if ($price === '') {
            throw new InvalidArgumentException('Enter an offer price.');
        }

        $value = (float) $price;
        if (!is_numeric($price) || $value <= 0) {
            throw new InvalidArgumentException('Enter a valid offer price.');
        }

        return round($value, 2);
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
}
