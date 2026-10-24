<?php
// Auth, ownership-checked.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/return_flow.php';

$user = api_require_auth($pdo);
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
if (!$bookingId) {
  api_error('booking_id is required.', ['booking_id' => 'booking_id is required.'], 422);
}

$stmt = $pdo->prepare('SELECT id FROM bookings WHERE id = ? AND user_id = ?');
$stmt->execute([$bookingId, $user['id']]);
if (!$stmt->fetch()) {
  api_error('Booking not found.', [], 404);
}

$damages = array_map(function (array $d) {
  return [
    'id' => (int) $d['id'],
    'description' => $d['description'],
    'estimated_amount' => $d['estimated_amount'],
    'adjusted_amount' => $d['adjusted_amount'],
    'adjustment_reason' => $d['adjustment_reason'],
    'status' => $d['status'],
    'created_at' => $d['created_at'],
  ];
}, return_flow_list_damages($pdo, $bookingId));

api_success(['damages' => $damages]);
