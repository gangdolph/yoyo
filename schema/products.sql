-- SQL schema for products
CREATE TABLE products (
    sku VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    quantity INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    reserved TINYINT(1) NOT NULL DEFAULT 0,
    owner_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reorder_threshold INT NOT NULL DEFAULT 0,
    is_official TINYINT(1) NOT NULL DEFAULT 0,
    is_skuze_official TINYINT(1) NOT NULL DEFAULT 0,
    is_skuze_product TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);
