<?php
// Authoritative receipt data.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/payment.php';
require_once __DIR__ . '/../../../lib/return_flow.php';

$user = api_require_auth($pdo);
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
if (!$bookingId) {
  api_error('booking_id is required.', ['booking_id' => 'booking_id is required.'], 422);
}

$stmt = $pdo->prepare(
  "SELECT b.*, v.title AS vehicle_title,
          pl.name AS pickup_location_name, dl.name AS dropoff_location_name
   FROM bookings b
   LEFT JOIN vehicles v ON v.id = b.vehicle_id
   LEFT JOIN locations pl ON pl.id = b.pickup_location_id
   LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
   WHERE b.id = ? AND b.user_id = ?"
);
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
  api_error('Booking not found.', [], 404);
}

if (!in_array($booking['status'], ['approved', 'paid', 'active', 'returned', 'completed'], true)) {
  api_error('Receipt unavailable for this booking.', [], 409);
}

$booking['change'] = round((float) $booking['paid'] - (float) $booking['total_amount'], 2);
$booking['generated_at'] = date('Y-m-d H:i:s');
$booking['payments'] = payment_list_for_booking($pdo, $bookingId);

// Post-settlement detail — empty/null fields before that point, same as receipt.php.
$booking['is_settled'] = !empty($booking['deposit_settled_at']);
$booking['damages'] = $booking['is_settled'] ? return_flow_list_damages($pdo, $bookingId) : [];

api_success(['receipt' => $booking]);
