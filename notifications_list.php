<?php
// The 10 most recent notifications for the current viewer, plus the unread count, as JSON for the bell dropdown.
session_start();
require 'db.php';
header('Content-Type: application/json');

require_once __DIR__ . '/lib/notify_read.php';
$who = notification_audience($_GET['audience'] ?? null);
if (!$who) {
    echo json_encode(['authenticated' => false, 'unread' => 0, 'items' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, type, title, body, link_url, booking_id, is_read, created_at
    FROM notifications
    WHERE recipient_type = ? AND recipient_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");
$stmt->execute([$who['type'], $who['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0"
);
$unreadStmt->execute([$who['type'], $who['id']]);

function notification_relative_time(string $ts): string
{
    $diff = time() - strtotime($ts);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($ts));
}

$items = array_map(function (array $r): array {
    return [
        'id'         => (int) $r['id'],
        'type'       => $r['type'],
        'title'      => $r['title'],
        'body'       => $r['body'],
        'link_url'   => $r['link_url'],
        'booking_id' => $r['booking_id'] !== null ? (int) $r['booking_id'] : null,
        'is_read'    => (int) $r['is_read'] === 1,
        'created_at' => $r['created_at'],
        'ago'        => notification_relative_time($r['created_at']),
    ];
}, $rows);

echo json_encode([
    'authenticated' => true,
    'audience'      => $who['type'],
    'unread'        => (int) $unreadStmt->fetchColumn(),
    'items'         => $items,
]);
