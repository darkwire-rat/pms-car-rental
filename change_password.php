<?php
session_start();
require 'db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Please log in first']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (!$currentPassword || !$newPassword) {
  http_response_code(400);
  echo json_encode(['error' => 'Current password and new password are required.']);
  exit;
}

if (strlen($newPassword) < 6) {
  http_response_code(400);
  echo json_encode(['error' => 'New password must be at least 6 characters.']);
  exit;
}

$userId = (int)$_SESSION['user']['id'];

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Current password is incorrect.']);
  exit;
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->execute([$newHash, $userId]);

echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
