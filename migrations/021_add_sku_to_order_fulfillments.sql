ALTER TABLE order_fulfillments
  ADD COLUMN sku VARCHAR(64) NOT NULL AFTER listing_id,
  ADD CONSTRAINT fk_order_fulfillments_product FOREIGN KEY (sku) REFERENCES products(sku);
