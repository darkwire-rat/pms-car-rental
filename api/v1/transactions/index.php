<?php
// Booking-centric, not transaction- row-centric. revision.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$stmt = $pdo->prepare(
  "SELECT b.id AS booking_id, b.booking_ref, b.rental_date, b.return_date,
          b.pickup_time, b.dropoff_time, b.total_amount, b.paid, b.status AS booking_status,
          b.payment_status, b.security_deposit, b.deposit_status,
          b.deposit_refund_amount, b.outstanding_balance,
          v.title AS vehicle_title
   FROM bookings b
   LEFT JOIN vehicles v ON v.id = b.vehicle_id
   WHERE b.user_id = ?
   ORDER BY b.id DESC"
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

// A receipt is available once the booking has been approved and onward.
$receiptStatuses = ['approved', 'paid', 'active', 'returned', 'completed'];
foreach ($rows as &$row) {
  $row['change'] = round((float) $row['paid'] - (float) $row['total_amount'], 2);
  $row['receipt_available'] = in_array($row['booking_status'], $receiptStatuses, true);
}
unset($row);

api_success(['transactions' => $rows]);
