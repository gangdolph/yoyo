ALTER TABLE users
  ADD COLUMN account_type ENUM('standard','business') NOT NULL DEFAULT 'standard',
  ADD COLUMN company_name VARCHAR(255) DEFAULT NULL,
  ADD COLUMN company_website VARCHAR(255) DEFAULT NULL,
  ADD COLUMN company_logo VARCHAR(255) DEFAULT NULL;
