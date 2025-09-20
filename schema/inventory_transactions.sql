-- SQL schema reference for inventory transactions
CREATE TABLE inventory_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_sku VARCHAR(64) NOT NULL,
    owner_id INT NOT NULL,
    transaction_type VARCHAR(32) NOT NULL,
    quantity_change INT NOT NULL,
    quantity_before INT DEFAULT NULL,
    quantity_after INT DEFAULT NULL,
    reference_type VARCHAR(32) DEFAULT NULL,
    reference_id BIGINT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_inventory_transactions_product ON inventory_transactions (product_sku);
CREATE INDEX idx_inventory_transactions_owner ON inventory_transactions (owner_id);
CREATE INDEX idx_inventory_transactions_reference ON inventory_transactions (reference_type, reference_id);
