<?php
// Authenticated serving for verification documents.
session_start();
require 'db.php';

function deny(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $msg;
    exit;
}

$type = $_GET['type'] ?? '';
if (!in_array($type, ['license', 'verification', 'damage'], true)) {
    deny(400, 'Bad request');
}

$isAdmin = !empty($_SESSION['admin_id']);
$sessionUserId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
if (!$isAdmin && !$sessionUserId) {
    deny(401, 'Sign in required');
}

$relPath = null;
$ownerId = null;

if ($type === 'damage') {
    $photoId = (int) ($_GET['photo'] ?? 0);
    $stmt = $pdo->prepare(
        "SELECT p.file_path AS p, b.user_id AS owner_id
         FROM booking_damage_photos p
         JOIN booking_damages d ON d.id = p.damage_id
         JOIN bookings b ON b.id = d.booking_id
         WHERE p.id = ?"
    );
    $stmt->execute([$photoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $ownerId = (int) $row['owner_id'];
        $relPath = $row['p'];
    }
} elseif (isset($_GET['user'])) {
    $targetUserId = (int) $_GET['user'];
    $column = $type === 'license' ? 'license_path' : 'verification_photo_path';
    $stmt = $pdo->prepare("SELECT id, {$column} AS p FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $ownerId = (int) $row['id'];
        $relPath = $row['p'];
    }
} elseif (isset($_GET['booking'])) {
    $bookingId = (int) $_GET['booking'];
    // The snapshot columns are bookings.license_file and bookings.verification_photo_path.
    $col = $type === 'license' ? 'license_file' : 'verification_photo_path';
    $stmt = $pdo->prepare("SELECT user_id, {$col} AS p FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $ownerId = (int) $row['user_id'];
        $relPath = $row['p'];
    }
} else {
    deny(400, 'Bad request');
}

if (!$relPath) {
    deny(404, 'Not found');
}
if (!$isAdmin && $ownerId !== $sessionUserId) {
    deny(403, 'Forbidden');
}

// Resolve and confine the path.
$allowedRoots = [
    realpath(__DIR__ . '/uploads/licenses'),
    realpath(__DIR__ . '/uploads/verification'),
    realpath(__DIR__ . '/uploads/damages'), // Phase 11 damage evidence
    realpath(__DIR__ . '/assets/licenses'), // register.php's historical destination
];
$allowedRoots = array_filter($allowedRoots);

$full = realpath(__DIR__ . '/' . ltrim($relPath, '/\\'));
if ($full === false || !is_file($full)) {
    deny(404, 'Not found');
}
$inAllowed = false;
foreach ($allowedRoots as $root) {
    if (strncmp($full, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0) {
        $inAllowed = true;
        break;
    }
}
if (!$inAllowed) {
    deny(403, 'Forbidden');
}

$imageType = @exif_imagetype($full);
$mime = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png'][$imageType] ?? null;
if ($mime === null) {
    deny(415, 'Unsupported file');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $type . '.' . ($imageType === IMAGETYPE_PNG ? 'png' : 'jpg') . '"');
header('Cache-Control: private, no-store');
readfile($full);
