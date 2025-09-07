ALTER TABLE escrow_transactions
    ADD COLUMN verified_by INT DEFAULT NULL,
    ADD CONSTRAINT fk_verified_by FOREIGN KEY (verified_by) REFERENCES users(id);
ALTER TABLE escrow_transactions
    MODIFY status ENUM('initiated','funded','verified','released','refunded') DEFAULT 'initiated';
