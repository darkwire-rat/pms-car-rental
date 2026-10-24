<?php
// The user's conversation list, same query my_messages.php uses for its sidebar.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$list = $pdo->prepare("
    SELECT c.id, c.subject, c.status, c.last_message_at, c.booking_id,
           b.booking_ref, v.title AS vehicle_title,
           (SELECT COUNT(*) FROM conversation_messages m
            WHERE m.conversation_id = c.id AND m.sender_type = 'admin' AND m.read_at IS NULL) AS unread,
           (SELECT m2.body FROM conversation_messages m2
            WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_body
    FROM conversations c
    LEFT JOIN bookings b ON b.id = c.booking_id
    LEFT JOIN vehicles v ON v.id = b.vehicle_id
    WHERE c.user_id = ?
    ORDER BY (unread > 0) DESC, c.last_message_at DESC
");
$list->execute([$user['id']]);
api_success(['conversations' => $list->fetchAll()]);
