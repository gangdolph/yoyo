-- SQL schema for trade listings feature

CREATE TABLE trade_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    have_item VARCHAR(255) NOT NULL,
    want_item VARCHAR(255) NOT NULL,
    status ENUM('open','accepted','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE trade_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    offerer_id INT NOT NULL,
    offered_item_id INT NOT NULL,
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
    FOREIGN KEY (offered_item_id) REFERENCES inventory_items(id)
);

CREATE TABLE escrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    status ENUM('initiated','funded','released','refunded') DEFAULT 'initiated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES trade_offers(id)
);
