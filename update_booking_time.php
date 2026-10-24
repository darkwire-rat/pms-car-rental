<?php
// Revision: pickup_time/dropoff_time are now generated columns derived from pickup_at/dropoff_at and can no longer be written.
session_start();
require 'db_connect.php'; // $conn (mysqli)
require 'db.php';         // $pdo (PDO) — needed for lib/booking_rules.php
require_once __DIR__ . '/lib/booking_rules.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Validate input
if (!isset($_POST['transaction_id']) || !isset($_POST['pickup_time']) || !isset($_POST['dropoff_time'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing required fields']));
}

$booking_id = (int)$_POST['transaction_id'];
$pickup_time = $_POST['pickup_time'];
$dropoff_time = $_POST['dropoff_time'];

try {
    $stmt = $pdo->prepare("SELECT pickup_at, dropoff_at FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    // Keep each side's existing date, replace only the time-of-day — this form has no date fields, only time inputs.
    $newPickupAt  = (new DateTime($booking['pickup_at']))->setTime(
        (int)explode(':', $pickup_time)[0], (int)explode(':', $pickup_time)[1]
    )->format('Y-m-d H:i:s');
    $newDropoffAt = (new DateTime($booking['dropoff_at']))->setTime(
        (int)explode(':', $dropoff_time)[0], (int)explode(':', $dropoff_time)[1]
    )->format('Y-m-d H:i:s');

    $validationError = booking_validate_rental_window($newPickupAt, $newDropoffAt);
    if ($validationError !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $validationError]);
        exit;
    }

    // Update the booking times
    $update = $conn->prepare("
        UPDATE bookings
        SET pickup_at = ?,
            dropoff_at = ?
        WHERE id = ?
    ");
    $update->bind_param('ssi', $newPickupAt, $newDropoffAt, $booking_id);

    if ($update->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update booking');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
