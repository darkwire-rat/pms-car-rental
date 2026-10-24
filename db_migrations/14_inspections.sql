-- Phase 10 — Pickup, return & inspection.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 8; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 10.
--
-- Pickup and return become recorded ADMIN events (picked_up_at / fuel_out
-- already existed from Phase 2 as picked_up_at; fuel_level_out is new here).
-- return_inspections is draft-saveable — finalisation (proposed overtime/
-- fuel/damage, deposit settlement) is Phase 11, which needs D6 and the
-- P2/P3/P4/P6 charge formulas. This phase only captures the inspection
-- data; it computes nothing yet.
--
-- Additive.

USE pms_connection;

ALTER TABLE bookings
  ADD COLUMN fuel_level_out TINYINT NULL AFTER picked_up_at,
  ADD CONSTRAINT chk_fuel_level_out CHECK (fuel_level_out IS NULL OR fuel_level_out BETWEEN 0 AND 8);

CREATE TABLE IF NOT EXISTS return_inspections (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  booking_id       INT NOT NULL UNIQUE,
  fuel_level_in    TINYINT NULL,
  condition_notes  TEXT NULL,
  has_damage       TINYINT(1) NOT NULL DEFAULT 0,
  damage_notes     TEXT NULL,
  status           ENUM('draft','finalized') NOT NULL DEFAULT 'draft',
  finalized_at     DATETIME NULL,
  created_by_admin_id INT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT return_inspections_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT chk_fuel_level_in CHECK (fuel_level_in IS NULL OR fuel_level_in BETWEEN 0 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
