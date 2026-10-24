<?php
/* Admin ↔ customer messaging —. */

require_once __DIR__ . '/notify.php';

/* The open booking-scoped conversation for, creating one if none exists. */
function messaging_get_or_open_booking_conversation(PDO $pdo, int $userId, int $bookingId, string $subject): int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM conversations
         WHERE user_id = ? AND booking_id = ? AND status = 'open'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$userId, $bookingId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $ins = $pdo->prepare(
        "INSERT INTO conversations (user_id, booking_id, subject, last_message_at)
         VALUES (?, ?, ?, NOW())"
    );
    $ins->execute([$userId, $bookingId, mb_substr($subject, 0, 150)]);
    return (int) $pdo->lastInsertId();
}

/* Append a message to a conversation and bump last_message_at. */
function messaging_post_message(PDO $pdo, int $conversationId, string $senderType, int $senderId, string $body): int
{
    if (!in_array($senderType, ['admin', 'user'], true)) {
        throw new InvalidArgumentException("messaging_post_message(): senderType must be 'admin' or 'user'");
    }
    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('messaging_post_message(): empty body');
    }

    $ins = $pdo->prepare(
        "INSERT INTO conversation_messages (conversation_id, sender_type, sender_id, body)
         VALUES (?, ?, ?, ?)"
    );
    $ins->execute([$conversationId, $senderType, $senderId, $body]);
    $id = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), status = 'open' WHERE id = ?")
        ->execute([$conversationId]);

    return $id;
}

/* Mark every message the given reader has not yet seen in this thread as read. */
function messaging_mark_thread_read(PDO $pdo, int $conversationId, string $readerType): void
{
    $senderToClear = $readerType === 'admin' ? 'user' : 'admin';
    $pdo->prepare(
        "UPDATE conversation_messages
         SET read_at = NOW()
         WHERE conversation_id = ? AND sender_type = ? AND read_at IS NULL"
    )->execute([$conversationId, $senderToClear]);
}

/** Count of unread customer messages across every conversation (admin inbox badge). */
function messaging_admin_unread(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM conversation_messages
         WHERE sender_type = 'user' AND read_at IS NULL"
    )->fetchColumn();
}

/** Count of unread admin messages in one customer's conversations. */
function messaging_user_unread(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM conversation_messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE c.user_id = ? AND m.sender_type = 'admin' AND m.read_at IS NULL"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** True if $userId owns $conversationId. */
function messaging_user_owns_conversation(PDO $pdo, int $conversationId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    return (bool) $stmt->fetchColumn();
}
