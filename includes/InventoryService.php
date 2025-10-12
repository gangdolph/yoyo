<?php
/**
 * Central inventory mutations with ledger writes.
 *
 * Guard InventoryService declaration to avoid redeclaration during multiple includes.
 */
declare(strict_types=1);

if (!class_exists('InventoryService')) {
    // Guarded class definition to avoid redeclaration during multiple includes.
    final class InventoryService
    {
        private mysqli $db;
    
        public function __construct(mysqli $db)
        {
            $this->db = $db;
        }
    
        public function reserve(string $sku, int $quantity, array $reference): array
        {
            if ($quantity <= 0) {
                throw new InvalidArgumentException('Reserve quantity must be positive.');
            }
    
            $this->db->begin_transaction();
            try {
                $product = $this->lockProduct($sku);
                $available = max(0, (int) $product['quantity'] - (int) $product['reserved']);
                if ($available < $quantity) {
                    throw new RuntimeException('Insufficient inventory to reserve.');
                }
    
                $updatedReserved = (int) $product['reserved'] + $quantity;
                $this->updateProduct($sku, (int) $product['quantity'], $updatedReserved);
                $this->recordTransaction($product, 'reserve', $quantity * -1, $product['reserved'], $updatedReserved, $reference);
    
                $this->db->commit();
    
                return ['quantity' => (int) $product['quantity'], 'reserved' => $updatedReserved];
            } catch (Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        }
    
        public function release(string $sku, int $quantity, array $reference): array
        {
            if ($quantity <= 0) {
                throw new InvalidArgumentException('Release quantity must be positive.');
            }
    
            $this->db->begin_transaction();
            try {
                $product = $this->lockProduct($sku);
                $newReserved = max(0, (int) $product['reserved'] - $quantity);
                $this->updateProduct($sku, (int) $product['quantity'], $newReserved);
                $this->recordTransaction($product, 'release', $quantity, $product['reserved'], $newReserved, $reference);
    
                $this->db->commit();
    
                return ['quantity' => (int) $product['quantity'], 'reserved' => $newReserved];
            } catch (Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        }
    
        public function decrement(string $sku, int $quantity, array $reference): array
        {
            if ($quantity <= 0) {
                throw new InvalidArgumentException('Decrement quantity must be positive.');
            }
    
            $this->db->begin_transaction();
            try {
                $product = $this->lockProduct($sku);
                if ((int) $product['reserved'] < $quantity) {
                    throw new RuntimeException('Cannot decrement more than reserved quantity.');
                }
    
                $newReserved = (int) $product['reserved'] - $quantity;
                $newQuantity = max(0, (int) $product['quantity'] - $quantity);
                $this->updateProduct($sku, $newQuantity, $newReserved);
                $this->recordTransaction($product, 'decrement', $quantity * -1, $product['quantity'], $newQuantity, $reference);
    
                $this->db->commit();
    
                return ['quantity' => $newQuantity, 'reserved' => $newReserved];
            } catch (Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        }
    
        public function adjust(string $sku, int $delta, string $reason, array $reference): array
        {
            if ($delta === 0) {
                throw new InvalidArgumentException('Adjustment must be non-zero.');
            }
    
            $this->db->begin_transaction();
            try {
                $product = $this->lockProduct($sku);
                $newQuantity = max(0, (int) $product['quantity'] + $delta);
                $newReserved = min($newQuantity, (int) $product['reserved']);
                $this->updateProduct($sku, $newQuantity, $newReserved);
                $this->recordTransaction($product, $reason, $delta, $product['quantity'], $newQuantity, $reference);
    
                $this->db->commit();
    
                return ['quantity' => $newQuantity, 'reserved' => $newReserved];
            } catch (Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        }
    
        private function lockProduct(string $sku): array
        {
            $stmt = $this->db->prepare('SELECT sku, owner_id, quantity, reserved FROM products WHERE sku = ? FOR UPDATE');
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare inventory lock.');
            }
    
            $stmt->bind_param('s', $sku);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Unable to lock inventory.');
            }
    
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
    
            if (!$row) {
                throw new RuntimeException('Inventory record not found.');
            }
    
            return $row;
        }
    
        private function updateProduct(string $sku, int $quantity, int $reserved): void
        {
            $stmt = $this->db->prepare('UPDATE products SET quantity = ?, reserved = ?, updated_at = NOW() WHERE sku = ?');
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare inventory update.');
            }
    
            $stmt->bind_param('iis', $quantity, $reserved, $sku);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Unable to update inventory: ' . $error);
            }
    
            $stmt->close();
        }
    
        private function recordTransaction(array $product, string $type, int $delta, $before, $after, array $reference): void
        {
            $metadata = json_encode([
                'reference' => $reference,
            ]);
    
            $referenceType = $reference['type'] ?? null;
            $referenceId = $reference['id'] ?? null;
    
            $stmt = $this->db->prepare('INSERT INTO inventory_transactions (product_sku, owner_id, transaction_type, quantity_change, quantity_before, quantity_after, reference_type, reference_id, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare inventory ledger insert.');
            }
    
            $ownerId = (int) $product['owner_id'];
            $beforeQty = is_int($before) ? $before : (int) $before;
            $afterQty = is_int($after) ? $after : (int) $after;
            $stmt->bind_param(
                'sisiiisis',
                $product['sku'],
                $ownerId,
                $type,
                $delta,
                $beforeQty,
                $afterQty,
                $referenceType,
                $referenceId,
                $metadata
            );
    
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Unable to record inventory transaction: ' . $error);
            }
    
            $stmt->close();
        }
    }
}
