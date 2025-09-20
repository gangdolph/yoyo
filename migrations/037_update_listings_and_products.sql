-- Normalize legacy listing statuses before updating the enum definition
UPDATE listings
   SET status = 'delisted'
 WHERE status = 'rejected';

-- Extend listings with new lifecycle statuses and inventory fields
ALTER TABLE listings
    MODIFY COLUMN status ENUM('draft','pending','approved','live','closed','delisted') NOT NULL DEFAULT 'draft',
    ADD COLUMN IF NOT EXISTS quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER price,
    ADD COLUMN IF NOT EXISTS reserved_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Ensure stored quantities remain in a valid range
UPDATE listings
   SET quantity = GREATEST(quantity, 1)
 WHERE quantity < 1;

UPDATE listings
   SET reserved_qty = LEAST(reserved_qty, quantity)
 WHERE reserved_qty > quantity;

-- Add the missing audit columns to products
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
