<?php
// Body {conversation_id, body}.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/messaging.php';
require_once __DIR__ . '/../../../lib/notify.php';

$user = api_require_auth($pdo);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$conversationId = (int) ($data['conversation_id'] ?? 0);
$text = trim((string) ($data['body'] ?? ''));

if ($conversationId <= 0 || $text === '') {
  api_error('A conversation and a message are required.', [], 422);
}
if (mb_strlen($text) > 2000) {
  $text = mb_substr($text, 0, 2000);
}

$pdo->beginTransaction();
try {
  $conv = $pdo->prepare("SELECT c.id, c.user_id, c.booking_id, v.title AS vehicle_title
                         FROM conversations c
                         LEFT JOIN bookings b ON b.id = c.booking_id
                         LEFT JOIN vehicles v ON v.id = b.vehicle_id
                         WHERE c.id = ? FOR UPDATE");
  $conv->execute([$conversationId]);
  $conversation = $conv->fetch(PDO::FETCH_ASSOC);
  if (!$conversation) {
    $pdo->rollBack();
    api_error('Conversation not found.', [], 404);
  }
  if ((int) $conversation['user_id'] !== (int) $user['id']) {
    $pdo->rollBack();
    api_error('This is not your conversation.', [], 403);
  }

  messaging_post_message($pdo, $conversationId, 'user', (int) $user['id'], $text);

  $bookingId = $conversation['booking_id'] !== null ? (int) $conversation['booking_id'] : null;
  notify_admin(
    $pdo,
    'customer_message',
    'Message from ' . $user['name'],
    mb_substr($text, 0, 140),
    'admin_messages.php?id=' . $conversationId,
    $bookingId
  );

  $pdo->commit();
  api_success([], 'Reply sent.');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  throw $e;
}
