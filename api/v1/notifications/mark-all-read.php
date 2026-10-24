<?php
// Marks every unread notification for the authenticated user as read.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$stmt = $pdo->prepare(
  "UPDATE notifications SET is_read = 1, read_at = NOW()
   WHERE recipient_type = 'user' AND recipient_id = ? AND is_read = 0"
);
$stmt->execute([$user['id']]);
$updated = $stmt->rowCount();

api_success(['updated' => $updated, 'unread_count' => 0]);
