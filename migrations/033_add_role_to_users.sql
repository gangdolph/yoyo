ALTER TABLE users
    ADD COLUMN role ENUM('user','admin','skuze_official') NOT NULL DEFAULT 'user' AFTER status;

UPDATE users
   SET role = 'admin'
 WHERE is_admin = 1;

UPDATE users
   SET role = 'skuze_official'
 WHERE role = 'user' AND LOWER(status) IN ('skuze official', 'official');
