<?php
// Revision: no longer mutates vehicles.units_total.
session_start();
require 'db_connect.php'; // $conn (mysqli)
require 'db.php';         // $pdo (PDO) — needed for lib/booking_rules.php
require_once __DIR__ . '/lib/booking_rules.php';
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

$bookingId = intval($_POST['id']);
$adminId = (int) $_SESSION['admin_id'];

$conn->begin_transaction();
try {
    // Lock booking row
    $stmt = $conn->prepare("
        SELECT b.id, b.user_id, b.vehicle_id, b.pickup_at, b.dropoff_at, b.total_amount, b.paid,
               b.contact_number, b.status, v.title AS vehicle_title
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
    $booking = $res->fetch_assoc();

    if (!in_array($booking['status'], ['pending_review', 'info_requested'], true)) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This booking is not awaiting approval (current status: ' . $booking['status'] . ').']);
        exit;
    }

    $vehicle_id = intval($booking['vehicle_id']);
    $amount = (float)$booking['total_amount'];

    // Datetime-aware, overlap-correct availability check.
    if (!booking_is_vehicle_available($pdo, $vehicle_id, $booking['pickup_at'], $booking['dropoff_at'])) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No available units for this vehicle on the requested dates.']);
        exit;
    }

    // Approval no longer touches payment_status.
    $update = $conn->prepare("UPDATE bookings SET status = 'approved', payment_status = 'unpaid', approved_at = NOW() WHERE id = ? LIMIT 1");
    $update->bind_param('i', $bookingId);
    if (!$update->execute()) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update booking']);
        exit;
    }

    // Status history (Phase 2, item 2C).
    $hist = $conn->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, ?, 'approved', 'admin', ?, 'Approved by admin.')"
    );
    $hist->bind_param('isi', $bookingId, $booking['status'], $adminId);
    $hist->execute();

    // Notification — inside the mysqli transaction.
    notify_user(
        $conn,
        (int) $booking['user_id'],
        'reservation_approved',
        'Reservation approved: ' . $booking['vehicle_title'],
        'Your booking for ' . $booking['vehicle_title'] . ' has been approved. Please pay the rental fee and security deposit within 48 hours to confirm it.',
        'transactions.php',
        $bookingId
    );

    // Insert transaction record if none exists for this booking.
    $tchk = $conn->prepare("SELECT COUNT(*) AS cnt FROM transactions WHERE booking_id = ?");
    $tchk->bind_param('i', $bookingId);
    $tchk->execute();
    $tcres = $tchk->get_result();
    $tc = $tcres->fetch_assoc()['cnt'] ?? 0;
    if (intval($tc) === 0) {
        $txref = 'TX' . time() . rand(1000,9999);
        $tins = $conn->prepare("INSERT INTO transactions (booking_id, transaction_ref, amount) VALUES (?, ?, ?)");
        $tins->bind_param('isd', $bookingId, $txref, $amount);
        @$tins->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
    exit;
}
