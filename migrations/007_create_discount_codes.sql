CREATE TABLE discount_codes (
  code VARCHAR(50) PRIMARY KEY,
  percent_off INT NOT NULL,
  expiry DATE NOT NULL,
  usage_limit INT NOT NULL
);
