CREATE TABLE tokens (
  user_id INT NOT NULL,
  token VARCHAR(255) PRIMARY KEY,
  type VARCHAR(50) NOT NULL,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
