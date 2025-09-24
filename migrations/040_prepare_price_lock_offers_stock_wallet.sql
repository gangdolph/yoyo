-- Migration: prepare schema for price locks, offers, stock reservations, and wallet withdrawals.
-- Ensures idempotent deployment for shared environments.

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2) NULL AFTER price;

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS quantity INT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS reserved_qty INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS purchase_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    initiator_id INT NOT NULL,
    counter_of INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    offer_price DECIMAL(10,2) NOT NULL,
    status ENUM('open','countered','accepted','declined','expired','cancelled') NOT NULL DEFAULT 'open',
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (initiator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (counter_of) REFERENCES purchase_offers(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_purchase_offers_listing ON purchase_offers(listing_id);

CREATE INDEX IF NOT EXISTS idx_purchase_offers_status ON purchase_offers(status);

CREATE TABLE IF NOT EXISTS wallet_withdrawals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount_cents INT NOT NULL,
    fee_cents INT NOT NULL DEFAULT 0,
    status ENUM('pending','approved','processing','paid','rejected','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    idempotency_key VARCHAR(64) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_wallet_withdrawals_idempotency ON wallet_withdrawals(idempotency_key);

UPDATE users SET status = 'member' WHERE status = 'vip';
UPDATE users SET role = 'member' WHERE role = 'vip';
