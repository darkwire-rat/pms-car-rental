<?php
// Bearer-token twin of start_conversation.php.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/messaging.php';
require_once __DIR__ . '/../../../lib/notify.php';

$user = api_require_auth($pdo);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$subject = trim((string) ($data['subject'] ?? ''));
$text = trim((string) ($data['body'] ?? ''));

if ($subject === '' || $text === '') {
  api_error('A subject and message are required.', [], 422);
}
if (mb_strlen($subject) > 150) {
  $subject = mb_substr($subject, 0, 150);
}
if (mb_strlen($text) > 2000) {
  $text = mb_substr($text, 0, 2000);
}

$pdo->beginTransaction();
try {
  $conversation = $pdo->prepare(
    'INSERT INTO conversations (user_id, booking_id, subject, last_message_at) VALUES (?, NULL, ?, NOW())'
  );
  $conversation->execute([(int) $user['id'], $subject]);
  $conversationId = (int) $pdo->lastInsertId();

  messaging_post_message($pdo, $conversationId, 'user', (int) $user['id'], $text);
  notify_admin(
    $pdo,
    'customer_message',
    'Message from ' . ($user['name'] ?? 'Customer'),
    mb_substr($text, 0, 140),
    'admin_messages.php?id=' . $conversationId,
    null
  );

  $pdo->commit();
  api_success(['conversation_id' => $conversationId], 'Message sent.', 201);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  throw $e;
}
