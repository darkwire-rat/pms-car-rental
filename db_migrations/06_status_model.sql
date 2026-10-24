-- Phase 2 (Foundation), item 2C — status model.
-- See docs/FUNCTIONAL_REVISION_ANALYSIS.md sections 7.2, 7.2a (D4, approved)
-- and docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 2.
--
-- Splits workflow state (status) from payment state (payment_status), which
-- were conflated in the old 4-value enum. 'overdue' and "payment pending"
-- remain deliberately UN-stored — they are derived at query time
-- (status='active' AND NOW() > dropoff_at, etc.) rather than written by a
-- scheduler this codebase has no infrastructure for. See Analysis sec 7.3.

USE pms_connection;

-- ---------------------------------------------------------------------------
-- Step 1: add the new status column alongside the old one, so the mapping
-- can be computed from live data before anything is dropped.
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  ADD COLUMN status_new ENUM(
    'pending_review','info_requested','approved','paid',
    'active','returned','completed','cancelled','declined'
  ) NOT NULL DEFAULT 'pending_review' AFTER status;

UPDATE bookings
SET status_new = CASE status
  WHEN 'pending'   THEN 'pending_review'
  WHEN 'confirmed' THEN 'approved'
  WHEN 'completed' THEN 'completed'
  WHEN 'cancelled' THEN 'cancelled'
END;

-- Guard: every row must have mapped to something other than the default
-- before proceeding — a NULL/unexpected old status would leave status_new
-- silently at 'pending_review', which is wrong for anything but genuinely
-- pending bookings. If this returns rows, STOP.
-- SELECT id, status FROM bookings WHERE status NOT IN ('pending','confirmed','completed','cancelled');

-- ---------------------------------------------------------------------------
-- Step 2: payment_status, derived per D4 (approved 2026-09-04). Only
-- 'confirmed'/'completed' bookings get a derived value; 'pending'/'cancelled'
-- keep the 'unpaid' default — no payment was ever recorded for a booking
-- that was never approved, and cancelled-booking refund tracking is Phase 9
-- scope, not this migration's.
-- ---------------------------------------------------------------------------
ALTER TABLE bookings
  ADD COLUMN payment_status ENUM('unpaid','partially_paid','paid','refunded')
  NOT NULL DEFAULT 'unpaid' AFTER status_new;

UPDATE bookings
SET payment_status = 'paid'
WHERE status IN ('confirmed','completed') AND paid >= total_amount;

-- ---------------------------------------------------------------------------
-- Step 3: swap status_new into status's place.
-- ---------------------------------------------------------------------------
ALTER TABLE bookings DROP INDEX status;
ALTER TABLE bookings DROP COLUMN status;
ALTER TABLE bookings CHANGE COLUMN status_new status ENUM(
    'pending_review','info_requested','approved','paid',
    'active','returned','completed','cancelled','declined'
  ) NOT NULL DEFAULT 'pending_review';

ALTER TABLE bookings
  ADD INDEX idx_status (status),
  ADD INDEX idx_payment_status (payment_status),
  ADD INDEX idx_status_pickup (status, pickup_at);

-- ---------------------------------------------------------------------------
-- Step 4: status history — the audit trail this codebase has never had
-- (Analysis sec 2.3: only created_at exists; no updated_at, no confirmed_at/
-- cancelled_at, no history table). Backfilled with one synthetic row per
-- existing booking recording its current state, since the real transition
-- times were never captured.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_status_history (
  id INT NOT NULL AUTO_INCREMENT,
  booking_id INT NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  changed_by_type ENUM('admin','user','system') NOT NULL,
  changed_by_id INT NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_booking_history (booking_id, created_at),
  CONSTRAINT booking_status_history_booking_fk
    FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, reason, created_at)
SELECT id, NULL, status, 'system',
       'Backfilled during Phase 2 migration (2026-09-04) — real transition time was never recorded by the pre-revision system; created_at is the closest available timestamp.',
       created_at
FROM bookings;

COMMIT;
