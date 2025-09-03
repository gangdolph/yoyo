CREATE TABLE order_fulfillments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    shipping_address TEXT NOT NULL,
    delivery_method VARCHAR(100) NOT NULL,
    notes TEXT,
    tracking_number VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (listing_id) REFERENCES listings(id)
);
