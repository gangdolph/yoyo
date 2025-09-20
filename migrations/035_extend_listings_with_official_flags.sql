ALTER TABLE listings
    MODIFY COLUMN product_sku VARCHAR(64) DEFAULT NULL,
    ADD COLUMN is_official_listing TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD INDEX idx_listings_product_sku (product_sku);

UPDATE listings AS l
INNER JOIN products AS p ON p.sku = l.product_sku
   SET l.is_official_listing = CASE WHEN p.is_skuze_official = 1 OR p.is_official = 1 THEN 1 ELSE l.is_official_listing END;
