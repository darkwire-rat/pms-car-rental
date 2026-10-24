<?php
session_start();
require 'db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['error' => 'Please provide a valid email address.']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $expires = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

  $update = $pdo->prepare("UPDATE users SET reset_code = ?, reset_code_expires = ? WHERE id = ?");
  $update->execute([$code, $expires, $user['id']]);

  $subject = 'PMS Car Rental — Password Reset Code';
  $message = "Hi {$user['name']},\r\n\r\nYour password reset code is: {$code}\r\n\r\nThis code expires in 15 minutes. If you did not request this, you can ignore this email.\r\n";
  $headers = "From: PMS Car Rental <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";

  @mail($email, $subject, $message, $headers);
}

echo json_encode(['success' => true, 'message' => 'If that email is registered, a reset code has been sent.']);
