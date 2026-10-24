<?php
// Decline a booking request from the admin transaction detail page.
session_start();
require 'db_connect.php'; // $conn (mysqli)
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking id']);
    exit;
}

$bookingId = (int) $_POST['id'];
$adminId   = (int) $_SESSION['admin_id'];
$reason    = trim($_POST['reason'] ?? '');

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A reason is required when declining a booking.']);
    exit;
}
if (mb_strlen($reason) > 255) {
    $reason = mb_substr($reason, 0, 255);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        SELECT b.status, b.user_id, v.title AS vehicle_title
        FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.id = ? FOR UPDATE");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    $bookingRow = $res->fetch_assoc();
    $current = $bookingRow['status'];

    if (!in_array($current, ['pending_review', 'info_requested'], true)) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This booking is not awaiting review (current status: ' . booking_status_label($current) . ').']);
        exit;
    }

    $upd = $conn->prepare("UPDATE bookings SET status = 'declined' WHERE id = ? LIMIT 1");
    $upd->bind_param('i', $bookingId);
    $upd->execute();

    $hist = $conn->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, ?, 'declined', 'admin', ?, ?)"
    );
    $hist->bind_param('isis', $bookingId, $current, $adminId, $reason);
    $hist->execute();

    notify_user(
        $conn,
        (int) $bookingRow['user_id'],
        'reservation_declined',
        'Reservation declined: ' . $bookingRow['vehicle_title'],
        'Your booking for ' . $bookingRow['vehicle_title'] . ' was declined. Reason: ' . $reason,
        'transactions.php',
        $bookingId
    );

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
