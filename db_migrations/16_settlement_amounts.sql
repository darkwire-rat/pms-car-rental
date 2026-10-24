-- Phase 11 — Finalized settlement amounts + adjustment reasons.
-- docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 11, work item 4.
--
-- Populated only at finalize (admin_settle_deposit.php); NULL until then.
-- booking_damages already carries adjusted_amount + adjustment_reason
-- (migration 15) — these four columns are the equivalent for the two
-- formula-computed lines, so an admin override on ANY proposed line
-- (overtime, fuel, or a damage entry) has somewhere to be recorded with
-- its reason ("the admin can adjust any line WITH A REASON").
--
-- deposit_status / deposit_refund_amount / deposit_settled_at /
-- outstanding_balance already exist (migration 13, Phase 9) and are
-- unused until this phase's finalize step writes them for the first time
-- — no new columns needed for those.
--
-- Additive.

USE pms_connection;

ALTER TABLE bookings
  ADD COLUMN overtime_amount             DECIMAL(10,2) NULL AFTER outstanding_balance,
  ADD COLUMN overtime_adjustment_reason  VARCHAR(255) NULL AFTER overtime_amount,
  ADD COLUMN fuel_charge_amount          DECIMAL(10,2) NULL AFTER overtime_adjustment_reason,
  ADD COLUMN fuel_adjustment_reason      VARCHAR(255) NULL AFTER fuel_charge_amount;
