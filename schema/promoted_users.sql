-- SQL schema to flag promoted shops

ALTER TABLE users
    ADD COLUMN promoted TINYINT(1) DEFAULT 0,
    ADD COLUMN promoted_expires DATETIME;
