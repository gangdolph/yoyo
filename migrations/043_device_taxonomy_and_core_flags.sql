-- Device taxonomy tables and feature flag defaults.
-- Ensures brands/models exist and product/listing tables have link columns with indexes.

CREATE TABLE IF NOT EXISTS brands (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_brands_slug (slug)
);

CREATE TABLE IF NOT EXISTS models (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    attributes JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_models_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_models_slug (slug),
    KEY idx_models_brand_id (brand_id)
);

-- Ensure inventory reserve columns are present before positioning taxonomy fields
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS reserved INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS reserved_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS brand_id INT UNSIGNED NULL AFTER reserved,
    ADD COLUMN IF NOT EXISTS model_id INT UNSIGNED NULL AFTER brand_id;

ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS brand_id INT UNSIGNED NULL AFTER product_sku,
    ADD COLUMN IF NOT EXISTS model_id INT UNSIGNED NULL AFTER brand_id;

-- Guarded foreign keys for products and listings
SET @product_brand_fk := (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_products_brand');
SET @sql := IF(@product_brand_fk IS NULL,
    'ALTER TABLE products ADD CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @product_model_fk := (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_products_model');
SET @sql := IF(@product_model_fk IS NULL,
    'ALTER TABLE products ADD CONSTRAINT fk_products_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @listing_brand_fk := (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND CONSTRAINT_NAME = 'fk_listings_brand');
SET @sql := IF(@listing_brand_fk IS NULL,
    'ALTER TABLE listings ADD CONSTRAINT fk_listings_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @listing_model_fk := (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND CONSTRAINT_NAME = 'fk_listings_model');
SET @sql := IF(@listing_model_fk IS NULL,
    'ALTER TABLE listings ADD CONSTRAINT fk_listings_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for taxonomy driven filters
SET @idx := (SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_brand_id');
SET @sql := IF(@idx IS NULL, 'CREATE INDEX idx_products_brand_id ON products(brand_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_model_id');
SET @sql := IF(@idx IS NULL, 'CREATE INDEX idx_products_model_id ON products(model_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND INDEX_NAME = 'idx_listings_brand_id');
SET @sql := IF(@idx IS NULL, 'CREATE INDEX idx_listings_brand_id ON listings(brand_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND INDEX_NAME = 'idx_listings_model_id');
SET @sql := IF(@idx IS NULL, 'CREATE INDEX idx_listings_model_id ON listings(model_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Wallet tables aligned to new naming if legacy tables not present
CREATE TABLE IF NOT EXISTS user_wallets (
    user_id INT NOT NULL PRIMARY KEY,
    balance_cents BIGINT NOT NULL DEFAULT 0,
    pending_cents BIGINT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_wallets_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wallet_ledger_v2 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('credit','debit','hold','release','payout','fee','refund','adjust') NOT NULL,
    amount_cents BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    reference_type VARCHAR(64) NULL,
    reference_id BIGINT NULL,
    balance_after BIGINT NOT NULL,
    memo TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_ledger_v2_user_created (user_id, created_at),
    INDEX idx_wallet_ledger_v2_reference (reference_type, reference_id),
    CONSTRAINT fk_wallet_ledger_v2_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount_cents BIGINT NOT NULL,
    provider ENUM('paypal','square','venmo','cashapp','ach','manual') NOT NULL DEFAULT 'manual',
    provider_account VARCHAR(255) NOT NULL,
    status ENUM('requested','approved','processing','paid','failed','cancelled') NOT NULL DEFAULT 'requested',
    fee_cents BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_withdrawal_requests_user FOREIGN KEY (user_id) REFERENCES users(id)
);

