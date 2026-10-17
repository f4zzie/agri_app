-- ============================================================
-- 002 – Lookup tables for counties and product categories
--
-- users.county and crops.category were free text validated only in PHP
-- (the 47-county list was duplicated in two files). They become foreign
-- keys to small reference tables, so the database itself guarantees
-- that a county/category exists and spelling can't drift.
-- ============================================================

CREATE TABLE counties (
  id   TINYINT UNSIGNED PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Official county codes 1–47 (Constitution of Kenya, First Schedule)
INSERT INTO counties (id, name) VALUES
 (1,'Mombasa'),(2,'Kwale'),(3,'Kilifi'),(4,'Tana River'),(5,'Lamu'),
 (6,'Taita-Taveta'),(7,'Garissa'),(8,'Wajir'),(9,'Mandera'),(10,'Marsabit'),
 (11,'Isiolo'),(12,'Meru'),(13,'Tharaka-Nithi'),(14,'Embu'),(15,'Kitui'),
 (16,'Machakos'),(17,'Makueni'),(18,'Nyandarua'),(19,'Nyeri'),(20,'Kirinyaga'),
 (21,'Murang''a'),(22,'Kiambu'),(23,'Turkana'),(24,'West Pokot'),(25,'Samburu'),
 (26,'Trans Nzoia'),(27,'Uasin Gishu'),(28,'Elgeyo-Marakwet'),(29,'Nandi'),(30,'Baringo'),
 (31,'Laikipia'),(32,'Nakuru'),(33,'Narok'),(34,'Kajiado'),(35,'Kericho'),
 (36,'Bomet'),(37,'Kakamega'),(38,'Vihiga'),(39,'Bungoma'),(40,'Busia'),
 (41,'Siaya'),(42,'Kisumu'),(43,'Homa Bay'),(44,'Migori'),(45,'Kisii'),
 (46,'Nyamira'),(47,'Nairobi');

CREATE TABLE categories (
  id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name) VALUES
 ('Grains & Cereals'),('Vegetables'),('Fruits'),('Dairy & Eggs'),('Meat & Poultry'),
 ('Herbs & Spices'),('Legumes & Pulses'),('Roots & Tubers'),('Other');

-- users.county (text) -> users.county_id (FK)
ALTER TABLE users ADD COLUMN county_id TINYINT UNSIGNED NULL AFTER phone;

UPDATE users u JOIN counties c ON c.name = u.county SET u.county_id = c.id;

ALTER TABLE users
  DROP COLUMN county,
  ADD CONSTRAINT fk_users_county FOREIGN KEY (county_id) REFERENCES counties(id);

-- crops.category (text) -> crops.category_id (FK)
ALTER TABLE crops ADD COLUMN category_id TINYINT UNSIGNED NULL AFTER variety;

UPDATE crops cr JOIN categories c ON c.name = cr.category SET cr.category_id = c.id;

ALTER TABLE crops
  DROP COLUMN category,
  ADD CONSTRAINT fk_crops_category FOREIGN KEY (category_id) REFERENCES categories(id);
