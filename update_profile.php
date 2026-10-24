<?php
session_start();
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Please log in first']);
  exit;
}

csrf_require();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$name = isset($data['name']) ? trim($data['name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';

if ($name === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Name is required.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
  exit;
}

$userId = (int)$_SESSION['user']['id'];

$check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$check->execute([$email, $userId]);

if ($check->fetch()) {
  echo json_encode(['success' => false, 'error' => 'Email already in use.']);
  exit;
}

$update = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
$update->execute([$name, $email, $userId]);

// Keep the session in sync so me.php reflects the change immediately.
$_SESSION['user']['name'] = $name;
$_SESSION['user']['email'] = $email;

echo json_encode(['success' => true]);
