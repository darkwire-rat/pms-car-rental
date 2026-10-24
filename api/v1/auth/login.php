<?php
// POST /api/v1/auth/login.php — customer-only; never checks the admins table.
require_once __DIR__ . '/../common/bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
  api_error('Email and password are required.', [], 422);
}

$stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
  api_error('Invalid email or password.', [], 401);
}

$token = api_issue_token($pdo, (int) $user['id']);
api_success([
  'token' => $token,
  'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']],
], 'Logged in.');
