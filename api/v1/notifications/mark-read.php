<?php
// Marks one notification read, scoped to the authenticated user.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$id = $data['id'] ?? null;
if ($id === null || !is_numeric($id)) {
  api_error('id is required.', ['id' => 'id is required.'], 422);
}

$stmt = $pdo->prepare(
  "UPDATE notifications SET is_read = 1, read_at = NOW()
   WHERE id = ? AND recipient_type = 'user' AND recipient_id = ? AND is_read = 0"
);
$stmt->execute([(int) $id, $user['id']]);

$unread = $pdo->prepare(
  "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'user' AND recipient_id = ? AND is_read = 0"
);
$unread->execute([$user['id']]);

api_success(['updated' => $stmt->rowCount(), 'unread_count' => (int) $unread->fetchColumn()]);
