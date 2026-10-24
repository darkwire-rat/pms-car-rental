<?php
// Same validation/storage story as upload_requirement.php, adapted from multipart to base64 input.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);
$userId = (int) $user['id'];

$type = $_GET['type'] ?? '';
$config = [
  'license'      => ['column' => 'license_path',            'dir' => 'uploads/licenses',     'label' => "driver's licence"],
  'verification' => ['column' => 'verification_photo_path', 'dir' => 'uploads/verification', 'label' => 'verification photo'],
][$type] ?? null;

if ($config === null) {
  api_error('Unknown document type.', [], 400);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$base64 = (string) ($data['base64'] ?? '');
if ($base64 === '') {
  api_error('Please choose an image to upload.', [], 400);
}

$binary = base64_decode($base64, true);
if ($binary === false) {
  api_error('That file could not be read.', [], 400);
}
if (strlen($binary) > 3 * 1024 * 1024) {
  api_error('The image must be 3MB or smaller.', [], 400);
}

$tmpPath = tempnam(sys_get_temp_dir(), 'pms_upload_');
file_put_contents($tmpPath, $binary);

$detected = @exif_imagetype($tmpPath);
$allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
if (!$detected || !isset($allowed[$detected])) {
  @unlink($tmpPath);
  api_error('Only JPG and PNG images are accepted.', [], 400);
}

$uploadDir = __DIR__ . '/../../../' . $config['dir'];
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

if (!rename($tmpPath, $targetPath)) {
  @unlink($tmpPath);
  api_error('Failed to store the file.', [], 500);
}
$relPath = $config['dir'] . '/' . $targetName;

$stmt = $pdo->prepare("SELECT {$config['column']} AS old FROM users WHERE id = ?");
$stmt->execute([$userId]);
$old = $stmt->fetchColumn();

if ($type === 'verification') {
  $pdo->prepare("UPDATE users SET verification_photo_path = ?, verification_status = 'pending', verified_at = NULL WHERE id = ?")
    ->execute([$relPath, $userId]);
} else {
  $pdo->prepare("UPDATE users SET license_path = ? WHERE id = ?")->execute([$relPath, $userId]);
}

// Remove the previous file only if no booking snapshot still points at it.
if ($old) {
  $ref = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE license_file = ? OR verification_photo_path = ?");
  $ref->execute([$old, $old]);
  if ((int) $ref->fetchColumn() === 0) {
    $oldFull = realpath(__DIR__ . '/../../../' . ltrim($old, '/\\'));
    if ($oldFull && is_file($oldFull)) {
      @unlink($oldFull);
    }
  }
}

api_success(['label' => $config['label']], 'Uploaded.');
