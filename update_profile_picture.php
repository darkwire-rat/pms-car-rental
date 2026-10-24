<?php
session_start();
require 'db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Please log in first']);
  exit;
}

if (empty($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Please choose an image to upload.']);
  exit;
}

$file = $_FILES['profile_picture'];

if ($file['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Error uploading file.']);
  exit;
}

// Size cap matches register.php's license-upload limit.
$maxSize = 3 * 1024 * 1024;
if ($file['size'] > $maxSize) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Profile picture must be 3MB or smaller.']);
  exit;
}

// Validate real image content, same exif_imagetype pattern as register.php/admin_add_vehicle.php.
$detected = @exif_imagetype($file['tmp_name']);
$allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
if (!$detected || !isset($allowed[$detected])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Only JPG and PNG images are accepted.']);
  exit;
}

// Assets/avatars/ — profile pictures are public.
$uploadDir = __DIR__ . '/assets/avatars/';
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0755, true);
}

// Server-generated filename only — never derived from the uploaded filename.
try {
  $basename = bin2hex(random_bytes(8));
} catch (Exception $ex) {
  $basename = uniqid('avatar_', true);
}
$ext = $allowed[$detected];
$targetName = $basename . '.' . $ext;
$targetPath = $uploadDir . $targetName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to store uploaded file.']);
  exit;
}

$relativePath = 'assets/avatars/' . $targetName;
$userId = (int)$_SESSION['user']['id'];

try {
  $stmt = $pdo->prepare("SELECT profile_picture_path FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $existing = $stmt->fetch();

  $update = $pdo->prepare("UPDATE users SET profile_picture_path = ? WHERE id = ?");
  $update->execute([$relativePath, $userId]);
} catch (PDOException $e) {
  // DB update failed — clean up the orphaned new file and leave the existing picture untouched rather than losing both.
  @unlink($targetPath);
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to update profile picture.']);
  exit;
}

// Only delete the old file once the new one is written AND the DB row actually points at it.
if (!empty($existing['profile_picture_path'])) {
  $oldPath = __DIR__ . '/' . $existing['profile_picture_path'];
  if (is_file($oldPath)) {
    @unlink($oldPath);
  }
}

$_SESSION['user']['profile_picture_path'] = $relativePath;

echo json_encode(['success' => true, 'profile_picture_path' => $relativePath]);
