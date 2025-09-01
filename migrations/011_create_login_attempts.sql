CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  attempt_time DATETIME NOT NULL,
  INDEX idx_ip_time (ip, attempt_time)
);
