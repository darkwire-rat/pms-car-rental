<?php
// Multipart upload of a verification document to the customer's profile.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/requirements.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in first']);
    exit;
}
$userId = (int) $_SESSION['user']['id'];

csrf_require();

$type = $_GET['type'] ?? '';
$config = [
    'license'      => ['column' => 'license_path',            'dir' => 'uploads/licenses',     'label' => "driver's licence"],
    'verification' => ['column' => 'verification_photo_path', 'dir' => 'uploads/verification', 'label' => 'verification photo'],
][$type] ?? null;

if ($config === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown document type.']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please choose an image to upload.']);
    exit;
}
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Error uploading file.']);
    exit;
}
if ($file['size'] > 3 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'The image must be 3MB or smaller.']);
    exit;
}

$detected = @exif_imagetype($file['tmp_name']);
$allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
if (!$detected || !isset($allowed[$detected])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only JPG and PNG images are accepted.']);
    exit;
}

$uploadDir = __DIR__ . '/' . $config['dir'];
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

try {
    $basename = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $basename = uniqid('doc_', true);
}
$targetName = $basename . '.' . $allowed[$detected];
$targetPath = $uploadDir . '/' . $targetName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to store the file.']);
    exit;
}
$relPath = $config['dir'] . '/' . $targetName;

try {
    $stmt = $pdo->prepare("SELECT {$config['column']} AS old FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();

    if ($type === 'verification') {
        $pdo->prepare("UPDATE users SET verification_photo_path = ?, verification_status = 'pending', verified_at = NULL WHERE id = ?")
            ->execute([$relPath, $userId]);
    } else {
        $pdo->prepare("UPDATE users SET license_path = ? WHERE id = ?")->execute([$relPath, $userId]);
    }
} catch (PDOException $e) {
    @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the upload.']);
    exit;
}

// Remove the previous file only if no booking snapshot still points at it.
if ($old) {
    $ref = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE license_file = ? OR verification_photo_path = ?");
    $ref->execute([$old, $old]);
    if ((int) $ref->fetchColumn() === 0) {
        $oldFull = realpath(__DIR__ . '/' . ltrim($old, '/\\'));
        if ($oldFull && is_file($oldFull)) {
            @unlink($oldFull);
        }
    }
}

echo json_encode(['success' => true, 'label' => $config['label']]);
