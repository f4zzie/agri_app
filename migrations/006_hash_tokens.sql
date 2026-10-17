-- ============================================================
-- 006 – Store only SHA-256 hashes of one-time tokens
--
-- Password-reset and email-verification tokens were stored in
-- plaintext: anyone who could read the table (backup, SQL injection,
-- shared phpMyAdmin) could take over any account with a pending reset.
-- Now only SHA2(token, 256) is stored; the plaintext exists only in the
-- emailed link. Tokens are 256-bit random values, so a fast hash is
-- sufficient (no need for bcrypt as with low-entropy passwords).
--
-- password_resets is also keyed by user_id (a real foreign key) instead
-- of a free-text email that could match no user.
-- ============================================================

ALTER TABLE password_resets
  ADD COLUMN user_id    INT      NULL AFTER id,
  ADD COLUMN token_hash CHAR(64) NULL AFTER user_id;

UPDATE password_resets pr
  JOIN users u ON u.email = pr.email
   SET pr.user_id    = u.id,
       pr.token_hash = SHA2(pr.token, 256);

DELETE FROM password_resets WHERE user_id IS NULL;

ALTER TABLE password_resets
  DROP COLUMN email,
  DROP COLUMN token,
  MODIFY user_id    INT      NOT NULL,
  MODIFY token_hash CHAR(64) NOT NULL,
  ADD CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE users
  ADD COLUMN verification_token_hash CHAR(64) NULL AFTER verification_token;

UPDATE users
   SET verification_token_hash = SHA2(verification_token, 256)
 WHERE verification_token IS NOT NULL;

ALTER TABLE users DROP COLUMN verification_token;
