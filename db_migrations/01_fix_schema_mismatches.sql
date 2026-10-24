-- Run AFTER importing pms_connection.sql (the real phpMyAdmin dump).
-- Fixes two mismatches between that dump and what the PHP code actually queries.

USE pms_connection;

-- login.php / register.php select/insert a `password` column, but the dump has `password_hash`.
ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL;

-- return_early.php inserts a `payment_status` value; the dump's transactions table lacks it.
-- (`IF NOT EXISTS` on ADD COLUMN is a MariaDB-only extension; real MySQL 8
-- rejects it as a syntax error, verified against MySQL 8.4.3 during Phase 17's
-- fresh-database replay. Dropped here since this migration only ever runs
-- once, immediately after importing the base dump, where the column is
-- always absent.)
ALTER TABLE transactions ADD COLUMN payment_status VARCHAR(50) DEFAULT NULL;
