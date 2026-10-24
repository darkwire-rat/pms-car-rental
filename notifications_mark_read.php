<?php
// Marks one notification or, with no id, every unread notification for the current viewer as read.
session_start();
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/lib/notify_read.php';

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$audienceHint = $_POST['audience'] ?? $body['audience'] ?? null;
$who = notification_audience($audienceHint);
if (!$who) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in']);
    exit;
}

$id = $_POST['id'] ?? $body['id'] ?? null;

if ($id !== null && is_numeric($id)) {
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = 1, read_at = NOW()
        WHERE id = ? AND recipient_type = ? AND recipient_id = ? AND is_read = 0
    ");
    $stmt->execute([(int) $id, $who['type'], $who['id']]);
} else {
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = 1, read_at = NOW()
        WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0
    ");
    $stmt->execute([$who['type'], $who['id']]);
}

$unread = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0"
);
$unread->execute([$who['type'], $who['id']]);

echo json_encode(['success' => true, 'unread' => (int) $unread->fetchColumn()]);
