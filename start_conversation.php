<?php
// start_conversation.php - Create a customer conversation with the PMS team.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/messaging.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

csrf_require();

$user = $_SESSION['user'] ?? null;
if (!$user || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please sign in.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$subject = trim((string) ($body['subject'] ?? ''));
$text = trim((string) ($body['body'] ?? ''));

if ($subject === '' || $text === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A subject and message are required.']);
    exit;
}
if (mb_strlen($subject) > 150) {
    $subject = mb_substr($subject, 0, 150);
}
if (mb_strlen($text) > 2000) {
    $text = mb_substr($text, 0, 2000);
}

try {
    $pdo->beginTransaction();

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
    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not start the conversation.']);
}
