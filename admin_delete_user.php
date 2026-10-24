<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type
header('Content-Type: application/json');

// Start session and include database connection
session_start();
require 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Get and validate user ID
if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid user ID']));
}

$user_id = (int)$_POST['user_id'];

// Prevent deleting self
if ($user_id == $_SESSION['admin_id']) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'You cannot delete your own account']));
}

try {
    // First, check if the user exists
    $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    if (!$check) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check->bind_param('i', $user_id);
    if (!$check->execute()) {
        throw new Exception('Database error: ' . $check->error);
    }
    
    if ($check->get_result()->num_rows === 0) {
        throw new Exception('User not found');
    }

    // Delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete user: ' . $stmt->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Delete User Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'An error occurred while deleting the user'
    ]);
}