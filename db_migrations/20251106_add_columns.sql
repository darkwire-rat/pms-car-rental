-- ============================================================================
-- EXCLUDED from the canonical migration sequence — Phase 17, 2026-09-05.
-- This file sits outside the 01-18 numbering and predates the revision
-- project entirely (its own date, 2025-11-06, is before any row in either
-- baseline dump was created). Every column it adds already exists in both
-- sql_file/pms_connection.sql and new_sql_file/pms_connection.sql — it was
-- applied to whatever database produced those dumps before they were taken,
-- so replaying it against the canonical dump or the 01-18 sequence is a
-- guaranteed no-op today. It is also NOT portable: contrary to its own
-- comment below, `ADD COLUMN IF NOT EXISTS` is a MariaDB-only extension —
-- verified as a hard syntax error on real MySQL 8.4.3 during Phase 17's
-- fresh-database migration replay (see db_migrations/README.md). Kept here
-- only as a historical record; do not run it.
-- ============================================================================
--
-- Migration: 2025-11-06 — Add missing columns and normalize schema
-- Run this against your PMS database (make a backup first).
-- Notes: Some statements use `IF NOT EXISTS` which requires MySQL 8+. If you run an older MySQL version, run the individual statements guarded by information_schema checks instead.

-- 1) Vehicles table: add missing columns
ALTER TABLE vehicles
  ADD COLUMN IF NOT EXISTS units_total INT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS thumbnail VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS fuel VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS price_per_day DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- If you already have a column named `active` and want to rename it to `is_active`, run the following instead (only if `active` exists):
-- ALTER TABLE vehicles CHANGE COLUMN active is_active TINYINT(1) NOT NULL DEFAULT 1;


-- 2) Bookings table: add missing columns
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS voucher_id INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS age INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS license_file VARCHAR(255) DEFAULT NULL;

-- If you need to add indexes/foreign keys for voucher_id:
-- ALTER TABLE bookings ADD INDEX idx_bookings_voucher_id (voucher_id);
-- ALTER TABLE bookings ADD CONSTRAINT fk_bookings_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE SET NULL;


-- 3) Vouchers table: ensure is_active exists and migrate from old `active` if present
ALTER TABLE vouchers
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- If your table has `active` and you want to migrate values to `is_active` and then drop `active`, you can run:
-- UPDATE vouchers SET is_active = active WHERE EXISTS (SELECT active FROM vouchers LIMIT 1);
-- ALTER TABLE vouchers DROP COLUMN active; -- Only run after verifying the UPDATE above succeeded


-- 4) Optional: Transactions table schema guidance
-- The codebase expects transactions to have at least these columns:
-- id (PK), booking_id (INT), transaction_ref (VARCHAR), payment_status (VARCHAR), created_at TIMESTAMP
-- If `payment_status` doesn't exist, add it:
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 5) Users table: ensure password column exists (the app stores hashed password in `password`)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password VARCHAR(255) NOT NULL;

-- If you currently have `password_hash` instead, you can rename it:
-- ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL;

-- End of migration
COMMIT;
