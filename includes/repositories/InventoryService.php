<?php
/*
 * Discovery note: Inventory adjustments only touched product stock and skipped linked listings.
 * Change: Synced product updates across associated listings while retaining ledger logging and
 *         now expose a Square webhook reconciliation path that records ledger entries.
 */
declare(strict_types=1);

require_once __DIR__ . '/ShopLogger.php';
require_once __DIR__ . '/ListingsRepo.php';

final class InventoryService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Apply an inventory delta against a product SKU with permission checks.
     *
     * @return array{sku: string, stock: int, quantity: ?int, reorder_threshold: int}
     */
    public function adjustProductStock(
        string $sku,
        int $delta,
        int $actorId,
        ?int $reorderThreshold,
        bool $isAdmin,
        bool $isOfficial
    ): array {
        if ($sku === '') {
            throw new RuntimeException('A product SKU is required.');
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'SELECT owner_id, stock, quantity, reorder_threshold, is_skuze_official FROM products WHERE sku = ? FOR UPDATE'
            );
            if ($stmt === false) {
                throw new RuntimeException('Unable to load inventory record.');
            }
            $stmt->bind_param('s', $sku);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Unable to load inventory record.');
            }

            $stmt->bind_result($ownerId, $stock, $quantity, $threshold, $isOfficialProduct);
            if (!$stmt->fetch()) {
                $stmt->close();
                throw new RuntimeException('Inventory record not found.');
            }
            $stmt->close();

            $ownerId = (int) $ownerId;
            $currentStock = (int) $stock;
            $currentQuantity = $quantity !== null ? (int) $quantity : null;
            $currentThreshold = (int) $threshold;
            $isOfficialProduct = (bool) $isOfficialProduct;

            $canManage = $ownerId === $actorId || $isAdmin || ($isOfficial && $isOfficialProduct);
            if (!$canManage) {
                throw new RuntimeException('You do not have permission to adjust this inventory item.');
            }

            $newStock = max(0, $currentStock + $delta);
            $newQuantity = $currentQuantity !== null ? max(0, $currentQuantity + $delta) : null;
            $nextThreshold = $reorderThreshold ?? $currentThreshold;
            if ($nextThreshold < 0) {
                $nextThreshold = 0;
            }

            if ($currentQuantity !== null) {
                $stmt = $this->conn->prepare('UPDATE products SET stock = ?, quantity = ?, reorder_threshold = ? WHERE sku = ?');
                if ($stmt === false) {
                    throw new RuntimeException('Unable to update inventory.');
                }
                $stmt->bind_param('iiis', $newStock, $newQuantity, $nextThreshold, $sku);
            } else {
                $stmt = $this->conn->prepare('UPDATE products SET stock = ?, reorder_threshold = ? WHERE sku = ?');
                if ($stmt === false) {
                    throw new RuntimeException('Unable to update inventory.');
                }
                $stmt->bind_param('iis', $newStock, $nextThreshold, $sku);
            }

            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to update inventory.');
            }
            $stmt->close();

            $listingStmt = $this->conn->prepare(
                'UPDATE listings '
                . 'SET quantity = CASE WHEN quantity IS NULL THEN NULL ELSE GREATEST(quantity + ?, 0) END, '
                . 'updated_at = NOW() WHERE product_sku = ?'
            );
            if ($listingStmt !== false) {
                $listingStmt->bind_param('is', $delta, $sku);
                $listingStmt->execute();
                $listingStmt->close();
            }

            $reconcile = $this->conn->prepare(
                'UPDATE listings SET reserved_qty = LEAST(reserved_qty, quantity) '
                . 'WHERE product_sku = ? AND quantity IS NOT NULL'
            );
            if ($reconcile !== false) {
                $reconcile->bind_param('s', $sku);
                $reconcile->execute();
                $reconcile->close();
            }

            $this->recordInventoryTransaction(
                $sku,
                $ownerId,
                'manual_adjustment',
                $delta,
                $currentStock,
                $newStock,
                null,
                null,
                ['actor_id' => $actorId]
            );

            $this->conn->commit();

            shop_log('inventory.adjusted', [
                'sku' => $sku,
                'actor_id' => $actorId,
                'delta' => $delta,
                'stock' => $newStock,
            ]);

            return [
                'sku' => $sku,
                'stock' => $newStock,
                'quantity' => $newQuantity,
                'reorder_threshold' => $nextThreshold,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('inventory.error', [
                'sku' => $sku,
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reserve listing quantity while a checkout is in-flight.
     *
     * @return array{listing_id: int, reserved_qty: int, quantity: int}
     */
    public function reserveListing(int $listingId, int $quantity, int $actorId): array
    {
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $this->conn->begin_transaction();
        try {
            $repo = new ListingsRepo($this->conn);
            $listing = $repo->fetchListing($listingId, true);
            if (!$listing) {
                throw new RuntimeException('Listing not found.');
            }

            $status = (string) ($listing['status'] ?? 'draft');
            if (!in_array($status, ['approved', 'live'], true)) {
                throw new RuntimeException('Listing is not available for reservation.');
            }

            $available = (int) $listing['quantity'] - (int) $listing['reserved_qty'];
            if ($available < $quantity) {
                throw new RuntimeException('Insufficient inventory for this listing.');
            }

            $stmt = $this->conn->prepare('UPDATE listings SET reserved_qty = reserved_qty + ?, updated_at = NOW() WHERE id = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to reserve listing quantity.');
            }

            $stmt->bind_param('ii', $quantity, $listingId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to reserve listing quantity.');
            }
            $stmt->close();

            $this->conn->commit();

            shop_log('listing.reserved', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'quantity' => $quantity,
            ]);

            return [
                'listing_id' => $listingId,
                'reserved_qty' => (int) $listing['reserved_qty'] + $quantity,
                'quantity' => (int) $listing['quantity'],
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('listing.reserve_error', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Release a reservation when checkout fails.
     */
    public function releaseListing(int $listingId, int $quantity, int $actorId): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('UPDATE listings SET reserved_qty = GREATEST(reserved_qty - ?, 0), updated_at = NOW() WHERE id = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to release reservation.');
            }
            $stmt->bind_param('ii', $quantity, $listingId);
            $stmt->execute();
            $stmt->close();
            $this->conn->commit();

            shop_log('listing.reservation_released', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'quantity' => $quantity,
            ]);
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('listing.release_error', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Capture a reservation as fulfilled and decrement stock.
     */
    public function captureListing(int $listingId, int $quantity, int $actorId, ?int $referenceId = null): array
    {
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $this->conn->begin_transaction();
        try {
            $repo = new ListingsRepo($this->conn);
            $listing = $repo->fetchListing($listingId, true);
            if (!$listing) {
                throw new RuntimeException('Listing not found.');
            }

            $reserved = (int) $listing['reserved_qty'];
            if ($reserved < $quantity) {
                throw new RuntimeException('Not enough reserved inventory to capture.');
            }

            $stmt = $this->conn->prepare('UPDATE listings SET reserved_qty = GREATEST(reserved_qty - ?, 0), quantity = GREATEST(quantity - ?, 0), updated_at = NOW() WHERE id = ?');
            if ($stmt === false) {
                throw new RuntimeException('Failed to update listing quantities.');
            }
            $stmt->bind_param('iii', $quantity, $quantity, $listingId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to update listing quantities.');
            }
            $stmt->close();

            $productSku = $listing['product_sku'] ?? null;
            $ownerId = (int) $listing['owner_id'];
            $afterReserved = max(0, $reserved - $quantity);
            $afterQuantity = max(0, (int) $listing['quantity'] - $quantity);

            if ($productSku) {
                $stmt = $this->conn->prepare('UPDATE products SET stock = GREATEST(stock - ?, 0), quantity = CASE WHEN quantity IS NULL THEN NULL ELSE GREATEST(quantity - ?, 0) END WHERE sku = ?');
                if ($stmt !== false) {
                    $stmt->bind_param('iis', $quantity, $quantity, $productSku);
                    $stmt->execute();
                    $stmt->close();
                }

                $this->recordInventoryTransaction(
                    (string) $productSku,
                    $ownerId,
                    'sale_capture',
                    -$quantity,
                    null,
                    null,
                    'order',
                    $referenceId,
                    ['actor_id' => $actorId, 'listing_id' => $listingId]
                );
            }

            $this->conn->commit();

            shop_log('listing.reservation_captured', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'quantity' => $quantity,
                'reserved_remaining' => $afterReserved,
                'quantity_remaining' => $afterQuantity,
            ]);

            return [
                'listing_id' => $listingId,
                'reserved_qty' => $afterReserved,
                'quantity' => $afterQuantity,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('listing.capture_error', [
                'listing_id' => $listingId,
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function recordInventoryTransaction(
        string $sku,
        int $ownerId,
        string $type,
        int $delta,
        ?int $before,
        ?int $after,
        ?string $referenceType,
        ?int $referenceId,
        array $metadata
    ): void {
        $stmt = $this->conn->prepare(
            'INSERT INTO inventory_transactions (product_sku, owner_id, transaction_type, quantity_change, quantity_before, quantity_after, reference_type, reference_id, metadata) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt === false) {
            return;
        }

        $json = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt->bind_param(
            'sisiiisis',
            $sku,
            $ownerId,
            $type,
            $delta,
            $before,
            $after,
            $referenceType,
            $referenceId,
            $json
        );
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Synchronise a product's stock level against an authoritative external value.
     *
     * @return array{sku: string, stock: int, quantity: ?int, delta: int}|null
     */
    public function reconcileExternalStock(
        string $sku,
        int $authoritativeStock,
        string $referenceType,
        ?int $referenceId,
        array $metadata
    ): ?array {
        if ($sku === '') {
            return null;
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'SELECT owner_id, stock, quantity FROM products WHERE sku = ? FOR UPDATE'
            );
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare inventory lookup.');
            }

            $stmt->bind_param('s', $sku);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Unable to fetch inventory for reconciliation.');
            }

            $stmt->bind_result($ownerId, $stock, $quantity);
            if (!$stmt->fetch()) {
                $stmt->close();
                $this->conn->rollback();
                return null;
            }
            $stmt->close();

            $ownerId = (int) $ownerId;
            $currentStock = (int) $stock;
            $currentQuantity = $quantity !== null ? (int) $quantity : null;

            $targetStock = max(0, $authoritativeStock);
            $targetQuantity = $currentQuantity !== null ? $targetStock : null;
            $delta = $targetStock - $currentStock;

            if ($currentQuantity !== null) {
                $update = $this->conn->prepare(
                    'UPDATE products SET stock = ?, quantity = ?, updated_at = NOW() WHERE sku = ?'
                );
                if ($update === false) {
                    throw new RuntimeException('Unable to update product inventory.');
                }
                $update->bind_param('iis', $targetStock, $targetQuantity, $sku);
            } else {
                $update = $this->conn->prepare('UPDATE products SET stock = ?, updated_at = NOW() WHERE sku = ?');
                if ($update === false) {
                    throw new RuntimeException('Unable to update product inventory.');
                }
                $update->bind_param('is', $targetStock, $sku);
            }

            if (!$update->execute()) {
                $update->close();
                throw new RuntimeException('Failed to persist reconciled inventory.');
            }
            $update->close();

            $listingUpdate = $this->conn->prepare(
                'UPDATE listings SET quantity = ?, updated_at = NOW() WHERE product_sku = ?'
            );
            if ($listingUpdate !== false) {
                $listingUpdate->bind_param('is', $targetStock, $sku);
                $listingUpdate->execute();
                $listingUpdate->close();
            }

            $reconcileReserved = $this->conn->prepare(
                'UPDATE listings SET reserved_qty = LEAST(reserved_qty, quantity) WHERE product_sku = ?'
            );
            if ($reconcileReserved !== false) {
                $reconcileReserved->bind_param('s', $sku);
                $reconcileReserved->execute();
                $reconcileReserved->close();
            }

            if ($delta !== 0) {
                $this->recordInventoryTransaction(
                    $sku,
                    $ownerId,
                    'square_webhook_sync',
                    $delta,
                    $currentStock,
                    $targetStock,
                    $referenceType,
                    $referenceId,
                    $metadata
                );
            }

            $this->conn->commit();

            return [
                'sku' => $sku,
                'stock' => $targetStock,
                'quantity' => $targetQuantity,
                'delta' => $delta,
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            shop_log('inventory.sync_error', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'reference_type' => $referenceType,
                'metadata' => $metadata,
            ]);
            throw $e;
        }
    }
}
