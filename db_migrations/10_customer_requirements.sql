-- Phase 7 — Customer requirements: verification photo, address, licence, policy.
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 6.5, 6.6, 2.9, 14; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 7.
--
-- Additive. Creates users.license_path PROPERLY (register.php's runtime
-- `ALTER TABLE ... ADD COLUMN IF NOT EXISTS license_path` inside the
-- registration handler is removed in the same phase — a web request must
-- never mutate the schema).

USE pms_connection;

-- --- users: identity + verification documents + structured address --------
ALTER TABLE users
  ADD COLUMN license_path            VARCHAR(255) NULL AFTER profile_picture_path,
  ADD COLUMN verification_photo_path VARCHAR(255) NULL AFTER license_path,
  ADD COLUMN verification_status     ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none' AFTER verification_photo_path,
  ADD COLUMN verified_at             DATETIME NULL AFTER verification_status,
  ADD COLUMN phone                   VARCHAR(32)  NULL AFTER verified_at,
  ADD COLUMN address_line1           VARCHAR(150) NULL AFTER phone,
  ADD COLUMN address_line2           VARCHAR(150) NULL AFTER address_line1,
  ADD COLUMN city                    VARCHAR(100) NULL AFTER address_line2,
  ADD COLUMN province                VARCHAR(100) NULL AFTER city,
  ADD COLUMN postal_code             VARCHAR(16)  NULL AFTER province;

-- --- bookings: snapshots taken at submit time ----------------------------
-- license_file already exists (legacy, NULL on all 17 rows) — reused as the
-- licence snapshot. The rest are new.
ALTER TABLE bookings
  ADD COLUMN verification_photo_path  VARCHAR(255) NULL AFTER license_file,
  ADD COLUMN customer_address_snapshot TEXT       NULL AFTER verification_photo_path,
  ADD COLUMN policy_version_accepted  VARCHAR(20)  NULL AFTER customer_address_snapshot,
  ADD COLUMN policy_accepted_at       DATETIME     NULL AFTER policy_version_accepted;

-- --- rental_policies: versioned, immutable-per-version -------------------
CREATE TABLE IF NOT EXISTS rental_policies (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  version        VARCHAR(20) NOT NULL UNIQUE,
  effective_from DATE NOT NULL,
  content_html   MEDIUMTEXT NOT NULL,
  is_current     TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO rental_policies (version, effective_from, is_current, content_html) VALUES
('1.0', '2026-09-04', 1, '<h5>PMS Car Rental — Rental Policy v1.0</h5>
<p class="text-body-secondary small">Effective 4 September 2026. This is a practical application policy, not a legal contract.</p>
<ol>
  <li><strong>Minimum rental &amp; how days are counted.</strong> The minimum rental is 24 hours, measured on date <em>and</em> time. One rental day is exactly 24 hours; billable days are the total hours divided by 24, rounded up (a 30-hour rental is 2 days).</li>
  <li><strong>Pricing.</strong> Rental fee = daily rate &times; billable days. Any voucher discount is applied to that subtotal. The amount shown on the booking summary is what you pay.</li>
  <li><strong>Payment.</strong> Payment is due after the reservation is approved. An approved booking left unpaid for 48 hours is cancelled automatically.</li>
  <li><strong>Cancellation &amp; refunds.</strong> Full refund if you cancel at least 24 hours before pickup; 50% refund inside 24 hours; no refund once the rental has started.</li>
  <li><strong>Return deadline, grace &amp; overtime.</strong> Return by the agreed drop-off time. A 60-minute grace period applies. Past that: 10% of the daily rate per started hour for the first 6 hours late; beyond 6 hours late, one additional full rental day (and another for each further 24 hours).</li>
  <li><strong>Fuel.</strong> Return the vehicle at the fuel level you received it. A shortfall is charged per eighth of a tank at the vehicle&rsquo;s category rate.</li>
  <li><strong>Security deposit.</strong> A refundable security deposit is collected with the rental payment and returned after a clean return and inspection. Charges for overtime, fuel or damage are deducted from it first; anything beyond the deposit is billed separately.</li>
  <li><strong>Damage.</strong> You are responsible for damage beyond normal wear. Any damage charge is assessed by an admin during the return inspection before it is applied &mdash; nothing is charged automatically.</li>
  <li><strong>Required documents.</strong> A valid driver&rsquo;s licence, a verification photo, and a current home address are required before a booking can be approved.</li>
  <li><strong>Customer responsibilities &amp; prohibited use.</strong> Only the named renter may drive. No use for hire, racing, off-road driving, or transport of illegal goods. Smoking in the vehicle incurs a cleaning fee.</li>
</ol>');
