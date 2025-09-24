-- Migration: introduce wallet accounts, ledger, holds, and optional audit log.
-- Guards ensure idempotent deployment on shared environments.

CREATE TABLE IF NOT EXISTS wallet_accounts (
    user_id INT NOT NULL PRIMARY KEY,
    available_cents INT NOT NULL DEFAULT 0,
    pending_cents INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_accounts_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wallet_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    entry_type ENUM('top_up','debit','credit','hold','release','refund','adjust') NOT NULL,
    amount_cents INT NOT NULL,
    sign ENUM('+','-') NOT NULL,
    balance_after_cents INT NOT NULL,
    related_type VARCHAR(32) NULL,
    related_id BIGINT NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_wallet_ledger_idempotency UNIQUE (idempotency_key),
    INDEX idx_wallet_ledger_user_created (user_id, created_at),
    CONSTRAINT fk_wallet_ledger_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wallet_holds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    amount_cents INT NOT NULL,
    status ENUM('held','released','cancelled') NOT NULL DEFAULT 'held',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    UNIQUE KEY uq_wallet_holds_order (order_id),
    CONSTRAINT fk_wallet_holds_order FOREIGN KEY (order_id) REFERENCES order_fulfillments(id),
    CONSTRAINT fk_wallet_holds_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
    CONSTRAINT fk_wallet_holds_seller FOREIGN KEY (seller_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wallet_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    action VARCHAR(32) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_audit_actor_created (actor_user_id, created_at),
    CONSTRAINT fk_wallet_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
);

ALTER TABLE wallet_accounts
    ADD COLUMN IF NOT EXISTS pending_cents INT NOT NULL DEFAULT 0;

ALTER TABLE wallet_accounts
    ADD COLUMN IF NOT EXISTS available_cents INT NOT NULL DEFAULT 0;

