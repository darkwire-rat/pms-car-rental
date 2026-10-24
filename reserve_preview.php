<?php
// Revision: uses lib/booking_rules.php for billable-day calculation and duration validation instead of its own date-diff copy — 4.
require 'db.php';
require_once __DIR__ . '/lib/booking_rules.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$vehicle_id   = (int)($data['vehicle_id'] ?? 0);
$rental_date  = $data['rental_date'] ?? '';
$return_date  = $data['return_date'] ?? '';
$pickup_time  = $data['pickup_time'] ?? '09:00:00';
$dropoff_time = $data['dropoff_time'] ?? '09:00:00';
$voucher_code = trim($data['voucher_code'] ?? '');
$pickup_location_id  = (int)($data['pickup_location_id'] ?? 0);
$dropoff_location_id = (int)($data['dropoff_location_id'] ?? 0);

if (!$vehicle_id || !$rental_date || !$return_date) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields']);
  exit;
}

try {
  $pickup_at  = (new DateTime("$rental_date $pickup_time"))->format('Y-m-d H:i:s');
  $dropoff_at = (new DateTime("$return_date $dropoff_time"))->format('Y-m-d H:i:s');
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid date/time format']);
  exit;
}

// 1. Fetch vehicle info
$stmt = $pdo->prepare('SELECT price_per_day, security_deposit FROM vehicles WHERE id = ? AND is_active = 1');
$stmt->execute([$vehicle_id]);
$veh = $stmt->fetch();
if (!$veh) {
  http_response_code(400);
  echo json_encode(['error' => 'Vehicle not found']);
  exit;
}

// Duration validation — a preview of an invalid window should say so rather than silently pricing it as 1 day.
$validationError = booking_validate_rental_window($pickup_at, $dropoff_at);
if ($validationError !== null) {
  http_response_code(400);
  echo json_encode(['error' => $validationError]);
  exit;
}

// Billable days + duration readout. duration_hours/duration_label feed the booking form's live "Duration.
$days           = booking_billable_days($pickup_at, $dropoff_at);
$durationHours  = booking_duration_hours($pickup_at, $dropoff_at);
$durationLabel  = booking_format_duration($durationHours);

// 4. Compute subtotal
$rate     = $veh['price_per_day'];
$subtotal = booking_calculate_rental_fee($rate, $days);

// 5. Optional voucher
$discount = 0.0;
if ($voucher_code) {
  $v = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? AND is_active = 1');
  $v->execute([$voucher_code]);
  $voucher = $v->fetch();
  if ($voucher) {
    if ($voucher['discount_pct']) {
      $discount = $subtotal * ($voucher['discount_pct'] / 100);
    } elseif ($voucher['discount_amount']) {
      $discount = (float)$voucher['discount_amount'];
    }
  } else {
    echo json_encode(['error' => 'Invalid voucher']);
    exit;
  }
}

// Only priced once both branches are chosen; a missing matrix row is a hard error, never ₱0.
$location_fee = 0.0;
$location_error = null;
if ($pickup_location_id && $dropoff_location_id) {
  $fee = booking_location_fee($pdo, $pickup_location_id, $dropoff_location_id);
  if ($fee === null) {
    $location_error = 'No location fee is configured for that pickup/drop-off pair.';
  } else {
    $location_fee = $fee;
  }
}
if ($location_error !== null) {
  http_response_code(400);
  echo json_encode(['error' => $location_error]);
  exit;
}

// 7. Total (rental only) + security deposit (P5), shown separately.
$total = max(0, $subtotal + $location_fee - $discount);
$security_deposit = booking_calculate_security_deposit(
  (float) $subtotal,
  $veh['security_deposit'] !== null ? (float) $veh['security_deposit'] : null
);

// Send response.: the booking-form breakdown must show the actual pickup/return datetimes the customer chose.
echo json_encode([
  'days'                  => $days,
  'duration_hours'        => round($durationHours, 2),
  'duration_label'        => $durationLabel,
  'pickup_at_label'       => (new DateTime($pickup_at))->format('M j, Y \a\t g:i A'),
  'dropoff_at_label'      => (new DateTime($dropoff_at))->format('M j, Y \a\t g:i A'),
  'rate'                  => $rate,
  'subtotal'              => $subtotal,
  'location_fee'          => $location_fee,
  'discount'              => $discount,
  'total'                 => $total,
  'security_deposit'      => $security_deposit,
  'amount_due_at_payment' => $total + $security_deposit
]);
