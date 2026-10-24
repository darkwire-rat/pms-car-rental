<?php
// Booking-centric, same reasoning as index.php.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/payment.php';

$user = api_require_auth($pdo);
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
if (!$bookingId) {
  api_error('booking_id is required.', ['booking_id' => 'booking_id is required.'], 422);
}

$stmt = $pdo->prepare(
  "SELECT b.id AS booking_id, b.booking_ref, b.rental_date, b.return_date,
          b.pickup_time, b.dropoff_time, b.rate, b.discount, b.total_amount, b.paid,
          b.status AS booking_status, b.contact_number, b.age,
          b.payment_status, b.security_deposit, b.deposit_status,
          b.deposit_refund_amount, b.outstanding_balance,
          v.title AS vehicle_title
   FROM bookings b
   LEFT JOIN vehicles v ON v.id = b.vehicle_id
   WHERE b.id = ? AND b.user_id = ?"
);
$stmt->execute([$bookingId, $user['id']]);
$row = $stmt->fetch();

if (!$row) {
  api_error('Booking not found.', [], 404);
}

$row['change'] = round((float) $row['paid'] - (float) $row['total_amount'], 2);
$row['receipt_available'] = in_array($row['booking_status'], ['approved', 'paid', 'active', 'returned', 'completed'], true);
$row['payments'] = payment_list_for_booking($pdo, $bookingId);

api_success(['transaction' => $row]);
