<?php
// Public, no auth. revision: this endpoint predates and.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/booking_rules.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$vehicle_id = (int) ($data['vehicle_id'] ?? 0);
$rental_date = $data['rental_date'] ?? '';
$return_date = $data['return_date'] ?? '';
$pickup_time = $data['pickup_time'] ?? '09:00:00';
$dropoff_time = $data['dropoff_time'] ?? '09:00:00';
$voucher_code = trim($data['voucher_code'] ?? '');
$pickup_location_id = (int) ($data['pickup_location_id'] ?? 0);
$dropoff_location_id = (int) ($data['dropoff_location_id'] ?? 0);

if (!$vehicle_id || !$rental_date || !$return_date) {
  api_error('vehicle_id, rental_date, and return_date are required.', [], 422);
}

try {
  $pickup_at = (new DateTime("$rental_date $pickup_time"))->format('Y-m-d H:i:s');
  $dropoff_at = (new DateTime("$return_date $dropoff_time"))->format('Y-m-d H:i:s');
} catch (Exception $e) {
  api_error('Invalid date/time format.', [], 422);
}

$stmt = $pdo->prepare("SELECT price_per_day, security_deposit FROM vehicles WHERE id = ? AND is_active = 1");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch();
if (!$vehicle) {
  api_error('Vehicle not found.', [], 404);
}

$validationError = booking_validate_rental_window($pickup_at, $dropoff_at);
if ($validationError !== null) {
  api_error($validationError, ['pickup_at' => $validationError], 422);
}

$days = booking_billable_days($pickup_at, $dropoff_at);
$durationHours = booking_duration_hours($pickup_at, $dropoff_at);
$durationLabel = booking_format_duration($durationHours);

$rate = (float) $vehicle['price_per_day'];
$subtotal = booking_calculate_rental_fee($rate, $days);
$discount = 0.0;

if ($voucher_code) {
  $v = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND is_active = 1");
  $v->execute([$voucher_code]);
  $voucher = $v->fetch();
  if (!$voucher) {
    api_error('Invalid voucher code.', ['voucher_code' => 'Invalid voucher code.'], 422);
  }
  if ($voucher['discount_pct']) {
    $discount = $subtotal * ($voucher['discount_pct'] / 100);
  } elseif ($voucher['discount_amount']) {
    $discount = (float) $voucher['discount_amount'];
  }
}

// Location fee — only priced once both branches are chosen.
$location_fee = 0.0;
if ($pickup_location_id && $dropoff_location_id) {
  $fee = booking_location_fee($pdo, $pickup_location_id, $dropoff_location_id);
  if ($fee === null) {
    api_error('No location fee is configured for that pickup/drop-off pair.', [], 422);
  }
  $location_fee = $fee;
}

$total = max(0, $subtotal + $location_fee - $discount);
$security_deposit = booking_calculate_security_deposit(
  $subtotal,
  $vehicle['security_deposit'] !== null ? (float) $vehicle['security_deposit'] : null
);

api_success([
  'days' => $days,
  'duration_hours' => round($durationHours, 2),
  'duration_label' => $durationLabel,
  'pickup_at_label' => (new DateTime($pickup_at))->format('M j, Y \a\t g:i A'),
  'dropoff_at_label' => (new DateTime($dropoff_at))->format('M j, Y \a\t g:i A'),
  'rate' => $rate,
  'subtotal' => $subtotal,
  'location_fee' => $location_fee,
  'discount' => $discount,
  'total' => $total,
  'security_deposit' => $security_deposit,
  'amount_due_at_payment' => $total + $security_deposit,
]);
