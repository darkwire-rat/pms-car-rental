<?php
/* Notification writes —. */

/**
 * .
 * @param PDO|mysqli $db            An OPEN connection, ideally mid-transaction.
 * @param string     $recipientType 'admin' | 'user'
 * @param int        $recipientId   user id, or 0 for "any admin"
 * @param string     $type          machine tag, e.g. 'reservation_submitted'
 * @param string     $title         <= 150 chars, shown bold in the dropdown
 * @param ?string    $body          optional longer line
 * @param ?string    $linkUrl       optional relative URL the item links to
 * @param ?int       $bookingId     optional booking this concerns
 */
function notify($db, string $recipientType, int $recipientId, string $type, string $title, ?string $body = null, ?string $linkUrl = null, ?int $bookingId = null): void
{
    if (!in_array($recipientType, ['admin', 'user'], true)) {
        throw new InvalidArgumentException("notify(): recipientType must be 'admin' or 'user'");
    }

    $title = mb_substr($title, 0, 150);
    if ($linkUrl !== null) {
        $linkUrl = mb_substr($linkUrl, 0, 255);
    }

    $sql = "INSERT INTO notifications (recipient_type, recipient_id, type, title, body, link_url, booking_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    if ($db instanceof PDO) {
        $db->prepare($sql)->execute([$recipientType, $recipientId, $type, $title, $body, $linkUrl, $bookingId]);
        return;
    }

    if ($db instanceof mysqli) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('sissssi', $recipientType, $recipientId, $type, $title, $body, $linkUrl, $bookingId);
        $stmt->execute();
        return;
    }

    throw new InvalidArgumentException('notify(): $db must be a PDO or mysqli connection');
}

/** Notify one customer. */
function notify_user($db, int $userId, string $type, string $title, ?string $body = null, ?string $linkUrl = null, ?int $bookingId = null): void
{
    notify($db, 'user', $userId, $type, $title, $body, $linkUrl, $bookingId);
}

/** Notify the admin inbox (recipient_id 0 — single admin account). */
function notify_admin($db, string $type, string $title, ?string $body = null, ?string $linkUrl = null, ?int $bookingId = null): void
{
    notify($db, 'admin', 0, $type, $title, $body, $linkUrl, $bookingId);
}
