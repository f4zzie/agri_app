-- ============================================================
-- 007 – Integrity constraints and reporting views
--
-- Business rules that were only checked in PHP are now also enforced
-- by the database, so no code path (or manual SQL) can break them.
-- Rows that would violate a new rule are repaired first.
-- ============================================================

-- ---------- Repair data that the new rules would reject ----------
UPDATE crops SET price = NULL            WHERE price <= 0;
UPDATE crops SET quantity = NULL         WHERE quantity < 0;
UPDATE crops SET expected_harvest = NULL WHERE expected_harvest < planting_date;
UPDATE crops SET unit = 'kg'
 WHERE unit IS NULL OR unit NOT IN ('kg','bag','crate','litre','dozen','piece','tonne','bundle');
UPDATE crops SET name = 'Unnamed product' WHERE CHAR_LENGTH(TRIM(name)) = 0;
UPDATE users SET name = 'Unnamed user'    WHERE CHAR_LENGTH(TRIM(name)) = 0;
UPDATE users SET email_verified = 0      WHERE email_verified IS NULL;
UPDATE users SET role = 'farmer'         WHERE role IS NULL;

-- ---------- crops ----------
ALTER TABLE crops
  MODIFY unit       VARCHAR(50) NOT NULL DEFAULT 'kg',
  MODIFY created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD CONSTRAINT chk_crops_name_not_blank CHECK (CHAR_LENGTH(TRIM(name)) > 0),
  ADD CONSTRAINT chk_crops_price_positive CHECK (price IS NULL OR price > 0),
  ADD CONSTRAINT chk_crops_quantity_nonneg CHECK (quantity IS NULL OR quantity >= 0),
  ADD CONSTRAINT chk_crops_unit CHECK (unit IN ('kg','bag','crate','litre','dozen','piece','tonne','bundle')),
  ADD CONSTRAINT chk_crops_harvest_after_planting
      CHECK (expected_harvest IS NULL OR planting_date IS NULL OR expected_harvest >= planting_date);

-- ---------- users ----------
ALTER TABLE users
  MODIFY email_verified TINYINT(1) NOT NULL DEFAULT 0,
  MODIFY role           ENUM('farmer','buyer') NOT NULL DEFAULT 'farmer',
  MODIFY created_at     TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD CONSTRAINT chk_users_name_not_blank CHECK (CHAR_LENGTH(TRIM(name)) > 0);

-- ---------- inquiries / activity ----------
ALTER TABLE inquiries
  MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE activity_log
  MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- Views
-- ============================================================

-- Every farmer listing with the names a page needs to display it.
-- Pages filter this view instead of repeating a four-table join.
CREATE OR REPLACE VIEW v_marketplace AS
SELECT c.id, c.user_id AS farmer_id, c.name, c.variety,
       c.category_id, cat.name AS category,
       c.price, c.unit, c.quantity, c.status, c.growth_stage,
       c.planting_date, c.expected_harvest, c.notes, c.image, c.created_at,
       u.name AS farmer_name, u.phone AS farmer_phone, u.email AS farmer_email,
       u.county_id, co.name AS farmer_county
  FROM crops c
  JOIN users u           ON u.id   = c.user_id AND u.role = 'farmer'
  LEFT JOIN categories cat ON cat.id = c.category_id
  LEFT JOIN counties co    ON co.id  = u.county_id;

-- Reputation per farmer, from ratings on completed deals.
CREATE OR REPLACE VIEW v_farmer_ratings AS
SELECT c.user_id                 AS farmer_id,
       ROUND(AVG(r.score), 1)    AS avg_rating,
       COUNT(*)                  AS rating_count
  FROM ratings r
  JOIN inquiries i ON i.id = r.inquiry_id
  JOIN crops c     ON c.id = i.crop_id
 GROUP BY c.user_id;

-- One row per conversation, with both parties and the latest message.
CREATE OR REPLACE VIEW v_inquiry_threads AS
SELECT i.id, i.crop_id, i.buyer_id, c.user_id AS farmer_id, i.status, i.contact_phone,
       i.created_at, c.name AS crop_name,
       b.name AS buyer_name, f.name AS farmer_name,
       lm.id AS last_message_id, lm.body AS last_body,
       lm.sender_id AS last_sender_id, lm.created_at AS last_message_at,
       (SELECT COUNT(*) FROM messages m WHERE m.inquiry_id = i.id) AS message_count,
       r.score AS rating
  FROM inquiries i
  JOIN crops c ON c.id = i.crop_id
  JOIN users b ON b.id = i.buyer_id
  JOIN users f ON f.id = c.user_id
  LEFT JOIN messages lm ON lm.id = (SELECT MAX(m2.id) FROM messages m2 WHERE m2.inquiry_id = i.id)
  LEFT JOIN ratings r   ON r.inquiry_id = i.id;
