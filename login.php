<?php
// login.php
session_start();
require 'db.php';
header('Content-Type: application/json');
// Prevent PHP warnings/notices from rendering HTML in JSON responses
ini_set('display_errors', 0);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing email or password.']);
  exit;
}

// Note: the users table stores hashed passwords in the `password` column
$stmt = $pdo->prepare("SELECT id,name,email,password FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Invalid email or password.']);
  exit;
}

// set session (do not store password hash in session)
$_SESSION['user'] = [
  'id' => $user['id'],
  'name' => $user['name'],
  'email' => $user['email']
];

echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
