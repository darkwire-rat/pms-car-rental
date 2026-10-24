<?php
// Mirrors cancel_booking.php's refund policy, ownership enforced in the query. revision.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/booking_rules.php';
require_once __DIR__ . '/../../../lib/payment.php';
require_once __DIR__ . '/../../../lib/notify.php';

$user = api_require_auth($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$booking_id = $data['booking_id'] ?? null;
if (!$booking_id) {
  api_error('booking_id is required.', ['booking_id' => 'booking_id is required.'], 422);
}

try {
  $pdo->beginTransaction();

  // Cancellable states are anything before the rental is actually picked up.
  $stmt = $pdo->prepare(
    "SELECT b.*, v.id as vehicle_id, v.title as vehicle_title
     FROM bookings b
     JOIN vehicles v ON b.vehicle_id = v.id
     WHERE b.id = ? AND b.user_id = ?
       AND b.status IN ('pending_review','info_requested','approved','paid')
     FOR UPDATE"
  );
  $stmt->execute([$booking_id, $user['id']]);
  $booking = $stmt->fetch();

  if (!$booking) {
    throw new Exception('Booking not found or already cancelled.');
  }

  $pickup_at = new DateTime($booking['pickup_at']);
  $now = new DateTime();
  if ($pickup_at <= $now) {
    throw new Exception('Cannot cancel bookings on or after the rental date.');
  }

  // Refund percentage: 100% at >=24h before pickup, 50% inside 24h.
  $refund_percentage = booking_calculate_refund_percentage($booking['pickup_at']);
  $wasPaid = $booking['payment_status'] === 'paid';
  $rentalRefund = round(((float) $booking['total_amount'] * $refund_percentage) / 100, 2);
  $depositRefund = ($wasPaid && (float) $booking['security_deposit'] > 0) ? (float) $booking['security_deposit'] : 0.0;
  $refund_amount = $rentalRefund + $depositRefund;
  $previous_status = $booking['status'];

  $reason = sprintf('Cancelled by customer via API. Refund: %d%% of the rental (₱%s)%s.',
    $refund_percentage, number_format($rentalRefund, 2),
    $depositRefund > 0 ? ' plus the ₱' . number_format($depositRefund, 2) . ' deposit' : '');

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
    $refundP = payment_create($pdo, (int) $booking_id, 'refund', $refund_amount, 'manual');
    $ev = payment_manual_build_event($refundP['provider_ref'], 'succeeded');
    payment_apply_event($pdo, json_decode($ev['payload'], true));
  }

  $pdo->prepare(
    "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
     VALUES (?, ?, 'cancelled', 'user', ?, ?)"
  )->execute([$booking_id, $previous_status, $user['id'], mb_substr($reason, 0, 255)]);

  notify_user(
    $pdo,
    (int) $user['id'],
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
    sprintf('%s cancelled booking #%d via API. Refund owed: %d%% (₱%s).',
      $user['name'] ?? ('Customer #' . (int) $user['id']),
      (int) $booking_id, $refund_percentage, number_format($refund_amount, 2)),
    'admin_transaction_detail.php?id=' . (int) $booking_id,
    (int) $booking_id
  );

  $pdo->commit();

  api_success([
    'refund_amount' => $refund_amount,
    'refund_percentage' => $refund_percentage,
    'deposit_refund_amount' => $depositRefund,
  ], "Booking cancelled. Refund: {$refund_percentage}% of total.");
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  api_error($e->getMessage(), [], 400);
}
