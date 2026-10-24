-- Phase 11 — Overtime, fuel, damage & deposit settlement.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 8.3, sec 8.4; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 11.
--
-- D6 (resolved, no longer open): damage exceeding the deposit is RECORDED
-- ONLY, via bookings.outstanding_balance (already added in Phase 9) — never
-- auto-charged, never a receivables flow. Nothing in this migration adds a
-- collection mechanism.
--
-- fuel_rates is a per-category lookup table (P6: "charged per eighth of a
-- tank missing, at a per-category rate"), mirroring the location_fees
-- precedent from db_migrations/11_locations.sql — a small, explicit,
-- admin-editable table rather than a hardcoded PHP array or a value
-- duplicated across every vehicles row in the same category. P6 fixes the
-- charging mechanism, not the peso amounts, so the rates below are
-- provisional placeholders (same status as migration 11's "fictional demo"
-- location fees) — adjust freely, no code depends on the specific values.
--
-- booking_damages + booking_damage_photos capture the report -> describe ->
-- estimate -> attach evidence -> admin confirms/waives -> deduct flow
-- (Analysis sec 8.4). estimated_amount is the system/admin's initial figure;
-- adjusted_amount + adjustment_reason are populated only if the finalising
-- admin overrides it (Phase 11 work item 4: "the admin can adjust any line
-- WITH A REASON"). Photos are served through secure_file.php, same private-
-- directory pattern as the licence/verification uploads (Phase 7).
--
-- Additive.

USE pms_connection;

CREATE TABLE IF NOT EXISTS fuel_rates (
  category        VARCHAR(50) NOT NULL PRIMARY KEY,
  rate_per_eighth DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- Provisional per-category rates (adjust via this table any time) ----
INSERT INTO fuel_rates (category, rate_per_eighth) VALUES
  ('Scooter', 150.00),
  ('Sedan',   300.00),
  ('Pickup',  400.00),
  ('SUV',     450.00),
  ('Minivan', 450.00),
  ('Van',     500.00)
ON DUPLICATE KEY UPDATE rate_per_eighth = rate_per_eighth;

CREATE TABLE IF NOT EXISTS booking_damages (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  booking_id          INT NOT NULL,
  description         TEXT NOT NULL,
  estimated_amount    DECIMAL(10,2) NOT NULL,
  adjusted_amount     DECIMAL(10,2) NULL,
  adjustment_reason   VARCHAR(255) NULL,
  status              ENUM('proposed','confirmed','waived') NOT NULL DEFAULT 'proposed',
  created_by_admin_id INT NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT booking_damages_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS booking_damage_photos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  damage_id  INT NOT NULL,
  file_path  VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT booking_damage_photos_damage_fk FOREIGN KEY (damage_id) REFERENCES booking_damages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
