<?php
// Requires auth; ownership resolved from the token's user, never from a client-supplied user_id. revision.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/payment.php';

$user = api_require_auth($pdo);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  api_error('Booking id is required.', ['id' => 'Booking id is required.'], 422);
}

$stmt = $pdo->prepare(
  "SELECT b.*, v.title AS vehicle_title,
          pl.name AS pickup_location_name, dl.name AS dropoff_location_name
   FROM bookings b
   LEFT JOIN vehicles v ON b.vehicle_id = v.id
   LEFT JOIN locations pl ON pl.id = b.pickup_location_id
   LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
   WHERE b.id = ? AND b.user_id = ?"
);
$stmt->execute([$id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
  api_error('Booking not found.', [], 404);
}

$booking['payments'] = payment_list_for_booking($pdo, $id);

api_success(['booking' => $booking]);
