-- Seed canonical service brands and models for core hardware categories.

-- Phones
INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Apple') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Apple');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'iPhone 15 Pro'
FROM service_brands b
WHERE b.name = 'Apple'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'iPhone 15 Pro');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'iPhone 14'
FROM service_brands b
WHERE b.name = 'Apple'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'iPhone 14');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Samsung') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Samsung');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Galaxy S23 Ultra'
FROM service_brands b
WHERE b.name = 'Samsung'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Galaxy S23 Ultra');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Galaxy Z Flip5'
FROM service_brands b
WHERE b.name = 'Samsung'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Galaxy Z Flip5');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Google') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Google');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Pixel 8 Pro'
FROM service_brands b
WHERE b.name = 'Google'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Pixel 8 Pro');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Pixel 7a'
FROM service_brands b
WHERE b.name = 'Google'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Pixel 7a');

-- Consoles & handhelds
INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Sony') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Sony');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'PlayStation 5'
FROM service_brands b
WHERE b.name = 'Sony'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'PlayStation 5');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'PlayStation 4 Pro'
FROM service_brands b
WHERE b.name = 'Sony'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'PlayStation 4 Pro');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Microsoft') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Microsoft');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Xbox Series X'
FROM service_brands b
WHERE b.name = 'Microsoft'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Xbox Series X');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Xbox Series S'
FROM service_brands b
WHERE b.name = 'Microsoft'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Xbox Series S');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Nintendo') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Nintendo');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Switch OLED'
FROM service_brands b
WHERE b.name = 'Nintendo'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Switch OLED');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Switch Lite'
FROM service_brands b
WHERE b.name = 'Nintendo'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Switch Lite');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Valve') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Valve');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Steam Deck OLED'
FROM service_brands b
WHERE b.name = 'Valve'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Steam Deck OLED');

-- Laptops & PCs
INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Dell') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Dell');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'XPS 13 Plus'
FROM service_brands b
WHERE b.name = 'Dell'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'XPS 13 Plus');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Alienware m16'
FROM service_brands b
WHERE b.name = 'Dell'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Alienware m16');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Lenovo') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Lenovo');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'ThinkPad X1 Carbon'
FROM service_brands b
WHERE b.name = 'Lenovo'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'ThinkPad X1 Carbon');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Legion Pro 7i'
FROM service_brands b
WHERE b.name = 'Lenovo'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Legion Pro 7i');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'HP') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'HP');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Spectre x360 13.5'
FROM service_brands b
WHERE b.name = 'HP'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Spectre x360 13.5');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Omen 16'
FROM service_brands b
WHERE b.name = 'HP'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Omen 16');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'ASUS') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'ASUS');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'ROG Zephyrus G14'
FROM service_brands b
WHERE b.name = 'ASUS'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'ROG Zephyrus G14');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Zenbook 14 OLED'
FROM service_brands b
WHERE b.name = 'ASUS'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Zenbook 14 OLED');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'MSI') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'MSI');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Raider GE78 HX'
FROM service_brands b
WHERE b.name = 'MSI'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Raider GE78 HX');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Stealth 14 Studio'
FROM service_brands b
WHERE b.name = 'MSI'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Stealth 14 Studio');

-- GPUs
INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'NVIDIA') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'NVIDIA');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'GeForce RTX 4090'
FROM service_brands b
WHERE b.name = 'NVIDIA'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'GeForce RTX 4090');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'GeForce RTX 4080 Super'
FROM service_brands b
WHERE b.name = 'NVIDIA'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'GeForce RTX 4080 Super');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'AMD') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'AMD');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Radeon RX 7900 XTX'
FROM service_brands b
WHERE b.name = 'AMD'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Radeon RX 7900 XTX');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Radeon RX 7700 XT'
FROM service_brands b
WHERE b.name = 'AMD'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Radeon RX 7700 XT');

-- CPUs
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Ryzen 9 7950X'
FROM service_brands b
WHERE b.name = 'AMD'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Ryzen 9 7950X');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Ryzen 7 7800X3D'
FROM service_brands b
WHERE b.name = 'AMD'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Ryzen 7 7800X3D');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Intel') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Intel');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Core i9-13900K'
FROM service_brands b
WHERE b.name = 'Intel'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Core i9-13900K');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Core i7-13700K'
FROM service_brands b
WHERE b.name = 'Intel'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Core i7-13700K');

-- Components & accessories
INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Logitech') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Logitech');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'G Pro X Superlight 2'
FROM service_brands b
WHERE b.name = 'Logitech'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'G Pro X Superlight 2');

INSERT INTO service_brands (name)
SELECT * FROM (SELECT 'Elgato') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM service_brands WHERE name = 'Elgato');
INSERT INTO service_models (brand_id, name)
SELECT b.id, 'Stream Deck MK.2'
FROM service_brands b
WHERE b.name = 'Elgato'
  AND NOT EXISTS (SELECT 1 FROM service_models sm WHERE sm.brand_id = b.id AND sm.name = 'Stream Deck MK.2');
