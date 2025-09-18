ALTER TABLE listings
  ADD tags TEXT NULL AFTER category;

CREATE INDEX idx_listings_tags ON listings (tags(191));
