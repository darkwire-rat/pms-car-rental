<?php
// POST /api/v1/profile/change-password.php — mirrors change_password.php exactly.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (!$currentPassword || !$newPassword) {
  api_error('Current password and new password are required.', [], 422);
}
if (strlen($newPassword) < 6) {
  api_error('New password must be at least 6 characters.', ['new_password' => 'Too short.'], 422);
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

if (!$row || !password_verify($currentPassword, $row['password'])) {
  api_error('Current password is incorrect.', [], 401);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);

api_success([], 'Password updated successfully.');
