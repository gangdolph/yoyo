ALTER TABLE trade_listings ADD trade_type ENUM('item','cash_card') DEFAULT 'item';
ALTER TABLE trade_offers ADD payment_amount DECIMAL(10,2) DEFAULT NULL, ADD payment_method VARCHAR(50) DEFAULT NULL;
ALTER TABLE trade_offers MODIFY offered_sku VARCHAR(64) DEFAULT NULL;
