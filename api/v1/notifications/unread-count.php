<?php
// Cheap poll target for the app's bell badge.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$stmt = $pdo->prepare(
  "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'user' AND recipient_id = ? AND is_read = 0"
);
$stmt->execute([$user['id']]);

api_success(['count' => (int) $stmt->fetchColumn()]);
