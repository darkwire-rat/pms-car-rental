-- Phase 9 — Payments.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 9.3; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 9.
--
-- A real payment ledger with a provider abstraction. D5 resolved
-- 2026-09-04: `manual` (simulated) is the only wired driver; the schema
-- keeps the full provider enum so a gateway driver is a drop-in later.
--
-- `purpose` is what makes deposit-vs-revenue accounting possible (sec 10).
-- UNIQUE (provider, provider_ref) makes webhook handling idempotent — a
-- re-delivered event cannot double-credit a booking.
--
-- The legacy `transactions` table is NOT touched — receipt.php and
-- transactions.php still read it (Analysis sec 9.3).
--
-- Additive.

USE pms_connection;

CREATE TABLE IF NOT EXISTS payments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  booking_id    INT NOT NULL,
  purpose       ENUM('rental','deposit','overtime','damage','fuel','refund') NOT NULL,
  provider      ENUM('manual','paymongo','stripe') NOT NULL DEFAULT 'manual',
  provider_ref  VARCHAR(255) NULL,
  method        VARCHAR(50) NULL,
  amount        DECIMAL(10,2) NOT NULL,
  currency      CHAR(3) NOT NULL DEFAULT 'PHP',
  status        ENUM('pending','processing','succeeded','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  paid_at       DATETIME NULL,
  raw_response  JSON NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_ref (provider, provider_ref),
  KEY idx_payments_booking (booking_id, status),
  KEY idx_payments_purpose (purpose),
  CONSTRAINT payments_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- Backfill (D4, Analysis sec 7.2a) ---------------------------------
-- The 8 bookings that D4 mapped to payment_status='paid' (bookings.paid
-- covered the total) get a `manual` / `cash_legacy` rental payment,
-- paid_at taken from the legacy transactions row where present.
INSERT INTO payments (booking_id, purpose, provider, provider_ref, method, amount, status, paid_at)
SELECT b.id,
       'rental',
       'manual',
       CONCAT('legacy-', b.id),
       'cash_legacy',
       b.total_amount,
       'succeeded',
       COALESCE((SELECT t.paid_at FROM transactions t WHERE t.booking_id = b.id ORDER BY t.id DESC LIMIT 1), b.created_at)
FROM bookings b
WHERE b.payment_status = 'paid'
  AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id AND p.purpose = 'rental');
