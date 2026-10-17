-- ============================================================
-- 008 – Indexes for the queries the application actually runs
--
-- Until now only primary keys, UNIQUE columns and the indexes InnoDB
-- creates for foreign keys existed. Each index below matches a specific
-- WHERE / ORDER BY in the code. Before/after EXPLAIN output and timings:
-- docs/EXPLAIN.md (reproduce with: php tools/explain_demo.php).
-- ============================================================

-- login.php rate limit: WHERE ip_address = ? AND attempted_at >= NOW() - INTERVAL 15 MINUTE
CREATE INDEX idx_login_attempts_ip_time    ON login_attempts (ip_address, attempted_at);
-- login.php per-account limit: WHERE email = ? AND attempted_at >= ...
CREATE INDEX idx_login_attempts_email_time ON login_attempts (email, attempted_at);

-- reset_password.php: WHERE token_hash = ?  (also guarantees uniqueness)
ALTER TABLE password_resets ADD CONSTRAINT uq_password_resets_token UNIQUE (token_hash);

-- buyer dashboard / marketplace: WHERE status = 'available' ORDER BY created_at DESC LIMIT n
CREATE INDEX idx_crops_status_created ON crops (status, created_at);

-- farmer dashboard: WHERE user_id = ? ORDER BY created_at DESC. Like the activity_log index
-- below, it can enforce the user_id foreign key, so InnoDB drops the
-- single-column index it had created for fk_crops_user.
CREATE INDEX idx_crops_user_created ON crops (user_id, created_at);

-- Not added: (role, county_id) on users for the marketplace county filter.
-- EXPLAIN showed the existing county_id FK index already serves it, with
-- no change in plan or time, so the extra index would only slow writes.

-- activity_log.php: WHERE user_id = ? ORDER BY created_at DESC LIMIT 20
-- The composite index can also enforce the user_id foreign key, so InnoDB
-- silently drops the single-column index it had created for that FK.
CREATE INDEX idx_activity_user_created ON activity_log (user_id, created_at);

-- price_trends.php: WHERE product_key = ? AND unit = ? AND recorded_at >= ?
CREATE INDEX idx_price_history_lookup ON price_history (product_key, unit, recorded_at);
