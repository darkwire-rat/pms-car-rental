-- (`IF NOT EXISTS` on ADD COLUMN is a MariaDB-only extension; real MySQL 8
-- rejects it as a syntax error, verified against MySQL 8.4.3 during Phase 17's
-- fresh-database replay. Dropped here since this migration only ever runs
-- once, in sequence after 01, where these columns are always absent.)
ALTER TABLE users
  ADD COLUMN reset_code VARCHAR(6) DEFAULT NULL,
  ADD COLUMN reset_code_expires DATETIME DEFAULT NULL;
