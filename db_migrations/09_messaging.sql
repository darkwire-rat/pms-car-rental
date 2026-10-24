-- Phase 6 — Messaging (admin ↔ customer).
-- docs/FUNCTIONAL_REVISION_ANALYSIS.md sec 11.2, sec 11.3; docs/FUNCTIONAL_IMPLEMENTATION_PLAN.md Phase 6.
--
-- One structure for both reservation-scoped and general threads: a
-- conversation with a nullable booking_id. There is a single admin account,
-- so "admin" is effectively a shared inbox. Read state is one read_at per
-- message — each message has exactly one non-sender reader (the one admin,
-- or the one customer), so a single column expresses per-participant read
-- state without a junction table.
--
-- The legacy `messages` contact-form table is NOT touched — it is a
-- different thing (an anonymous-ish enquiry) and still backed by
-- save_message.php / about.php.
--
-- Additive only.

USE pms_connection;

CREATE TABLE IF NOT EXISTS conversations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  booking_id      INT NULL,
  subject         VARCHAR(150) NOT NULL,
  status          ENUM('open','closed') NOT NULL DEFAULT 'open',
  last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_conv_user (user_id, last_message_at),
  KEY idx_conv_booking (booking_id),
  CONSTRAINT conversations_user_fk FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT conversations_booking_fk FOREIGN KEY (booking_id)
    REFERENCES bookings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS conversation_messages (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_type     ENUM('admin','user') NOT NULL,
  sender_id       INT NOT NULL,
  body            TEXT NOT NULL,
  read_at         DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cmsg_conv (conversation_id, created_at),
  KEY idx_cmsg_unread (conversation_id, sender_type, read_at),
  CONSTRAINT cmsg_conv_fk FOREIGN KEY (conversation_id)
    REFERENCES conversations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
