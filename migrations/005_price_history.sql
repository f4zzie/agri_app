-- ============================================================
-- 005 – Price history ledger, maintained by triggers
--
-- Every time a listing gets a price (insert) or its price/unit/name
-- changes (update), a row is appended here. The price trends page
-- aggregates it by product, month and county.
--
-- Design note: this is an append-only ledger of *observed market
-- prices*. product_key, unit and county_id record what was true when
-- the price was observed, so they are historical facts, not copies that
-- must stay in sync. crop_id is SET NULL when a listing is deleted, so
-- market history survives listings coming and going.
-- ============================================================

CREATE TABLE price_history (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  crop_id      INT               NULL,
  product_key  VARCHAR(100)      NOT NULL COMMENT 'LOWER(TRIM(crops.name)) at observation time',
  unit         VARCHAR(50)       NOT NULL,
  county_id    TINYINT UNSIGNED  NULL,
  price        DECIMAL(10,2)     NOT NULL,
  recorded_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ph_crop   FOREIGN KEY (crop_id)   REFERENCES crops(id)    ON DELETE SET NULL,
  CONSTRAINT fk_ph_county FOREIGN KEY (county_id) REFERENCES counties(id),
  CONSTRAINT chk_ph_price_positive CHECK (price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill from current listings
INSERT INTO price_history (crop_id, product_key, unit, county_id, price, recorded_at)
SELECT c.id, LOWER(TRIM(c.name)), c.unit, u.county_id, c.price, c.created_at
  FROM crops c JOIN users u ON u.id = c.user_id
 WHERE c.price IS NOT NULL AND c.price > 0;

-- Single-statement trigger bodies (no BEGIN…END), so this file needs no
-- DELIMITER and imports cleanly through phpMyAdmin too.
CREATE TRIGGER trg_crops_price_after_insert
AFTER INSERT ON crops FOR EACH ROW
INSERT INTO price_history (crop_id, product_key, unit, county_id, price)
SELECT NEW.id, LOWER(TRIM(NEW.name)), NEW.unit, u.county_id, NEW.price
  FROM users u
 WHERE u.id = NEW.user_id AND NEW.price > 0;

CREATE TRIGGER trg_crops_price_after_update
AFTER UPDATE ON crops FOR EACH ROW
INSERT INTO price_history (crop_id, product_key, unit, county_id, price)
SELECT NEW.id, LOWER(TRIM(NEW.name)), NEW.unit, u.county_id, NEW.price
  FROM users u
 WHERE u.id = NEW.user_id
   AND NEW.price > 0
   AND (NOT (OLD.price <=> NEW.price)
        OR OLD.unit <> NEW.unit
        OR LOWER(TRIM(OLD.name)) <> LOWER(TRIM(NEW.name)));
