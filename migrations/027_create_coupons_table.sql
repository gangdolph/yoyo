CREATE TABLE coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  code VARCHAR(50) NOT NULL,
  discount_type ENUM('percentage','fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  expires_at DATETIME DEFAULT NULL,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  UNIQUE KEY unique_coupon_code (listing_id, code)
);
