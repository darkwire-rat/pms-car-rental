<?php
// Auth, ownership-checked.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/messaging.php';

$user = api_require_auth($pdo);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  api_error('Conversation id is required.', ['id' => 'Conversation id is required.'], 422);
}
if (!messaging_user_owns_conversation($pdo, $id, (int) $user['id'])) {
  api_error('Conversation not found.', [], 404);
}

$t = $pdo->prepare("
    SELECT c.*, b.booking_ref, v.title AS vehicle_title
    FROM conversations c
    LEFT JOIN bookings b ON b.id = c.booking_id
    LEFT JOIN vehicles v ON v.id = b.vehicle_id
    WHERE c.id = ?
");
$t->execute([$id]);
$thread = $t->fetch(PDO::FETCH_ASSOC);

messaging_mark_thread_read($pdo, $id, 'user');

$m = $pdo->prepare("
    SELECT sender_type, body, created_at
    FROM conversation_messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC, id ASC
");
$m->execute([$id]);

api_success(['conversation' => $thread, 'messages' => $m->fetchAll()]);
