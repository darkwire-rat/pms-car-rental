-- Phase 2 (Foundation), item 2B — availability rewrite, database side.
-- See docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 1.4 (Finding A) and sec 15.
--
-- vehicles.units_total was being permanently DECREMENTED by
-- admin_confirm_booking.php on every approval and incremented back by
-- cancel_booking.php / return_early.php on cancellation/return. That code is
-- being removed in this phase (see reserve.php, admin_confirm_booking.php,
-- cancel_booking.php, return_early.php) — going forward, units_total means
-- "total units owned" and is never written by a booking action again;
-- availability is computed on the fly from live overlapping bookings.
--
-- But units_total's CURRENT value already has that decrement baked in for
-- every vehicle with a booking still in 'approved' status (not yet
-- completed/cancelled, so its decrement was never given back). This
-- migration reconstructs the true original fleet size once, before the
-- mutating code is removed:
--
--   true_total = current_units_total + COUNT(bookings currently 'approved' for that vehicle)
--
-- This is exact under the assumption that every past cancel/complete action
-- correctly restored its unit (verified true by reading cancel_booking.php
-- and return_early.php: both increment units_total unconditionally when a
-- previously-confirmed booking leaves that state) — the only currently-
-- outstanding decrement is from bookings still sitting in 'approved'.
--
-- Verified affected vehicles (8 of 34) before writing this migration:
--   id 4  (Ford Everest)          3 -> 4
--   id 6  (Nissan Terra)          3 -> 4
--   id 13 (Ford Ranger Raptor)    3 -> 4
--   id 18 (Kia Forte)             3 -> 4
--   id 19 (Chevrolet Cruze)       5 -> 6
--   id 23 (Chevrolet Trailblazer) 3 -> 5
--   id 34 (Honda Beat)            7 -> 8
--   id 37 (Toyota)                3 -> 4
-- The other 26 vehicles have no currently-approved bookings and are
-- unaffected (0 added).

USE pms_connection;

UPDATE vehicles v
LEFT JOIN (
  SELECT vehicle_id, COUNT(*) AS held
  FROM bookings
  WHERE status = 'approved'
  GROUP BY vehicle_id
) h ON h.vehicle_id = v.id
SET v.units_total = v.units_total + COALESCE(h.held, 0)
WHERE COALESCE(h.held, 0) > 0;

-- is_active is no longer coupled to inventory exhaustion by any booking
-- action after this phase's code changes — it means "in the catalogue"
-- only, per Analysis sec 2B. No data change needed here: is_active was
-- already 1 for every vehicle in the live dataset (verified: no vehicle
-- had reached 0 units before this migration), so there is nothing to
-- reverse.

COMMIT;
