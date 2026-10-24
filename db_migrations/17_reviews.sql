-- Phase 12 — Reviews & ratings.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 12; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 12.
--
-- Numbered 17, not the 16 the plan document names — migration 16 was
-- already taken by Phase 11's settlement-amounts columns (bookings.
-- overtime_amount etc.), added after this phase's spec was written but
-- before it was built. No functional change, just a renumber.
--
-- UNIQUE(booking_id) is the real duplicate guard (Analysis sec 12: "the
-- database is the duplicate guard, not PHP" — a double-clicked submit or a
-- replayed request cannot defeat a unique key the way a SELECT-then-INSERT
-- check in PHP can). CHECK(rating BETWEEN 1 AND 5) likewise — server-side
-- validation is UX, this is the actual guarantee.
--
-- vehicle_id is stored directly rather than joined through bookings on
-- every read — this table exists to be aggregated per vehicle (listing
-- cards, detail modal), and Analysis sec 15.4 names
-- vehicle_reviews(vehicle_id, status) as a required index for exactly
-- that query shape.
--
-- status: 'visible' (default, post-moderation — Analysis sec 12's
-- explicit choice over pre-moderation) or 'hidden' (admin action, review
-- excluded from aggregates and public lists, but the row and the
-- customer's original text are never deleted or edited).
--
-- Additive.

USE pms_connection;

CREATE TABLE IF NOT EXISTS vehicle_reviews (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  booking_id         INT NOT NULL UNIQUE,
  vehicle_id         INT NOT NULL,
  user_id            INT NOT NULL,
  rating             TINYINT NOT NULL,
  body               TEXT NULL,
  status             ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  admin_response     TEXT NULL,
  admin_response_at  DATETIME NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT vehicle_reviews_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT vehicle_reviews_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE,
  CONSTRAINT vehicle_reviews_user_fk    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT chk_vehicle_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  KEY idx_vehicle_reviews_vehicle_status (vehicle_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
