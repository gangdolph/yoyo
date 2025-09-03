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

CREATE TABLE trade_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    offerer_id INT NOT NULL,
    message TEXT,
    offer_item TEXT,
    offer_items TEXT,
    request_items TEXT,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    fulfillment_type ENUM('meetup','ship_to_skuzE'),
    shipping_address TEXT,
    meeting_location VARCHAR(255),
    tracking_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES trade_listings(id),
    FOREIGN KEY (offerer_id) REFERENCES users(id)
);

CREATE TABLE user_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
