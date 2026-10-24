# Database migrations

Canonical, ordered sequence for standing up this schema from the pre-revision
baseline dump. Consolidated and verified in Phase 17 (2026-09-05) — see
`docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md`'s Phase 17 section for the full
audit, and `docs/DEPLOYMENT.md` for the actual deploy runbook.

## Fastest path for a new environment

Import `sql_file/pms_connection.sql` — the canonical dump. It already
reflects every migration below (schema *and* the demonstration seed data).
No migrations need to be replayed against it.

## Building the schema from the pre-revision baseline instead

If you're starting from the original, pre-revision dump (what
`sql_file/pms_connection.sql` looked like before Phase 17), run these against
it in exact numeric order:

```
01_fix_schema_mismatches.sql       (or the _hostinger variant — see below)
02_add_password_reset_columns.sql
03_add_profile_picture_column.sql
04_add_api_tokens_table.sql
05_datetime_model.sql
06_status_model.sql
07_availability_cleanup.sql
08_notifications.sql
09_messaging.sql
10_customer_requirements.sql
11_locations.sql
12_payments.sql
13_deposit.sql
14_inspections.sql
15_damages.sql
16_settlement_amounts.sql
17_reviews.sql                     (numbered 17, not 16 — see its own header)
18_fix_rental_policy_heading_encoding.sql
```

This exact sequence was replayed end-to-end against a throwaway MySQL 8.4.3
database during Phase 17 and confirmed to produce a schema structurally
identical to the live, fully-migrated database (verified by diffing
`mysqldump --no-data` output; the only differences were `AUTO_INCREMENT`
counters, which depend on how much data has since been inserted/deleted).

`05_datetime_backfill_report.csv` is not a migration — it's the audit trail
`05_datetime_model.sql` writes originals to (D3, Analysis §15.3a). Nothing
reads it back in; keep it for the record.

## `01_fix_schema_mismatches.sql` vs `01_fix_schema_mismatches_hostinger.sql`

Same two fixes (the `password_hash`→`password` rename, adding
`transactions.payment_status`), against two different target databases — run
whichever variant matches where you're deploying, never both. The Hostinger
variant additionally resets the `admins` row's password to a known bootstrap
value. **That line is a one-time initial-bootstrap step only** — never
re-run it against a database that already has a live admin account with its
own credentials, since doing so silently resets the admin login. See
`docs/DEPLOYMENT.md`'s runbook for how this interacts with the (separate,
still-outstanding) database-credential rotation.

## `20251106_add_columns.sql` — excluded, not part of the sequence

Pre-dates the revision project (its own timestamp, 2025-11-06, is before
either baseline dump's data existed) and every column it adds is already
present in both baseline dumps — replaying it today is a guaranteed no-op.
It's also non-portable: it uses `ADD COLUMN IF NOT EXISTS`, a MariaDB-only
extension that real MySQL 8 rejects as a syntax error (verified against
MySQL 8.4.3 while replaying the sequence above — the same defect this
consolidation fixed in migrations 01–03, which used to carry the same
pattern). Kept in this directory only as a historical record.

## A note on portability

Migrations 01 (both variants), 02, and 03 originally used
`ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...` as an idempotency guard. That
syntax is MariaDB-only; it silently worked wherever these were first written
and run, but produces a hard parse error on real MySQL 8 (confirmed on
8.4.3). Phase 17 dropped the `IF NOT EXISTS` from those three files — every
migration from 04 onward already ran unconditionally with no guard, so this
just brings 01–03 in line with the rest of the sequence. None of these
migrations are meant to be re-run against a database that already has their
columns; they're a strictly-ordered, run-once sequence, not idempotent
scripts.

## Demonstration seed data

The canonical dump carries: 5 locations + the 25-row `location_fees` matrix
(Phase 8), `rental_policies` v1.0 with a correctly-encoded heading (migration
18), `fuel_rates` (Phase 11), and one real `vehicle_reviews` row — submitted
through the actual `review_create()` code path against the one completed
booking in the demo dataset, not a fabricated fixture.

## Backups

`backups/` holds `mysqldump` snapshots taken before each risky migration
step, named `pre_phaseN_<timestamp>.sql`.
