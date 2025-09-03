ALTER TABLE trade_offers
  MODIFY offer_item TEXT NULL,
  ADD COLUMN offer_items TEXT NULL,
  ADD COLUMN request_items TEXT NULL;

CREATE TABLE user_inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
