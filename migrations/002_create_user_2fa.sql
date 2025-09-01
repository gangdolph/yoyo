CREATE TABLE IF NOT EXISTS user_2fa (
  user_id INT PRIMARY KEY,
  secret VARCHAR(32) NOT NULL,
  recovery_code VARCHAR(32) NOT NULL,
  CONSTRAINT fk_user_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
