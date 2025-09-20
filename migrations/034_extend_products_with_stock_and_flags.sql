ALTER TABLE products
    ADD COLUMN stock INT NOT NULL DEFAULT 0 AFTER quantity,
    ADD COLUMN is_skuze_official TINYINT(1) NOT NULL DEFAULT 0 AFTER is_official,
    ADD COLUMN is_skuze_product TINYINT(1) NOT NULL DEFAULT 0 AFTER is_skuze_official;

UPDATE products
   SET stock = quantity
 WHERE stock = 0;

UPDATE products
   SET is_skuze_official = is_official
 WHERE is_skuze_official = 0 AND is_official = 1;
