-- Phase 2 (Foundation), item 2A + 2E — datetime model + D3 remediation.
-- See docs/FUNCTIONAL_REVISION_ANALYSIS.md sections 1.4 (Finding B), 15.2, 15.3a
-- and docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 2.
--
-- Run AFTER db_migrations/05_datetime_backfill_report.csv has been generated
-- (it captures the pre-remediation original values for the 5 rows this
-- migration collapses — see that file for what changes and why).
--
-- Safety: a full backup was taken to db_migrations/backups/ before this ran.

USE pms_connection;

-- ---------------------------------------------------------------------------
-- Step 1: add the new timestamp columns as nullable first (dropoff_time can
-- be legally NULL today so pickup_at/dropoff_at can't be NOT NULL until
-- after backfill).
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  ADD COLUMN pickup_at DATETIME NULL AFTER dropoff_time,
  ADD COLUMN dropoff_at DATETIME NULL AFTER pickup_at,
  ADD COLUMN actual_return_at DATETIME NULL AFTER dropoff_at,
  ADD COLUMN picked_up_at DATETIME NULL AFTER actual_return_at,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- ---------------------------------------------------------------------------
-- Step 2: backfill from the existing date+time columns. No booking has a
-- NULL pickup_time/dropoff_time (verified against live data before writing
-- this migration), so no default-time fallback is needed.
-- ---------------------------------------------------------------------------
UPDATE bookings
SET pickup_at  = CONCAT(rental_date, ' ', pickup_time),
    dropoff_at = CONCAT(return_date, ' ', dropoff_time)
WHERE pickup_at IS NULL;

-- ---------------------------------------------------------------------------
-- Step 3 (D3, approved 2026-09-04): remediate the 5 rows whose stored window
-- is inconsistent with what was actually billed. Every one of the five has
-- days = 1 (billed for one day), so this resolves to pickup_at + 24h in
-- every case. Original values are preserved in
-- db_migrations/05_datetime_backfill_report.csv, generated before this ran.
--
-- Booking 41 is the consequential case: its stored dates spanned 5 days
-- (2025-11-03 -> 2025-11-08) but it was billed for 1 day (days=1,
-- total_amount=rate x 1). This migration trusts the billed amount over the
-- stored window, consistent with the same rule applied to bookings 37, 38,
-- 46 and 69 (all zero/negative-duration windows billed for 1 day). See
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 15.3a for the full reasoning.
-- ---------------------------------------------------------------------------
UPDATE bookings
SET dropoff_at = DATE_ADD(pickup_at, INTERVAL (GREATEST(1, days) * 24) HOUR)
WHERE id IN (37, 38, 41, 46, 69);

-- ---------------------------------------------------------------------------
-- Step 4: verify no NULLs and no remaining violations before locking down.
-- If either SELECT below returns rows, STOP — do not proceed to step 5.
-- ---------------------------------------------------------------------------
-- SELECT id FROM bookings WHERE pickup_at IS NULL OR dropoff_at IS NULL;
-- SELECT id, pickup_at, dropoff_at FROM bookings WHERE dropoff_at < DATE_ADD(pickup_at, INTERVAL 24 HOUR);

-- ---------------------------------------------------------------------------
-- Step 5: lock down NOT NULL now that every row is populated and valid.
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  MODIFY COLUMN pickup_at DATETIME NOT NULL,
  MODIFY COLUMN dropoff_at DATETIME NOT NULL;

-- ---------------------------------------------------------------------------
-- Step 6: convert the four legacy columns to generated columns derived from
-- pickup_at/dropoff_at, so every existing query that reads rental_date,
-- return_date, pickup_time or dropoff_time keeps working unchanged, while
-- pickup_at/dropoff_at become the authoritative source. This is the load-
-- bearing safety decision of this migration (Analysis sec 15.2) — it is why
-- ~30 pre-existing queries do not need to change in this phase.
--
-- Indexes on the old columns must be dropped before the columns themselves
-- (MySQL will not silently drop a named single-column index when the column
-- it covers is dropped in the same statement in all versions, so this is
-- done as two explicit steps rather than assumed).
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  DROP INDEX rental_date,
  DROP INDEX return_date;

ALTER TABLE bookings
  DROP COLUMN rental_date,
  DROP COLUMN return_date,
  DROP COLUMN pickup_time,
  DROP COLUMN dropoff_time;

ALTER TABLE bookings
  ADD COLUMN rental_date  DATE GENERATED ALWAYS AS (DATE(pickup_at))  STORED AFTER dropoff_at,
  ADD COLUMN return_date  DATE GENERATED ALWAYS AS (DATE(dropoff_at)) STORED AFTER rental_date,
  ADD COLUMN pickup_time  TIME GENERATED ALWAYS AS (TIME(pickup_at))  STORED AFTER return_date,
  ADD COLUMN dropoff_time TIME GENERATED ALWAYS AS (TIME(dropoff_at)) STORED AFTER pickup_time;

-- ---------------------------------------------------------------------------
-- Step 7: re-create the indexes the legacy columns had, plus new indexes the
-- datetime-aware availability query (Phase 2, item 2B) needs.
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  ADD INDEX idx_rental_date (rental_date),
  ADD INDEX idx_return_date (return_date),
  ADD INDEX idx_pickup_at (pickup_at),
  ADD INDEX idx_dropoff_at (dropoff_at),
  ADD INDEX idx_vehicle_window (vehicle_id, pickup_at, dropoff_at);

-- ---------------------------------------------------------------------------
-- Step 8: the minimum-duration constraint (R1/R3). Added last, after every
-- row is known to satisfy it, so this ALTER cannot fail.
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  ADD CONSTRAINT chk_min_duration
  CHECK (dropoff_at >= DATE_ADD(pickup_at, INTERVAL 24 HOUR));

COMMIT;
