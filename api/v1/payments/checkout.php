<?php
// Bearer-token twin of payment_process.php's PayMongo branch.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/payment.php';

$user = api_require_auth($pdo);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = (int) ($data['booking_id'] ?? 0);
if ($bookingId <= 0) {
  api_error('Booking id is required.', ['booking_id' => 'Booking id is required.'], 422);
}

$pdo->beginTransaction();
try {
  $stmt = $pdo->prepare(
    "SELECT b.*, v.title AS vehicle_title
     FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
     WHERE b.id = ? FOR UPDATE"
  );
  $stmt->execute([$bookingId]);
  $booking = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$booking) {
    $pdo->rollBack();
    api_error('Booking not found.', [], 404);
  }
  if ((int) $booking['user_id'] !== (int) $user['id']) {
    $pdo->rollBack();
    api_error('This is not your booking.', [], 403);
  }
  if ($booking['status'] !== 'approved') {
    $pdo->rollBack();
    api_error('This booking is not awaiting payment (status: ' . $booking['status'] . ').', [], 409);
  }
  if ($booking['payment_status'] === 'paid') {
    $pdo->rollBack();
    api_error('This booking is already paid.', [], 409);
  }

  $checkout = payment_paymongo_create_checkout(
    $pdo,
    $bookingId,
    (string) $booking['vehicle_title'],
    (float) $booking['total_amount'],
    (float) $booking['security_deposit'],
    $user['email'] ?? null,
    'app'
  );
  $pdo->commit();
  api_success(['checkout_url' => $checkout['checkout_url']]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  throw $e; // bootstrap.php's generic exception handler
}
