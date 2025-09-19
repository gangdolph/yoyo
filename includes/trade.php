<?php
declare(strict_types=1);

require_once __DIR__ . '/inventory.php';

if (!function_exists('trade_log_event')) {
    /**
     * Record a trade-related event for auditing and state tracking.
     *
     * @param array<string,mixed> $metadata
     */
    function trade_log_event(mysqli $conn, int $offerId, string $eventType, ?int $actorId = null, array $metadata = []): void
    {
        $eventType = trim($eventType);
        if ($eventType === '') {
            return;
        }

        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        if ($actorId === null && $metadataJson === null) {
            $stmt = $conn->prepare('INSERT INTO trade_events (offer_id, event_type) VALUES (?, ?)');
            $stmt->bind_param('is', $offerId, $eventType);
        } elseif ($actorId === null) {
            $stmt = $conn->prepare('INSERT INTO trade_events (offer_id, event_type, metadata) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $offerId, $eventType, $metadataJson);
        } elseif ($metadataJson === null) {
            $stmt = $conn->prepare('INSERT INTO trade_events (offer_id, actor_id, event_type) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $offerId, $actorId, $eventType);
        } else {
            $stmt = $conn->prepare('INSERT INTO trade_events (offer_id, actor_id, event_type, metadata) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiss', $offerId, $actorId, $eventType, $metadataJson);
        }

        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('trade_create_offer')) {
    /**
     * Create a new trade offer while reserving any offered inventory.
     *
     * @param array<string,mixed> $payload
     */
    function trade_create_offer(mysqli $conn, int $listingId, int $offererId, array $payload): int
    {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare('SELECT id, owner_id, trade_type, status, have_sku FROM trade_listings WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $listingId);
            $stmt->execute();
            $listing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$listing) {
                throw new RuntimeException('Listing not found.');
            }

            if ((int)$listing['owner_id'] === $offererId) {
                throw new RuntimeException('You cannot make an offer on your own listing.');
            }

            if (($listing['status'] ?? '') !== 'open') {
                throw new RuntimeException('This listing is no longer accepting offers.');
            }

            $tradeType = (string)($listing['trade_type'] ?? 'item');

            $offeredSku = null;
            if ($tradeType === 'item') {
                $offeredSku = trim((string)($payload['offered_sku'] ?? ''));
                if ($offeredSku === '') {
                    throw new RuntimeException('Please choose an item to offer.');
                }

                inventory_reserve_owned_product($conn, $offererId, $offeredSku);
            }

            $paymentAmountValue = null;
            $paymentMethodValue = null;
            if ($tradeType === 'cash_card') {
                $paymentAmount = isset($payload['payment_amount']) ? (float)$payload['payment_amount'] : null;
                $paymentMethod = trim((string)($payload['payment_method'] ?? ''));

                if ($paymentAmount === null || $paymentAmount <= 0) {
                    throw new RuntimeException('Enter a valid payment amount.');
                }

                if ($paymentMethod === '') {
                    throw new RuntimeException('Select a payment method.');
                }

                $paymentAmountValue = number_format($paymentAmount, 2, '.', '');
                $paymentMethodValue = $paymentMethod;
            }

            $message = trim((string)($payload['message'] ?? ''));
            $messageValue = $message !== '' ? $message : null;
            $useEscrow = !empty($payload['use_escrow']) ? 1 : 0;

            $stmt = $conn->prepare('INSERT INTO trade_offers (listing_id, offerer_id, offered_sku, payment_amount, payment_method, message, use_escrow) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param(
                'iissssi',
                $listingId,
                $offererId,
                $offeredSku,
                $paymentAmountValue,
                $paymentMethodValue,
                $messageValue,
                $useEscrow
            );
            $stmt->execute();
            $offerId = (int)$stmt->insert_id;
            $stmt->close();

            trade_log_event($conn, $offerId, 'offer_created', $offererId, [
                'listing_id' => $listingId,
                'trade_type' => $tradeType,
                'use_escrow' => (bool)$useEscrow,
            ]);

            if ($tradeType === 'item' && $offeredSku !== null) {
                trade_log_event($conn, $offerId, 'inventory_reserved', $offererId, ['sku' => $offeredSku]);
            }

            $conn->commit();

            return $offerId;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('trade_accept_offer')) {
    /**
     * Accept a pending offer, consuming reserved inventory and updating related records.
     */
    function trade_accept_offer(mysqli $conn, int $offerId, int $actorId): void
    {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                'SELECT o.id, o.listing_id, o.offerer_id, o.offered_sku, o.use_escrow, o.status, '
                . 'l.owner_id, l.have_sku, l.trade_type, l.status AS listing_status '
                . 'FROM trade_offers o '
                . 'JOIN trade_listings l ON o.listing_id = l.id '
                . 'WHERE o.id = ? FOR UPDATE'
            );
            $stmt->bind_param('i', $offerId);
            $stmt->execute();
            $offer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            if ((int)$offer['owner_id'] !== $actorId) {
                throw new RuntimeException('You are not allowed to accept this offer.');
            }

            if (($offer['status'] ?? '') !== 'pending') {
                throw new RuntimeException('Only pending offers can be accepted.');
            }

            if (($offer['listing_status'] ?? '') !== 'open') {
                throw new RuntimeException('This listing is no longer open for offers.');
            }

            $tradeType = (string)($offer['trade_type'] ?? 'item');
            $haveSku = $offer['have_sku'] ?? null;
            $offeredSku = $offer['offered_sku'] ?? null;

            if ($tradeType === 'item') {
                if (!$offeredSku) {
                    throw new RuntimeException('Offer is missing the offered inventory SKU.');
                }

                $offererId = (int)$offer['offerer_id'];
                $offeredProduct = inventory_fetch_owned_product($conn, $offererId, (string)$offeredSku, true);
                if (!$offeredProduct) {
                    throw new RuntimeException('The offered item is no longer available.');
                }

                if ((int)$offeredProduct['reserved'] === 0) {
                    inventory_reserve_owned_product($conn, $offererId, (string)$offeredSku);
                    trade_log_event($conn, $offerId, 'inventory_reserved', $offererId, [
                        'sku' => $offeredSku,
                        'party' => 'offerer',
                        'context' => 'acceptance',
                    ]);
                }

                if ($haveSku) {
                    inventory_reserve_owned_product($conn, $actorId, (string)$haveSku);
                    trade_log_event($conn, $offerId, 'inventory_reserved', $actorId, [
                        'sku' => $haveSku,
                        'party' => 'listing_owner',
                        'context' => 'acceptance',
                    ]);
                }
            }

            $stmt = $conn->prepare(
                'UPDATE trade_offers o '
                . 'JOIN trade_listings l ON o.listing_id = l.id '
                . 'SET o.status = "accepted", l.status = "accepted" '
                . 'WHERE o.id = ? AND o.status = "pending" AND l.owner_id = ?'
            );
            $stmt->bind_param('ii', $offerId, $actorId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected !== 1) {
                throw new RuntimeException('Offer could not be accepted.');
            }

            if ($tradeType === 'item' && $offeredSku) {
                inventory_consume_reserved_product($conn, (int)$offer['offerer_id'], (string)$offeredSku);
                trade_log_event($conn, $offerId, 'inventory_consumed', $actorId, ['sku' => $offeredSku, 'party' => 'offerer']);

                if ($haveSku) {
                    inventory_consume_reserved_product($conn, $actorId, (string)$haveSku);
                    trade_log_event($conn, $offerId, 'inventory_consumed', $actorId, ['sku' => $haveSku, 'party' => 'listing_owner']);
                }
            }

            if (!empty($offer['use_escrow'])) {
                $stmt = $conn->prepare('INSERT INTO escrow_transactions (offer_id) VALUES (?)');
                $stmt->bind_param('i', $offerId);
                $stmt->execute();
                $stmt->close();
            }

            trade_log_event($conn, $offerId, 'offer_accepted', $actorId, ['listing_id' => (int)$offer['listing_id']]);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('trade_decline_offer')) {
    /**
     * Decline or expire a pending offer, releasing any reserved inventory.
     */
    function trade_decline_offer(mysqli $conn, int $offerId, int $actorId, string $reason = 'declined'): void
    {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                'SELECT o.id, o.listing_id, o.offerer_id, o.offered_sku, o.status, '
                . 'l.owner_id, l.trade_type '
                . 'FROM trade_offers o '
                . 'JOIN trade_listings l ON o.listing_id = l.id '
                . 'WHERE o.id = ? FOR UPDATE'
            );
            $stmt->bind_param('i', $offerId);
            $stmt->execute();
            $offer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$offer) {
                throw new RuntimeException('Offer not found.');
            }

            if ((int)$offer['owner_id'] !== $actorId && $reason === 'declined') {
                throw new RuntimeException('You are not allowed to decline this offer.');
            }

            if (($offer['status'] ?? '') !== 'pending') {
                throw new RuntimeException('Only pending offers can be updated.');
            }

            $stmt = $conn->prepare(
                'UPDATE trade_offers o '
                . 'JOIN trade_listings l ON o.listing_id = l.id '
                . 'SET o.status = "declined" '
                . 'WHERE o.id = ? AND o.status = "pending" AND l.owner_id = ?'
            );
            $stmt->bind_param('ii', $offerId, $offer['owner_id']);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected !== 1) {
                throw new RuntimeException('Offer could not be updated.');
            }

            $tradeType = (string)($offer['trade_type'] ?? 'item');
            $offeredSku = $offer['offered_sku'] ?? null;
            if ($tradeType === 'item' && $offeredSku) {
                inventory_release_product($conn, (string)$offeredSku);
                trade_log_event($conn, $offerId, 'inventory_released', $actorId, ['sku' => $offeredSku, 'reason' => $reason]);
            }

            trade_log_event($conn, $offerId, 'offer_declined', $actorId, ['reason' => $reason]);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('trade_release_offer_inventory')) {
    /**
     * Release reserved inventory for an offer without altering its status (used for admin cleanup).
     */
    function trade_release_offer_inventory(mysqli $conn, int $offerId, ?int $actorId = null, string $reason = 'manual_release'): void
    {
        $stmt = $conn->prepare(
            'SELECT o.offered_sku, o.status, l.trade_type '
            . 'FROM trade_offers o '
            . 'JOIN trade_listings l ON o.listing_id = l.id '
            . 'WHERE o.id = ?'
        );
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$offer) {
            return;
        }

        if (($offer['status'] ?? '') === 'pending'
            && ($offer['trade_type'] ?? 'item') === 'item'
            && !empty($offer['offered_sku'])
        ) {
            inventory_release_product($conn, (string)$offer['offered_sku']);
            trade_log_event($conn, $offerId, 'inventory_released', $actorId, [
                'sku' => $offer['offered_sku'],
                'reason' => $reason,
            ]);
        }
    }
}
