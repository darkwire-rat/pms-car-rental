<?php
// Revision: the old guard refused to delete a booking with `status 'active'`.
session_start();
require 'db_connect.php';
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/csrf.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

csrf_require();

// Validate input
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid booking ID']));
}

$booking_id = (int)$_POST['id'];

try {
    $check = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
    $check->bind_param('i', $booking_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();

    if (!$result) {
        http_response_code(404);
        throw new Exception('Booking not found');
    }

    if (!booking_status_can_hard_delete($result['status'])) {
        http_response_code(409);
        throw new Exception(
            'This booking is "' . booking_status_label($result['status']) . '" and cannot be deleted — '
            . 'it has moved past approval and may have payment, inspection or deposit records attached. '
            . 'Cancel or decline it instead.'
        );
    }

    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $booking_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        throw new Exception('Failed to delete booking');
    }
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
