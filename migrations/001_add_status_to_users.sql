ALTER TABLE users
  ADD COLUMN status ENUM('online','offline','busy') NOT NULL DEFAULT 'offline';
