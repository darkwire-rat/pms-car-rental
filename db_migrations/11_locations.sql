-- Phase 8 — Locations & location pricing.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 13; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 8.
--
-- Fixed branches + an explicit 25-row fee matrix. NO per-branch inventory —
-- the location fee is purely a relocation surcharge; company-wide inventory
-- is a documented simplification (Analysis sec 13.3). The five branches are
-- FICTIONAL demonstration locations, labelled as such in the UI.
--
-- Fee tiers (approved rule P9): ₱0 same branch · ₱300 adjacent · ₱500 non-adjacent.
-- The matrix is seeded symmetrically here but the schema (UNIQUE per ordered
-- pair) allows asymmetry if the business ever wants it.
--
-- Additive.

USE pms_connection;

CREATE TABLE IF NOT EXISTS locations (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  address    VARCHAR(200) NULL,
  city       VARCHAR(100) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS location_fees (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  pickup_location_id  INT NOT NULL,
  dropoff_location_id INT NOT NULL,
  fee                 DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uq_location_pair (pickup_location_id, dropoff_location_id),
  CONSTRAINT location_fees_pickup_fk  FOREIGN KEY (pickup_location_id)  REFERENCES locations (id) ON DELETE CASCADE,
  CONSTRAINT location_fees_dropoff_fk FOREIGN KEY (dropoff_location_id) REFERENCES locations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE bookings
  ADD COLUMN pickup_location_id  INT NULL AFTER contact_number,
  ADD COLUMN dropoff_location_id INT NULL AFTER pickup_location_id,
  ADD COLUMN location_fee        DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER dropoff_location_id,
  ADD KEY idx_pickup_location (pickup_location_id),
  ADD KEY idx_dropoff_location (dropoff_location_id),
  ADD CONSTRAINT bookings_pickup_location_fk  FOREIGN KEY (pickup_location_id)  REFERENCES locations (id) ON DELETE SET NULL,
  ADD CONSTRAINT bookings_dropoff_location_fk FOREIGN KEY (dropoff_location_id) REFERENCES locations (id) ON DELETE SET NULL;

-- --- 5 fictional demonstration branches (ids 1-5) -----------------------
INSERT INTO locations (name, address, city, is_active, sort_order) VALUES
  ('Cubao Branch (demo)',       'Gen. Araneta Ave',  'Quezon City', 1, 1),
  ('Project 4 Branch (demo)',   'Katipunan Ave',     'Quezon City', 1, 2),
  ('QC Circle Branch (demo)',   'Elliptical Rd',     'Quezon City', 1, 3),
  ('Marikina Branch (demo)',    'Sumulong Hwy',      'Marikina',    1, 4),
  ('Novaliches Branch (demo)',  'Quirino Hwy',       'Quezon City', 1, 5);

-- --- 25-row fee matrix (P9) -------------------------------------------
-- Adjacent pairs (₱300): 1-2, 1-3, 1-4, 2-3, 2-4, 3-4, 3-5
-- Non-adjacent (₱500):   1-5, 2-5, 4-5
INSERT INTO location_fees (pickup_location_id, dropoff_location_id, fee) VALUES
  (1,1,0),   (2,2,0),   (3,3,0),   (4,4,0),   (5,5,0),
  (1,2,300), (2,1,300),
  (1,3,300), (3,1,300),
  (1,4,300), (4,1,300),
  (2,3,300), (3,2,300),
  (2,4,300), (4,2,300),
  (3,4,300), (4,3,300),
  (3,5,300), (5,3,300),
  (1,5,500), (5,1,500),
  (2,5,500), (5,2,500),
  (4,5,500), (5,4,500);
