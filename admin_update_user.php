<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type
header('Content-Type: application/json');

session_start();
require 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Get form data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Input validation
if (empty($name) || empty($email) || $user_id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Name and email are required']));
}

try {
    // Check if email already exists for another user
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param('si', $email, $user_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        throw new Exception('Email already exists');
    }

    // Update user's name and email
    $query = "UPDATE users SET 
              name = ?, 
              email = ?
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('ssi', $name, $email, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update user: ' . $stmt->error);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}