-- Phase 5 — Notifications (both centres).
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 11.1; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 5.
--
-- One polymorphic table for both audiences (admin + customer): the bell UI,
-- the read/unread logic, the mark-all-read action and the JSON shape are
-- identical for both, so two tables would be two of everything for no
-- behavioural difference. The composite index serves the only hot query —
-- "unread for this recipient, newest first".
--
-- Admin notifications use recipient_id = 0 ("any admin") — this system has a
-- single admin account and the admin bell reads every recipient_type='admin'
-- row regardless of id.
--
-- Additive only: touches no existing table.

USE pms_connection;

CREATE TABLE IF NOT EXISTS notifications (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('admin','user') NOT NULL,
  recipient_id   INT NOT NULL,
  type           VARCHAR(50) NOT NULL,
  title          VARCHAR(150) NOT NULL,
  body           TEXT NULL,
  link_url       VARCHAR(255) NULL,
  booking_id     INT NULL,
  is_read        TINYINT(1) NOT NULL DEFAULT 0,
  read_at        DATETIME NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_recipient (recipient_type, recipient_id, is_read, created_at),
  CONSTRAINT notifications_booking_fk FOREIGN KEY (booking_id)
    REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
