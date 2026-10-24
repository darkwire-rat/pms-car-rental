<?php
// Records a reported damage entry against a 'returned' booking, with zero or more evidence photos.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/return_flow.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$description = trim((string) ($_POST['description'] ?? ''));
$estimatedAmount = $_POST['estimated_amount'] ?? '';

if ($bookingId <= 0 || $description === '' || !is_numeric($estimatedAmount) || (float) $estimatedAmount < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A description and a non-negative estimated amount are required.']);
    exit;
}

// Validate every photo BEFORE touching the database.
$allowedExt = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
$photoFiles = $_FILES['photos'] ?? null;
$toStore = [];

if ($photoFiles && is_array($photoFiles['name'] ?? null)) {
    $count = count($photoFiles['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($photoFiles['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($photoFiles['error'][$i] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Error uploading one of the photos.']);
            exit;
        }
        if ($photoFiles['size'][$i] > 3 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Each photo must be 3MB or smaller.']);
            exit;
        }
        $detected = @exif_imagetype($photoFiles['tmp_name'][$i]);
        if (!$detected || !isset($allowedExt[$detected])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Only JPG and PNG images are accepted.']);
            exit;
        }
        $toStore[] = ['tmp' => $photoFiles['tmp_name'][$i], 'ext' => $allowedExt[$detected]];
    }
}

$movedFiles = [];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found.']);
        exit;
    }
    if ($booking['status'] !== 'returned') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Damage can only be reported while the booking is awaiting inspection (current status: ' . booking_status_label($booking['status']) . ').']);
        exit;
    }

    $damageId = return_flow_add_damage($pdo, $bookingId, $description, (float) $estimatedAmount, (int) $_SESSION['admin_id']);

    $uploadDir = __DIR__ . '/uploads/damages';
    if ($toStore && !is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    foreach ($toStore as $file) {
        try {
            $basename = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $basename = uniqid('damage_', true);
        }
        $targetName = $basename . '.' . $file['ext'];
        $targetPath = $uploadDir . '/' . $targetName;
        if (!move_uploaded_file($file['tmp'], $targetPath)) {
            throw new RuntimeException('Failed to store an uploaded photo.');
        }
        $movedFiles[] = $targetPath;
        return_flow_add_damage_photo($pdo, $damageId, 'uploads/damages/' . $targetName);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'damage_id' => $damageId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($movedFiles as $f) {
        @unlink($f);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to report the damage.']);
}
