-- Run AFTER importing pms_connection.sql, while the correct Hostinger database
-- (e.g. <db_name>) is selected in phpMyAdmin.
-- Fixes two mismatches between that dump and what the PHP code actually queries.

-- login.php / register.php select/insert a `password` column, but the dump has `password_hash`.
ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL;

-- return_early.php inserts a `payment_status` value; the dump's transactions table lacks it.
-- (`IF NOT EXISTS` on ADD COLUMN is a MariaDB-only extension; real MySQL 8
-- rejects it as a syntax error, verified against MySQL 8.4.3 during Phase 17's
-- fresh-database replay. Dropped here since this migration only ever runs
-- once, immediately after importing the base dump, where the column is
-- always absent.)
ALTER TABLE transactions ADD COLUMN payment_status VARCHAR(50) DEFAULT NULL;

-- Reset admin password to a known value (same as the local XAMPP setup): <admin_password>
-- Change this password after logging in.
UPDATE admins SET password = '<bcrypt_hash_of_new_admin_password>' WHERE email = 'admin@pms.local';
