-- ============================================================
-- 004 – Normalise inquiries into conversation threads + ratings
--
-- Problems with v1 `inquiries`:
--   * buyer_name / buyer_email copied users.name / users.email, so a
--     profile change left stale copies (update anomaly).
--   * farmer_id duplicated crops.user_id (transitive dependency:
--     inquiry -> crop -> farmer), so the two could disagree.
--   * One message per inquiry: no way to reply inside the app.
--
-- New design:
--   inquiries  one conversation per (crop, buyer)  [UNIQUE]
--   messages   every message in a conversation, from either party
--   ratings    one 1–5 rating per completed conversation
--
-- inquiries.contact_phone is kept on purpose: it is the number the buyer
-- chose for *this* deal (may differ from their profile phone), i.e. a
-- deliberate snapshot, not a copy of users.phone.
-- ============================================================

CREATE TABLE messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id  INT       NOT NULL,
  sender_id   INT       NOT NULL,
  body        TEXT      NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender  FOREIGN KEY (sender_id)  REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT chk_msg_body_not_blank CHECK (CHAR_LENGTH(TRIM(body)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. Every v1 inquiry message becomes the first message of its thread
INSERT INTO messages (inquiry_id, sender_id, body, created_at)
SELECT id, buyer_id, message, created_at FROM inquiries;

-- 2. Merge duplicate inquiries (same buyer, same crop) into the oldest one
UPDATE messages m
  JOIN inquiries i ON i.id = m.inquiry_id
  JOIN (SELECT crop_id, buyer_id, MIN(id) AS keep_id
          FROM inquiries GROUP BY crop_id, buyer_id) k
    ON k.crop_id = i.crop_id AND k.buyer_id = i.buyer_id
   SET m.inquiry_id = k.keep_id;

DELETE i FROM inquiries i
  JOIN (SELECT crop_id, buyer_id, MIN(id) AS keep_id
          FROM inquiries GROUP BY crop_id, buyer_id) k
    ON k.crop_id = i.crop_id AND k.buyer_id = i.buyer_id
 WHERE i.id <> k.keep_id;

-- 3. Drop the redundant / moved columns
ALTER TABLE inquiries
  DROP FOREIGN KEY fk_inq_farmer;

ALTER TABLE inquiries
  DROP COLUMN farmer_id,
  DROP COLUMN message,
  DROP COLUMN buyer_name,
  DROP COLUMN buyer_email,
  CHANGE buyer_phone contact_phone VARCHAR(20) NULL
    COMMENT 'Snapshot: phone the buyer gave for this deal',
  MODIFY status ENUM('pending','read','responded','completed') NOT NULL DEFAULT 'pending',
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD CONSTRAINT uq_inquiry_crop_buyer UNIQUE (crop_id, buyer_id);

UPDATE inquiries SET updated_at = created_at;

-- 4. Ratings: one per completed deal. The buyer (rater) and farmer (ratee)
--    are derived through inquiries -> crops, so they are not stored again.
CREATE TABLE ratings (
  inquiry_id  INT              PRIMARY KEY,
  score       TINYINT UNSIGNED NOT NULL,
  comment     VARCHAR(500)     NULL,
  created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rating_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
  CONSTRAINT chk_rating_score CHECK (score BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
