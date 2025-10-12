-- Migration: redefine purchase_offers with buyer/seller roles and messaging support.
-- Ensures the schema matches the latest negotiation model while remaining idempotent.

-- Drop legacy version of the table if it still has the old initiator-based columns.
SET @needs_reset := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'purchase_offers'
      AND COLUMN_NAME IN ('initiator_id', 'counter_of', 'offer_price')
);

SET @drop_sql := IF(@needs_reset > 0, 'DROP TABLE purchase_offers', 'SELECT 0');
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS purchase_offers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    buyer_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    offered_price DECIMAL(10,2) NOT NULL,
    message TEXT NULL,
    status ENUM('pending_seller','pending_buyer','accepted','declined','cancelled','expired') NOT NULL DEFAULT 'pending_seller',
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_purchase_offers_listing ON purchase_offers(listing_id);
CREATE INDEX IF NOT EXISTS idx_purchase_offers_status ON purchase_offers(status);
CREATE INDEX IF NOT EXISTS idx_purchase_offers_buyer ON purchase_offers(buyer_id);
CREATE INDEX IF NOT EXISTS idx_purchase_offers_seller ON purchase_offers(seller_id);
