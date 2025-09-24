<?php
/**
 * PurchaseOffersService manages marketplace offers/counteroffers for fixed-price listings.
 */
declare(strict_types=1);

require_once __DIR__ . '/repositories/ShopLogger.php';

final class PurchaseOffersService
{
    private const LISTING_ACTIVE_STATUSES = ['approved', 'live'];

    private mysqli $conn;

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
            . 'po.id, po.listing_id, po.counter_of, po.initiator_id, po.quantity, po.offer_price, '
            . 'po.status, po.created_at, po.expires_at, '
            . 'u.display_name, u.username '
            . 'FROM purchase_offers po '
            . 'JOIN users u ON u.id = po.initiator_id '
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
            $initiatorId = (int) $row['initiator_id'];
            $status = (string) $row['status'];
            $quantity = (int) $row['quantity'];
            $price = (float) $row['offer_price'];
            $total = round($price * $quantity, 2);

            $buyerId = null;
            try {
                $buyerId = $this->resolveBuyerId($offerId, $sellerId);
            } catch (Throwable $ignored) {
                $buyerId = null;
            }

            $isSeller = $actorId === $sellerId;
            $isInitiator = $actorId === $initiatorId;
            $isBuyer = $buyerId !== null && $actorId === $buyerId;

            if ($actorId <= 0) {
                continue;
            }

            if (!$isSeller && !$isInitiator && !$isBuyer) {
                continue;
            }

            if ($context['actor_role'] !== 'seller') {
                $context['actor_role'] = $isBuyer || $isInitiator ? 'buyer' : 'viewer';
            }

            $statusLabel = ucfirst($status);
            $createdAtRaw = (string) ($row['created_at'] ?? '');
            $createdAtTime = $createdAtRaw !== '' ? strtotime($createdAtRaw) : false;
            $createdAtDisplay = $createdAtTime ? date('M j, Y g:i a', $createdAtTime) : '—';
            $offer = [
                'id' => $offerId,
                'quantity' => $quantity,
                'offer_price' => number_format($price, 2, '.', ''),
                'offer_price_display' => number_format($price, 2),
                'total_display' => number_format($total, 2),
                'status' => $status,
                'status_label' => $statusLabel,
                'created_at_iso' => $createdAtRaw,
                'created_at_display' => $createdAtDisplay,
                'counter_of' => $row['counter_of'] !== null ? (int) $row['counter_of'] : null,
                'initiator_id' => $initiatorId,
                'initiator_display' => $isInitiator
                    ? 'You'
                    : ($row['display_name'] ?: ($row['username'] ?: ('User #' . $initiatorId))),
                'can_accept' => $status === 'open' && !$isInitiator && ($isSeller || $isBuyer),
                'can_decline' => $status === 'open' && !$isInitiator && ($isSeller || $isBuyer),
                'can_cancel' => $status === 'open' && $isInitiator,
                'is_counter' => $row['counter_of'] !== null,
                'buyer_id' => $buyerId,
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
                'INSERT INTO purchase_offers (listing_id, initiator_id, quantity, offer_price) VALUES (?,?,?,?)'
            );
            if ($stmt === false) {
                throw new RuntimeException('Failed to record the offer.');
            }

            $priceString = $this->formatPrice($price);
            $stmt->bind_param('iiis', $listingId, $actorId, $quantity, $priceString);
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
                'offer_price' => $priceString,
            ]);

            return [
                'offer_id' => $offerId,
                'status' => 'open',
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
     * @return array{offer_id: int, status: string, countered_offer: int}
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

            if ($offer['offer_status'] !== 'open') {
                throw new RuntimeException('Only open offers can be countered.');
            }

            $sellerId = (int) $offer['seller_id'];
            $buyerId = $this->resolveBuyerId($offerId, $sellerId);

            if ($actorId !== $sellerId && ($buyerId === null || $actorId !== $buyerId)) {
                throw new RuntimeException('You are not part of this negotiation.');
            }

            if ($actorId === (int) $offer['initiator_id']) {
                throw new RuntimeException('You cannot counter your own offer.');
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

            $update = $this->conn->prepare('UPDATE purchase_offers SET status = ? WHERE id = ?');
            if ($update === false) {
                throw new RuntimeException('Failed to update the original offer.');
            }

            $statusCountered = 'countered';
            $update->bind_param('si', $statusCountered, $offerId);
            if (!$update->execute()) {
                $update->close();
                throw new RuntimeException('Failed to update the original offer.');
            }
            $update->close();

            $stmt = $this->conn->prepare(
                'INSERT INTO purchase_offers (listing_id, initiator_id, counter_of, quantity, offer_price) VALUES (?,?,?,?,?)'
            );
            if ($stmt === false) {
                throw new RuntimeException('Failed to record the counteroffer.');
            }

            $priceString = $this->formatPrice($price);
            $stmt->bind_param('iiiis', $offer['listing_id'], $actorId, $offerId, $quantity, $priceString);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to record the counteroffer.');
            }

            $newOfferId = (int) $stmt->insert_id;
            $stmt->close();

            $this->conn->commit();

            shop_log('purchase_offer.countered', [
                'offer_id' => $newOfferId,
                'countered_offer' => $offerId,
                'listing_id' => $offer['listing_id'],
                'actor_id' => $actorId,
                'quantity' => $quantity,
                'offer_price' => $priceString,
            ]);

            return [
                'offer_id' => $newOfferId,
                'status' => 'open',
                'countered_offer' => $offerId,
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
        return $this->closeOffer($actorId, $offerId, 'accepted');
    }

    /**
     * Decline an open offer.
     */
    public function declineOffer(int $actorId, int $offerId): array
    {
        return $this->closeOffer($actorId, $offerId, 'declined');
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

            if ($offer['offer_status'] !== 'open') {
                throw new RuntimeException('Only open offers can be cancelled.');
            }

            if ($actorId !== (int) $offer['initiator_id']) {
                throw new RuntimeException('You can only cancel offers you created.');
            }

            $status = 'cancelled';
            $stmt = $this->conn->prepare('UPDATE purchase_offers SET status = ? WHERE id = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to cancel the offer.');
            }

            $stmt->bind_param('si', $status, $offerId);
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
            ]);

            return [
                'offer_id' => $offerId,
                'status' => $status,
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

        if (!in_array($status, ['accepted', 'declined'], true)) {
            throw new InvalidArgumentException('Unsupported status change.');
        }

        $this->conn->begin_transaction();

        try {
            $offer = $this->fetchOfferWithListing($offerId, true);
            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            if ($offer['offer_status'] !== 'open') {
                throw new RuntimeException('This offer is no longer open.');
            }

            $sellerId = (int) $offer['seller_id'];
            $buyerId = $this->resolveBuyerId($offerId, $sellerId);

            if ($actorId === (int) $offer['initiator_id']) {
                throw new RuntimeException('You cannot ' . $status . ' your own offer.');
            }

            if ($actorId !== $sellerId && ($buyerId === null || $actorId !== $buyerId)) {
                throw new RuntimeException('You are not part of this negotiation.');
            }

            $stmt = $this->conn->prepare('UPDATE purchase_offers SET status = ? WHERE id = ?');
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
        $sql = 'SELECT id, owner_id, status, quantity, reserved_qty, original_price, price FROM listings WHERE id = ?';
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
        $sql = 'SELECT '
            . 'po.id, po.listing_id, po.initiator_id, po.counter_of, po.quantity AS offer_quantity, '
            . 'po.offer_price, po.status AS offer_status, po.expires_at, po.created_at, '
            . 'l.owner_id AS seller_id, l.status AS listing_status, '
            . 'l.quantity AS listing_quantity, l.reserved_qty AS listing_reserved_qty, '
            . 'l.original_price AS listing_original_price, l.price AS listing_price '
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

    /**
     * Determine the buyer involved in this offer chain.
     */
    private function resolveBuyerId(int $offerId, int $sellerId): ?int
    {
        $visited = [];
        $currentId = $offerId;
        $sql = 'SELECT initiator_id, counter_of FROM purchase_offers WHERE id = ? LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to load offer context.');
        }

        try {
            while ($currentId !== null && !in_array($currentId, $visited, true)) {
                $visited[] = $currentId;

                $stmt->bind_param('i', $currentId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Failed to load offer context.');
                }

                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $result->free();

                if (!$row) {
                    return null;
                }

                $initiatorId = (int) $row['initiator_id'];
                if ($initiatorId !== $sellerId) {
                    return $initiatorId;
                }

                $next = $row['counter_of'] ?? null;
                if ($next === null) {
                    return null;
                }

                $currentId = (int) $next;
                if ($currentId <= 0) {
                    $currentId = null;
                }
            }
        } finally {
            $stmt->close();
        }

        return null;
    }
}
