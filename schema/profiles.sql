CREATE TABLE profiles (
  user_id INT PRIMARY KEY,
  avatar_path VARCHAR(255),
  is_private TINYINT(1) NOT NULL DEFAULT 0,
  bio TEXT,
  show_bio TINYINT(1) NOT NULL DEFAULT 1,
  show_friends TINYINT(1) NOT NULL DEFAULT 1,
  show_listings TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
