<?php
// Saves the return-inspection form as a draft.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/return_flow.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$fuelLevelIn = ($_POST['fuel_level_in'] ?? '') !== '' ? $_POST['fuel_level_in'] : null;
$conditionNotes = trim((string) ($_POST['condition_notes'] ?? ''));
$hasDamage = !empty($_POST['has_damage']);
$damageNotes = trim((string) ($_POST['damage_notes'] ?? ''));
$actualReturnAt = trim((string) ($_POST['actual_return_at'] ?? ''));

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Booking id is required.']);
    exit;
}
if ($fuelLevelIn !== null && !return_flow_valid_fuel_level($fuelLevelIn)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fuel level must be between 0 and 8 (eighths of a tank).']);
    exit;
}

try {
    $pdo->beginTransaction();

    $booking = $pdo->prepare("SELECT id, status, actual_return_at FROM bookings WHERE id = ? FOR UPDATE");
    $booking->execute([$bookingId]);
    $booking = $booking->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found.']);
        exit;
    }
    if (!in_array($booking['status'], ['returned'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This booking has not been returned yet.']);
        exit;
    }

    // Optional correction to the actual return timestamp.
    if ($actualReturnAt !== '') {
        try {
            $newReturnAt = (new DateTime($actualReturnAt))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid return date/time.']);
            exit;
        }
        if ($newReturnAt !== $booking['actual_return_at']) {
            $pdo->prepare("UPDATE bookings SET actual_return_at = ? WHERE id = ?")->execute([$newReturnAt, $bookingId]);
            $pdo->prepare(
                "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
                 VALUES (?, 'returned', 'returned', 'admin', ?, ?)"
            )->execute([$bookingId, (int) $_SESSION['admin_id'], 'Actual return time corrected during inspection.']);
        }
    }

    $inspection = return_flow_save_inspection_draft(
        $pdo, $bookingId,
        $fuelLevelIn !== null ? (int) $fuelLevelIn : null,
        $conditionNotes, $hasDamage, $damageNotes,
        (int) $_SESSION['admin_id']
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'inspection' => $inspection]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the inspection draft.']);
}
