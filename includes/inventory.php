<?php
declare(strict_types=1);

/**
 * Inventory helper functions for reserving and consuming user-owned products.
 */

if (!function_exists('inventory_fetch_owned_product')) {
    /**
     * Fetch a product owned by the given user.
     *
     * @return array<string,mixed>|null
     */
    function inventory_fetch_owned_product(mysqli $conn, int $ownerId, string $sku, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT sku, owner_id, quantity, reserved FROM products WHERE sku = ? AND owner_id = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $sku, $ownerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $product ?: null;
    }
}

if (!function_exists('inventory_reserve_owned_product')) {
    /**
     * Reserve an owned product so it cannot be offered elsewhere while a trade is pending.
     */
    function inventory_reserve_owned_product(mysqli $conn, int $ownerId, string $sku): void
    {
        $product = inventory_fetch_owned_product($conn, $ownerId, $sku, true);
        if (!$product) {
            throw new RuntimeException('Selected item could not be found.');
        }

        if ((int)$product['quantity'] <= 0) {
            throw new RuntimeException('Selected item is out of stock.');
        }

        if ((int)$product['reserved'] === 1) {
            throw new RuntimeException('Selected item is already reserved.');
        }

        $stmt = $conn->prepare('UPDATE products SET reserved = 1 WHERE sku = ? AND reserved = 0');
        $stmt->bind_param('s', $sku);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new RuntimeException('Unable to reserve the selected item.');
        }
    }
}

if (!function_exists('inventory_release_product')) {
    /**
     * Release any reservation on the given SKU.
     */
    function inventory_release_product(mysqli $conn, string $sku): void
    {
        $stmt = $conn->prepare('UPDATE products SET reserved = 0 WHERE sku = ?');
        $stmt->bind_param('s', $sku);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('inventory_consume_reserved_product')) {
    /**
     * Consume one quantity from an owned product and clear any reservation flag.
     */
    function inventory_consume_reserved_product(mysqli $conn, int $ownerId, string $sku): void
    {
        $product = inventory_fetch_owned_product($conn, $ownerId, $sku, true);
        if (!$product) {
            throw new RuntimeException('Inventory record missing for the selected item.');
        }

        if ((int)$product['quantity'] <= 0) {
            throw new RuntimeException('No remaining quantity for the selected item.');
        }

        $stmt = $conn->prepare('UPDATE products SET quantity = quantity - 1, reserved = 0 WHERE sku = ? AND quantity > 0');
        $stmt->bind_param('s', $sku);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new RuntimeException('Failed to consume inventory for the selected item.');
        }
    }
}
