<?php
// POST a reply into a conversation.
session_start();
require 'db.php'; // $pdo
require_once __DIR__ . '/lib/messaging.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

csrf_require();

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$conversationId = (int) ($body['conversation_id'] ?? 0);
$text = trim((string) ($body['body'] ?? ''));

if ($conversationId <= 0 || $text === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A conversation and a message are required.']);
    exit;
}
if (mb_strlen($text) > 2000) {
    $text = mb_substr($text, 0, 2000);
}

$isAdmin = !empty($_SESSION['admin_id']);
$isUser  = !empty($_SESSION['user']['id']);
if (!$isAdmin && !$isUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please sign in.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $conv = $pdo->prepare("SELECT c.id, c.user_id, c.booking_id, u.name AS user_name, v.title AS vehicle_title
                           FROM conversations c
                           JOIN users u ON u.id = c.user_id
                           LEFT JOIN bookings b ON b.id = c.booking_id
                           LEFT JOIN vehicles v ON v.id = b.vehicle_id
                           WHERE c.id = ? FOR UPDATE");
    $conv->execute([$conversationId]);
    $conversation = $conv->fetch(PDO::FETCH_ASSOC);
    if (!$conversation) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
        exit;
    }

    if ($isAdmin) {
        $senderType = 'admin';
        $senderId = (int) $_SESSION['admin_id'];
    } else {
        if ((int) $conversation['user_id'] !== (int) $_SESSION['user']['id']) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'This is not your conversation.']);
            exit;
        }
        $senderType = 'user';
        $senderId = (int) $_SESSION['user']['id'];
    }

    messaging_post_message($pdo, $conversationId, $senderType, $senderId, $text);

    $bookingId = $conversation['booking_id'] !== null ? (int) $conversation['booking_id'] : null;
    $context = $conversation['vehicle_title'] ? (' about ' . $conversation['vehicle_title']) : '';

    if ($senderType === 'user') {
        notify_admin(
            $pdo,
            'customer_message',
            'Message from ' . $conversation['user_name'],
            mb_substr($text, 0, 140),
            'admin_messages.php?id=' . $conversationId,
            $bookingId
        );
    } else {
        notify_user(
            $pdo,
            (int) $conversation['user_id'],
            'admin_message',
            'New message from PMS' . $context,
            mb_substr($text, 0, 140),
            'my_messages.php?id=' . $conversationId,
            $bookingId
        );
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send the message.']);
}
