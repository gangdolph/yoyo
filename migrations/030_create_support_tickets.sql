CREATE TABLE support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('open','pending','closed') DEFAULT 'open',
  assigned_to INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

ALTER TABLE message_requests
  ADD COLUMN support_ticket_id INT DEFAULT NULL AFTER body,
  ADD COLUMN category VARCHAR(32) DEFAULT NULL AFTER support_ticket_id,
  ADD INDEX idx_message_requests_support_ticket (support_ticket_id),
  ADD INDEX idx_message_requests_category (category),
  ADD CONSTRAINT fk_message_requests_support_ticket
    FOREIGN KEY (support_ticket_id) REFERENCES support_tickets(id)
    ON DELETE SET NULL;
