-- Phase 9 — Security deposit + financial-model split.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 10; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 9.
--
-- The security deposit is a refundable LIABILITY, never revenue (sec 10.1).
-- It is tracked in its own columns; total_amount stays the RENTAL total
-- (rental_fee + location_fee - discount) exactly as it already is — the
-- deposit was never folded into it. `rental_fee` is added as an explicit
-- revenue figure for Phase 13 reporting, backfilled from total_amount.
--
-- P5: deposit = 30% of the rental subtotal, rounded up to ₱500, floor
-- ₱1,000, cap ₱5,000; vehicles.security_deposit overrides.
-- P7: cancellation refund tiers, now persisted rather than thrown away.
-- P8: payment window = 48h after approval (approved_at), lazy expiry.
--
-- Additive.

USE pms_connection;

ALTER TABLE vehicles
  ADD COLUMN security_deposit DECIMAL(10,2) NULL AFTER price_per_day;

ALTER TABLE bookings
  ADD COLUMN rental_fee            DECIMAL(10,2) NULL AFTER total_amount,
  ADD COLUMN security_deposit      DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER rental_fee,
  ADD COLUMN deposit_status        ENUM('not_collected','held','partially_refunded','refunded','forfeited') NOT NULL DEFAULT 'not_collected' AFTER security_deposit,
  ADD COLUMN deposit_refund_amount DECIMAL(10,2) NULL AFTER deposit_status,
  ADD COLUMN deposit_settled_at    DATETIME NULL AFTER deposit_refund_amount,
  ADD COLUMN outstanding_balance   DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER deposit_settled_at,
  ADD COLUMN approved_at           DATETIME NULL AFTER updated_at,
  ADD COLUMN refund_amount         DECIMAL(10,2) NULL AFTER outstanding_balance,
  ADD COLUMN refund_percentage     TINYINT NULL AFTER refund_amount,
  ADD COLUMN cancelled_at          DATETIME NULL AFTER refund_percentage,
  ADD COLUMN cancel_reason         VARCHAR(255) NULL AFTER cancelled_at;

-- Backfill rental_fee from the existing (rental-only) total_amount.
UPDATE bookings SET rental_fee = total_amount WHERE rental_fee IS NULL;

-- Backfill approved_at for bookings already past approval, from the
-- status-history row that recorded the transition.
UPDATE bookings b
JOIN (
  SELECT booking_id, MIN(created_at) AS approved_at
  FROM booking_status_history
  WHERE to_status = 'approved'
  GROUP BY booking_id
) h ON h.booking_id = b.id
SET b.approved_at = h.approved_at
WHERE b.approved_at IS NULL
  AND b.status IN ('approved','paid','active','returned','completed');

-- Existing paid bookings: their deposit was never separately collected in
-- the old model, so leave security_deposit = 0 / deposit_status =
-- 'not_collected'. New bookings compute it at submit (reserve.php).
