-- Phase 16 (Full system testing) — found during the happy-path walkthrough's
-- policy-acceptance step: the rental_policies v1.0 row's content_html heading
-- rendered as "PMS Car Rental Ã”Ã‡Ã¶ Rental Policy v1.0" in the booking modal's
-- policy panel, visible to every customer.
--
-- The source migration (10_customer_requirements.sql) has always contained the
-- correct byte sequence — "PMS Car Rental — Rental Policy v1.0" — confirmed by
-- reading that file directly. The corruption is a live-data artifact from how
-- that INSERT was originally executed (almost certainly a non-utf8mb4 client
-- connection charset at the time), not a defect in the migration script
-- itself, so a fresh install applying 10_customer_requirements.sql through a
-- properly utf8mb4-connected client would never reproduce this. This
-- migration only needs to run against an installation that already has the
-- corrupted row.
--
-- SET NAMES pins the connection charset for this session so the UPDATE itself
-- can't reintroduce the same corruption regardless of how this file is
-- invoked.
SET NAMES utf8mb4;

UPDATE rental_policies
SET content_html = REPLACE(
  content_html,
  '<h5>PMS Car Rental Ã”Ã‡Ã¶ Rental Policy v1.0</h5>',
  '<h5>PMS Car Rental — Rental Policy v1.0</h5>'
)
WHERE version = '1.0';
