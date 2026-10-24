<?php
session_start();
require 'db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');
$code = trim($data['code'] ?? '');
$newPassword = $data['new_password'] ?? '';

if (!$email || !$code || !$newPassword) {
  http_response_code(400);
  echo json_encode(['error' => 'Email, code, and new password are required.']);
  exit;
}

if (strlen($newPassword) < 6) {
  http_response_code(400);
  echo json_encode(['error' => 'New password must be at least 6 characters.']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, reset_code, reset_code_expires FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !$user['reset_code'] || $user['reset_code'] !== $code) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid or expired reset code.']);
  exit;
}

if (!$user['reset_code_expires'] || new DateTime($user['reset_code_expires']) < new DateTime()) {
  http_response_code(400);
  echo json_encode(['error' => 'This reset code has expired. Please request a new one.']);
  exit;
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $pdo->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_code_expires = NULL WHERE id = ?");
$update->execute([$newHash, $user['id']]);

echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
