-- Migrate legacy inventory_items into products and update trade_offers
INSERT INTO products (sku, title, description, quantity, owner_id, price)
SELECT CONCAT('LEG-', id), name, description, quantity, user_id, 0.00 FROM inventory_items;

ALTER TABLE trade_offers ADD offered_sku VARCHAR(64) DEFAULT NULL;
UPDATE trade_offers o JOIN inventory_items i ON o.offered_item_id = i.id
SET o.offered_sku = CONCAT('LEG-', i.id);
ALTER TABLE trade_offers DROP FOREIGN KEY trade_offers_ibfk_3;
ALTER TABLE trade_offers DROP COLUMN offered_item_id;
ALTER TABLE trade_offers MODIFY offered_sku VARCHAR(64) NOT NULL;
ALTER TABLE trade_offers ADD CONSTRAINT fk_trade_offers_product FOREIGN KEY (offered_sku) REFERENCES products(sku);

RENAME TABLE inventory_items TO inventory_items_legacy;
