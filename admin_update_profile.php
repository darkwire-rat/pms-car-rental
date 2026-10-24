<?php
session_start();
require 'db_connect.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Identity is derived from the session only.
$adminId = (int)$_SESSION['admin_id'];
$action = $_POST['action'] ?? 'update_profile';

if ($action === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Current password and new password are required.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin || !password_verify($currentPassword, $admin['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $update->bind_param('si', $newHash, $adminId);
    $update->execute();

    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    exit;
}

// Default action: update profile (name/email).
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

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

// Uniqueness is checked within admins only.
$check = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
$check->bind_param('si', $email, $adminId);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Email already in use.']);
    exit;
}

$update = $conn->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
$update->bind_param('ssi', $name, $email, $adminId);
$update->execute();

// Keep the session in sync so the topbar reflects the change immediately.
$_SESSION['admin_name'] = $name;

echo json_encode(['success' => true]);
