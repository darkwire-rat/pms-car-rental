<?php
// POST /api/v1/profile/update.php — mirrors update_profile.php's rules exactly.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');

if ($name === '') {
  api_error('Name is required.', ['name' => 'Name is required.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  api_error('Please enter a valid email address.', ['email' => 'Invalid email.'], 422);
}

$check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$check->execute([$email, $user['id']]);
if ($check->fetch()) {
  api_error('Email already in use.', ['email' => 'Email already in use.'], 409);
}

$update = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
$update->execute([$name, $email, $user['id']]);

api_success(['user' => ['id' => $user['id'], 'name' => $name, 'email' => $email]], 'Profile updated.');
