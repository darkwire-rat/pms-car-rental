<?php
// Cheap poll target for the bell badge.
session_start();
require 'db.php';
header('Content-Type: application/json');

require_once __DIR__ . '/lib/notify_read.php';
$who = notification_audience($_GET['audience'] ?? null);
if (!$who) {
    echo json_encode(['authenticated' => false, 'count' => 0]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0"
);
$stmt->execute([$who['type'], $who['id']]);

echo json_encode([
    'authenticated' => true,
    'audience'      => $who['type'],
    'count'         => (int) $stmt->fetchColumn(),
]);
