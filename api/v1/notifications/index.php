<?php
// The authenticated customer's notifications, newest first. ?limit=, ?before_id= for paging.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
$limit = max(1, min(100, $limit));
$beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : 0;

$sql = "SELECT id, type, title, body, link_url, booking_id, is_read, created_at
        FROM notifications
        WHERE recipient_type = 'user' AND recipient_id = ?";
$params = [$user['id']];
if ($beforeId > 0) {
  $sql .= " AND id < ?";
  $params[] = $beforeId;
}
$sql .= " ORDER BY created_at DESC, id DESC LIMIT " . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
  $r['id'] = (int) $r['id'];
  $r['booking_id'] = $r['booking_id'] !== null ? (int) $r['booking_id'] : null;
  $r['is_read'] = (bool) $r['is_read'];
}
unset($r);

api_success(['notifications' => $rows]);
