CREATE TABLE IF NOT EXISTS listing_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    requester_id INT NOT NULL,
    reviewer_id INT DEFAULT NULL,
    change_type VARCHAR(32) NOT NULL DEFAULT 'status',
    change_summary TEXT DEFAULT NULL,
    requested_status ENUM('draft','pending','approved','live','closed','delisted') DEFAULT NULL,
    payload JSON DEFAULT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    review_notes TEXT DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lcr_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_lcr_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_lcr_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_lcr_listing_status (listing_id, status),
    INDEX idx_lcr_requester (requester_id),
    INDEX idx_lcr_reviewer (reviewer_id)
);

CREATE TABLE IF NOT EXISTS inventory_transactions (
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
    CONSTRAINT fk_inventory_tx_product FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_tx_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_inventory_tx_product (product_sku),
    INDEX idx_inventory_tx_owner (owner_id),
    INDEX idx_inventory_tx_reference (reference_type, reference_id)
);

-- Optional Square catalog integration guard. Set @feature_square_catalog_map = 0 before
-- running the migration to skip creating this table.
SET @feature_square_catalog_map = COALESCE(@feature_square_catalog_map, 1);

SET @square_catalog_map_sql = IF(
    @feature_square_catalog_map = 1,
    'CREATE TABLE IF NOT EXISTS square_catalog_map (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        local_type ENUM(''product'',''listing'') NOT NULL,
        local_id INT NOT NULL,
        square_object_id VARCHAR(255) NOT NULL,
        square_catalog_version BIGINT DEFAULT NULL,
        sync_status ENUM(''pending'',''synced'',''failed'') NOT NULL DEFAULT ''pending'',
        last_synced_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_square_catalog_map_local (local_type, local_id),
        UNIQUE KEY uniq_square_catalog_map_square (square_object_id),
        KEY idx_square_catalog_map_sync_status (sync_status)
    )',
    'SELECT 1'
);

PREPARE create_square_catalog_map FROM @square_catalog_map_sql;
EXECUTE create_square_catalog_map;
DEALLOCATE PREPARE create_square_catalog_map;
