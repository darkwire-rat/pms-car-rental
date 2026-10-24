<?php
// Revision: status strings updated to the new enum.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/booking_rules.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first']);
    exit;
}

csrf_require();

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'] ?? null;

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Booking ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Details — cancellable states are anything before the rental is actually picked up.
    $stmt = $pdo->prepare(
        "SELECT b.*, v.id as vehicle_id, v.title as vehicle_title
         FROM bookings b
         JOIN vehicles v ON b.vehicle_id = v.id
         WHERE b.id = ? AND b.user_id = ?
           AND b.status IN ('pending_review','info_requested','approved','paid')
         FOR UPDATE"
    );
    $stmt->execute([$booking_id, $_SESSION['user']['id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        throw new Exception('Booking not found or already cancelled');
    }

    // Check if pickup is in the future
    $pickup_at = new DateTime($booking['pickup_at']);
    $now = new DateTime();

    if ($pickup_at <= $now) {
        http_response_code(409);
        throw new Exception('Cannot cancel bookings on or after the rental date');
    }

    // Refund percentage: 100% at >=24h before pickup, 50% inside 24h, 0% once started.
    $refund_percentage = booking_calculate_refund_percentage($booking['pickup_at']);
    $wasPaid = $booking['payment_status'] === 'paid';
    $rentalRefund  = round(((float) $booking['total_amount'] * $refund_percentage) / 100, 2);
    $depositRefund = ($wasPaid && (float) $booking['security_deposit'] > 0) ? (float) $booking['security_deposit'] : 0.0;
    $refund_amount = $rentalRefund + $depositRefund;

    $previous_status = $booking['status'];
    $reason = sprintf('Cancelled by customer. Refund: %d%% of the rental (₱%s)%s.',
        $refund_percentage, number_format($rentalRefund, 2),
        $depositRefund > 0 ? ' plus the ₱' . number_format($depositRefund, 2) . ' deposit' : '');

    // Persist the refund on the booking.
    $newPaymentStatus = $wasPaid ? 'refunded' : $booking['payment_status'];
    $newDepositStatus = ($wasPaid && (float) $booking['security_deposit'] > 0) ? 'refunded' : $booking['deposit_status'];
    $updateBooking = $pdo->prepare(
        "UPDATE bookings
         SET status = 'cancelled', payment_status = ?, deposit_status = ?,
             refund_amount = ?, refund_percentage = ?, cancelled_at = NOW(),
             cancel_reason = ?,
             deposit_refund_amount = CASE WHEN ? > 0 THEN ? ELSE deposit_refund_amount END,
             deposit_settled_at = CASE WHEN ? > 0 THEN NOW() ELSE deposit_settled_at END
         WHERE id = ?"
    );
    $updateBooking->execute([
        $newPaymentStatus, $newDepositStatus,
        $refund_amount, $refund_percentage, mb_substr($reason, 0, 255),
        $depositRefund, $depositRefund,
        $depositRefund,
        $booking_id,
    ]);

    // If money was actually collected, record the refund as a payments row so the ledger balances.
    if ($wasPaid && $refund_amount > 0) {
        // purpose='refund' signals the direction; amount stays positive.
        $refundP = payment_create($pdo, (int) $booking_id, 'refund', $refund_amount, 'manual');
        $ev = payment_manual_build_event($refundP['provider_ref'], 'succeeded');
        payment_apply_event($pdo, json_decode($ev['payload'], true));
    }

    // Status history (Phase 2, item 2C).
    $hist = $pdo->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, ?, 'cancelled', 'user', ?, ?)"
    );
    $hist->execute([
        $booking_id,
        $previous_status,
        $_SESSION['user']['id'],
        mb_substr($reason, 0, 255),
    ]);

    // Notifications, inside the transaction.
    notify_user(
        $pdo,
        (int) $_SESSION['user']['id'],
        'booking_cancelled',
        'Booking cancelled: ' . $booking['vehicle_title'],
        sprintf('Your booking for %s was cancelled. Refund: %d%% of total (₱%s).',
            $booking['vehicle_title'], $refund_percentage, number_format($refund_amount, 2)),
        'transactions.php',
        (int) $booking_id
    );
    notify_admin(
        $pdo,
        'booking_cancelled',
        'Booking cancelled: ' . $booking['vehicle_title'],
        sprintf('%s cancelled booking #%d. Refund owed: %d%% (₱%s).',
            $_SESSION['user']['name'] ?? ('Customer #' . (int) $_SESSION['user']['id']),
            (int) $booking_id, $refund_percentage, number_format($refund_amount, 2)),
        'admin_transaction_detail.php?id=' . (int) $booking_id,
        (int) $booking_id
    );

    // Commit transaction
    $pdo->commit();

    // Success response
    echo json_encode([
        'success' => true,
        'message' => "Booking cancelled successfully. Refund amount: ₱" . number_format($refund_amount, 2) . " ({$refund_percentage}% of total)",
        'refund_amount' => $refund_amount,
        'refund_percentage' => $refund_percentage
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // A specific 404/409 may already have been set above for an expected rejection.
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
