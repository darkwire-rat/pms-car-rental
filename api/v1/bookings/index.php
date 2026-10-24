<?php
// Full booking history for the authenticated user. revision.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$stmt = $pdo->prepare(
  "SELECT b.id, b.booking_ref, b.vehicle_id, v.title AS vehicle_title,
          b.status, b.rental_date, b.return_date, b.pickup_time, b.dropoff_time,
          b.days, b.rate, b.discount, b.location_fee, b.total_amount, b.paid,
          b.payment_status, b.security_deposit, b.deposit_status,
          b.deposit_refund_amount, b.outstanding_balance,
          pl.name AS pickup_location_name, dl.name AS dropoff_location_name
   FROM bookings b
   LEFT JOIN vehicles v ON b.vehicle_id = v.id
   LEFT JOIN locations pl ON pl.id = b.pickup_location_id
   LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
   WHERE b.user_id = ?
   ORDER BY b.id DESC"
);
$stmt->execute([$user['id']]);
api_success(['bookings' => $stmt->fetchAll()]);
