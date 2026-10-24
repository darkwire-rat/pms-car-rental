<?php
// Creates a customer account.
require_once __DIR__ . '/../common/bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$privacyConsent = $data['privacy_consent'] ?? false;

$errors = [];
if (!$name) $errors['name'] = 'Name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'A valid email is required.';
if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
if (empty($privacyConsent)) $errors['privacy_consent'] = 'You must agree to the Privacy Policy.';
if ($errors) {
  api_error('Validation failed.', $errors, 422);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
  api_error('Email already registered.', ['email' => 'Email already registered.'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?,?,?)");
$ins->execute([$name, $email, $hash]);
$userId = (int) $pdo->lastInsertId();

$token = api_issue_token($pdo, $userId);
api_success(
  ['token' => $token, 'user' => ['id' => $userId, 'name' => $name, 'email' => $email]],
  'Account created.',
  201
);
