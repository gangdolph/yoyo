CREATE TABLE user_shipping_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(2) NOT NULL DEFAULT 'US',
    phone VARCHAR(30) DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

ALTER TABLE order_fulfillments
    ADD COLUMN shipping_profile_id INT DEFAULT NULL AFTER user_id,
    ADD COLUMN shipping_snapshot TEXT DEFAULT NULL AFTER shipping_address,
    ADD COLUMN is_official_order TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD CONSTRAINT fk_order_fulfillments_shipping_profile FOREIGN KEY (shipping_profile_id) REFERENCES user_shipping_profiles(id);

UPDATE order_fulfillments AS of
SET of.shipping_snapshot = JSON_OBJECT(
        'address', of.shipping_address,
        'delivery_method', of.delivery_method,
        'notes', of.notes
    )
WHERE of.shipping_snapshot IS NULL;
