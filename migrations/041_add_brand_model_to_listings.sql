ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER product_sku,
    ADD COLUMN IF NOT EXISTS model_id INT NULL AFTER brand_id,
    ADD CONSTRAINT fk_listings_brand FOREIGN KEY (brand_id) REFERENCES service_brands(id),
    ADD CONSTRAINT fk_listings_model FOREIGN KEY (model_id) REFERENCES service_models(id);

CREATE INDEX IF NOT EXISTS idx_listings_brand_id ON listings(brand_id);
CREATE INDEX IF NOT EXISTS idx_listings_model_id ON listings(model_id);

ALTER TABLE trade_listings
    ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER want_item,
    ADD COLUMN IF NOT EXISTS model_id INT NULL AFTER brand_id,
    ADD CONSTRAINT fk_trade_listings_brand FOREIGN KEY (brand_id) REFERENCES service_brands(id),
    ADD CONSTRAINT fk_trade_listings_model FOREIGN KEY (model_id) REFERENCES service_models(id);

CREATE INDEX IF NOT EXISTS idx_trade_listings_brand_id ON trade_listings(brand_id);
CREATE INDEX IF NOT EXISTS idx_trade_listings_model_id ON trade_listings(model_id);
