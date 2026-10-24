<?php
// register.php
require 'db.php';
header('Content-Type: application/json');
// Prevent PHP warnings/notices from rendering HTML in JSON responses
ini_set('display_errors', 0);


// Accept JSON body or multipart/form-data (with file)
$data = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!empty($_FILES) || ($contentType && stripos($contentType, 'multipart/') === 0)) {
  // form-data submission
  $data = $_POST;
} else {
  $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
}


$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$privacyConsent = $data['privacy_consent'] ?? false;


if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(['error' => 'Please provide a valid name, email, and a password (min 6 chars).']);
  exit;
}


// The client-side checkbox gate is not sufficient on its own for a compliance requirement.
if (empty($privacyConsent)) {
  http_response_code(400);
  echo json_encode(['error' => 'You must agree to the Privacy Policy to create an account.']);
  exit;
}


$licensePath = null;
// Handle uploaded license image if present
if (!empty($_FILES['license']) && $_FILES['license']['error'] !== UPLOAD_ERR_NO_FILE) {
  $file = $_FILES['license'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Error uploading file.']);
    exit;
  }


  // Validate size (max 3MB)
  $maxSize = 3 * 1024 * 1024;
  if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'License image must be 3MB or smaller.']);
    exit;
  }


  // Validate image type
  $tmp = $file['tmp_name'];
  $detected = @exif_imagetype($tmp);
  $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
  if (!$detected || !isset($allowed[$detected])) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG and PNG images are accepted for license.']);
    exit;
  }


  // Ensure upload directory exists
  $uploadDir = __DIR__ . '/assets/licenses/';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
  }


  // Generate safe unique filename
  try {
    $basename = bin2hex(random_bytes(8));
  } catch (Exception $ex) {
    $basename = uniqid('lic_', true);
  }
  $ext = $allowed[$detected];
  $targetName = $basename . '.' . $ext;
  $targetPath = $uploadDir . $targetName;


  if (!move_uploaded_file($tmp, $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to store uploaded file.']);
    exit;
  }


  // Relative path to store in DB
  $licensePath = 'assets/licenses/' . $targetName;
}


try {
  // check if email exists
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered.']);
    exit;
  }


  $hash = password_hash($password, PASSWORD_DEFAULT);

  // Users.license_path is a real column now.
  if ($licensePath) {
    $ins = $pdo->prepare("INSERT INTO users (name,email,password,license_path) VALUES (?,?,?,?)");
    $ins->execute([$name, $email, $hash, $licensePath]);
  } else {
    $ins = $pdo->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
    $ins->execute([$name, $email, $hash]);
  }


  echo json_encode(['success' => true, 'message' => 'User registered. You can now log in.']);
} catch (PDOException $e) {
  // If we stored a file but registration failed, attempt to remove the file to avoid orphaned uploads
  if (!empty($targetPath) && file_exists($targetPath)) {
    @unlink($targetPath);
  }
  http_response_code(500);
  echo json_encode(['error' => 'Registration failed', 'details' => $e->getMessage()]);
}



