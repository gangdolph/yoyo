-- SQL schema for trade listings feature

CREATE TABLE trade_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    have_sku VARCHAR(64) NOT NULL,
    want_sku VARCHAR(64) NOT NULL,
    trade_type ENUM('item','cash_card') DEFAULT 'item',
    description TEXT,
    image VARCHAR(255),
    status ENUM('open','accepted','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (have_sku) REFERENCES products(sku),
    FOREIGN KEY (want_sku) REFERENCES products(sku)
);

CREATE TABLE trade_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    offerer_id INT NOT NULL,
    offered_sku VARCHAR(64) DEFAULT NULL,
    payment_amount DECIMAL(10,2) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    message TEXT,
    use_escrow BOOLEAN DEFAULT 0,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    fulfillment_type ENUM('meetup','ship_to_skuzE'),
    shipping_address TEXT,
    meeting_location VARCHAR(255),
    tracking_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES trade_listings(id),
    FOREIGN KEY (offerer_id) REFERENCES users(id),
    FOREIGN KEY (offered_sku) REFERENCES products(sku)
);

CREATE TABLE escrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    status ENUM('initiated','funded','verified','released','refunded') DEFAULT 'initiated',
    verified_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES trade_offers(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);
