-- (`IF NOT EXISTS` on ADD COLUMN is a MariaDB-only extension; real MySQL 8
-- rejects it as a syntax error, verified against MySQL 8.4.3 during Phase 17's
-- fresh-database replay. Dropped here since this migration only ever runs
-- once, in sequence after 02, where this column is always absent.)
ALTER TABLE users
  ADD COLUMN profile_picture_path VARCHAR(255) DEFAULT NULL;
