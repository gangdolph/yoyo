-- SQL schema reference for the optional Square catalog map
CREATE TABLE square_catalog_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    local_type ENUM('product','listing') NOT NULL,
    local_id INT NOT NULL,
    square_object_id VARCHAR(255) NOT NULL,
    square_catalog_version BIGINT DEFAULT NULL,
    sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uniq_square_catalog_map_local ON square_catalog_map (local_type, local_id);
CREATE UNIQUE INDEX uniq_square_catalog_map_square ON square_catalog_map (square_object_id);
CREATE INDEX idx_square_catalog_map_sync_status ON square_catalog_map (sync_status);
