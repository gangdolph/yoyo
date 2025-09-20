-- SQL schema reference for listings
CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    product_sku VARCHAR(64) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    `condition` VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    reserved_qty INT UNSIGNED NOT NULL DEFAULT 0,
    category VARCHAR(50),
    tags TEXT DEFAULT NULL,
    image VARCHAR(255) NOT NULL,
    status ENUM('draft','pending','approved','live','closed','delisted') NOT NULL DEFAULT 'draft',
    is_official_listing TINYINT(1) NOT NULL DEFAULT 0,
    pickup_only TINYINT(1) NOT NULL DEFAULT 0,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_sku) REFERENCES products(sku)
);

CREATE INDEX idx_listings_product_sku ON listings (product_sku);
CREATE INDEX idx_listings_tags ON listings (tags(191));
