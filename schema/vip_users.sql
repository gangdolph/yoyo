-- SQL schema to add VIP fields to users table
ALTER TABLE users
  ADD COLUMN vip_status TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN vip_expires_at DATETIME DEFAULT NULL;
