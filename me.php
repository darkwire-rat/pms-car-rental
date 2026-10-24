<?php
// me.php - returns the current user session as JSON
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
  echo json_encode(['logged_in' => false]);
  exit;
}

$user = $_SESSION['user'];

// Created_at isn't stored in the session, so it's looked up fresh on every call.
$stmt = $pdo->prepare("SELECT created_at, profile_picture_path FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

// don't return password_hash
echo json_encode([
  'logged_in' => true,
  'user' => [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'] ?? 'user',
    'created_at' => $row['created_at'] ?? null,
    'profile_picture_path' => $row['profile_picture_path'] ?? null
  ]
]);
