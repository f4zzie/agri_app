-- ============================================================
-- 003 – Separate listing availability from growth stage
--
-- crops.status mixed two unrelated concepts in one ENUM:
--   marketplace availability  (available, out_of_stock)
--   agronomic growth stage    (planted, growing, harvested, failed)
-- The listing forms only wrote the first pair, while the marketplace
-- filter and harvest reminders only read the second, so neither
-- feature could ever match a real listing.
--
-- New model:
--   status        availability – what buyers care about (required)
--   growth_stage  optional farming stage; reminders use it
-- ============================================================

ALTER TABLE crops
  ADD COLUMN growth_stage ENUM('planted','growing','harvested','failed') NULL AFTER status;

UPDATE crops
   SET growth_stage = status
 WHERE status IN ('planted','growing','harvested','failed');

-- A crop still in the ground (or failed) can't be bought yet.
UPDATE crops
   SET status = CASE WHEN status IN ('available','harvested') THEN 'available' ELSE 'out_of_stock' END;

ALTER TABLE crops
  MODIFY status ENUM('available','out_of_stock') NOT NULL DEFAULT 'available';
