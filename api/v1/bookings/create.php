<?php
// Mirrors reserve.php's business logic minus the optional base64 license upload.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/booking_rules.php';
require_once __DIR__ . '/../../../lib/requirements.php';
require_once __DIR__ . '/../../../lib/notify.php';

$user = api_require_auth($pdo);
$user_id = (int) $user['id'];

$data = json_decode(file_get_contents('php://input'), true);
if ($data === null) {
  api_error('Invalid JSON body.', [], 400);
}

$vehicle_id = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
$rental_date = isset($data['rental_date']) ? trim($data['rental_date']) : null;
$return_date = isset($data['return_date']) ? trim($data['return_date']) : null;
$pickup_time = isset($data['pickup_time']) ? trim($data['pickup_time']) : null;
$dropoff_time = isset($data['dropoff_time']) ? trim($data['dropoff_time']) : null;
$contact_number = isset($data['contact_number']) ? trim($data['contact_number']) : null;
$age = isset($data['age']) ? (int) $data['age'] : null;
$voucher_code = isset($data['voucher_code']) ? trim($data['voucher_code']) : null;
$pickup_location_id = isset($data['pickup_location_id']) ? (int) $data['pickup_location_id'] : 0;
$dropoff_location_id = isset($data['dropoff_location_id']) ? (int) $data['dropoff_location_id'] : 0;
$policy_accepted = !empty($data['policy_accepted']);
$policy_version_input = trim((string) ($data['policy_version'] ?? ''));

$errors = [];
if (!$vehicle_id) $errors['vehicle_id'] = 'Vehicle is required.';
if (!$rental_date) $errors['rental_date'] = 'Rental date is required.';
if (!$return_date) $errors['return_date'] = 'Return date is required.';
if (!$pickup_time) $errors['pickup_time'] = 'Pickup time is required.';
if (!$dropoff_time) $errors['dropoff_time'] = 'Dropoff time is required.';
if (!$contact_number) $errors['contact_number'] = 'Contact number is required.';
if ($age === null || $age < 18) $errors['age'] = 'Driver must be at least 18 years old.';
if (!$pickup_location_id) $errors['pickup_location_id'] = 'Pickup location is required.';
if (!$dropoff_location_id) $errors['dropoff_location_id'] = 'Drop-off location is required.';
if ($errors) {
  api_error('Validation failed.', $errors, 422);
}

try {
  $pickup_at = (new DateTime("$rental_date $pickup_time"))->format('Y-m-d H:i:s');
  $dropoff_at = (new DateTime("$return_date $dropoff_time"))->format('Y-m-d H:i:s');
} catch (Exception $e) {
  api_error('Invalid date/time format.', [], 422);
}

// Minimum-24-hour-rental validation — same function and same error message the web endpoint uses.
$validationError = booking_validate_rental_window($pickup_at, $dropoff_at);
if ($validationError !== null) {
  api_error($validationError, ['pickup_at' => $validationError], 422);
}

$sth = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND is_active = 1");
$sth->execute([$vehicle_id]);
$vehicle = $sth->fetch();
if (!$vehicle) {
  api_error('Vehicle not found or not active.', [], 404);
}

$days = booking_billable_days($pickup_at, $dropoff_at);

$rate = (float) $vehicle['price_per_day'];
$subtotal = booking_calculate_rental_fee($rate, $days);
$discount = 0.0;
$voucher_id = null;
$voucher = null;

if ($voucher_code) {
  $v = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND is_active = 1");
  $v->execute([$voucher_code]);
  $voucher = $v->fetch();
  if (!$voucher) {
    api_error('Invalid voucher code.', ['voucher_code' => 'Invalid voucher code.'], 422);
  }
  $voucher_id = $voucher['id'];
  if (!empty($voucher['discount_pct'])) {
    $discount = $subtotal * ($voucher['discount_pct'] / 100);
  } elseif (!empty($voucher['discount_amount'])) {
    $discount = (float) $voucher['discount_amount'];
  }
}

// A pair with no matrix row is a hard error, never a silent ₱0 — same as reserve.php.
$location_fee = booking_location_fee($pdo, $pickup_location_id, $dropoff_location_id);
if ($location_fee === null) {
  api_error('No location fee is configured for that pickup/drop-off pair.', [], 422);
}

// Rental total = the revenue figure.
$total = max(0, $subtotal + $location_fee - $discount);
$security_deposit = booking_calculate_security_deposit(
  $subtotal,
  $vehicle['security_deposit'] !== null ? (float) $vehicle['security_deposit'] : null
);

// Verification requirements + rental-policy acceptance, both enforced server-side.
$reqStatus = requirements_user_status($pdo, $user_id);
if (!$reqStatus['complete']) {
  $need = [];
  if (!$reqStatus['license']) $need[] = "a driver's licence";
  if (!$reqStatus['photo']) $need[] = 'a verification photo';
  if (!$reqStatus['address']) $need[] = 'your home address';
  api_error('Please add ' . implode(', ', $need) . ' in your profile before booking.', [], 422);
}

$policy = requirements_current_policy($pdo);
if (!$policy || !$policy_accepted || $policy_version_input !== $policy['version']) {
  api_error('You must accept the current rental policy to book.', [], 422);
}

$booking_ref = 'B' . time() . rand(1000, 9999);

$pdo->beginTransaction();
try {
  // Lock the vehicle row for the duration of the availability check + insert.
  $lock = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? FOR UPDATE");
  $lock->execute([$vehicle_id]);
  if (!$lock->fetch()) {
    $pdo->rollBack();
    api_error('Vehicle not found.', [], 404);
  }

  // Datetime-aware, overlap-correct availability check (Finding A fix).
  if (!booking_is_vehicle_available($pdo, $vehicle_id, $pickup_at, $dropoff_at)) {
    $pdo->rollBack();
    api_error('Vehicle unavailable on selected dates.', [], 409);
  }

  $license_file = $reqStatus['user']['license_path'];
  $verification_photo_path = $reqStatus['user']['verification_photo_path'];
  $customer_address_snapshot = requirements_format_address($reqStatus['user']);
  $policy_version_accepted = $policy['version'];

  // No payment is collected at booking time — `paid` starts at 0.
  $ins = $pdo->prepare(
    "INSERT INTO bookings
       (booking_ref, user_id, vehicle_id, pickup_at, dropoff_at, days, rate,
        voucher_id, discount, total_amount, rental_fee, security_deposit, paid,
        contact_number, age,
        pickup_location_id, dropoff_location_id, location_fee,
        license_file, verification_photo_path, customer_address_snapshot,
        policy_version_accepted, policy_accepted_at, status, created_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?, NOW(), 'pending_review', NOW())"
  );
  $ins->execute([
    $booking_ref, $user_id, $vehicle_id, $pickup_at, $dropoff_at,
    $days, $rate, $voucher_id, $discount, $total, $total, $security_deposit,
    $contact_number, $age,
    $pickup_location_id, $dropoff_location_id, $location_fee,
    $license_file, $verification_photo_path, $customer_address_snapshot,
    $policy_version_accepted,
  ]);
  $booking_id = $pdo->lastInsertId();

  $hist = $pdo->prepare(
    "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
     VALUES (?, NULL, 'pending_review', 'user', ?, 'Booking submitted via API.')"
  );
  $hist->execute([$booking_id, $user_id]);

  // Notifications — written inside the same transaction as the booking + history rows.
  notify_admin(
    $pdo,
    'reservation_submitted',
    'New reservation: ' . $vehicle['title'],
    $user['name'] . ' requested ' . $vehicle['title'] . ' for ' . $rental_date . ' -> ' . $return_date . '.',
    'admin_transaction_detail.php?id=' . $booking_id,
    (int) $booking_id
  );
  notify_user(
    $pdo,
    $user_id,
    'reservation_received',
    'Reservation request received',
    'We received your request for ' . $vehicle['title'] . '. You\'ll be notified once it is reviewed.',
    'transactions.php',
    (int) $booking_id
  );

  $pdo->commit();
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  throw $e; // caught by bootstrap.php's generic exception handler
}

// Voucher usage bookkeeping — same as reserve.php.
if ($voucher) {
  $new_usage_count = (int) ($voucher['usage_count'] ?? 0) + 1;
  $updateVoucher = $pdo->prepare("UPDATE vouchers SET used_by = ?, used_at = NOW(), usage_count = ? WHERE id = ?");
  $updateVoucher->execute([$user_id, $new_usage_count, $voucher_id]);
  if ($new_usage_count >= (int) $voucher['usage_limit']) {
    $pdo->prepare("UPDATE vouchers SET is_active = 0 WHERE id = ?")->execute([$voucher_id]);
  }
}

api_success([
  'booking_id' => $booking_id,
  'booking_ref' => $booking_ref,
  'total' => $total,
  'location_fee' => $location_fee,
  'discount' => $discount,
  'security_deposit' => $security_deposit,
  'amount_due_at_payment' => $total + $security_deposit,
  'days' => $days,
  'rate' => $rate,
  'subtotal' => $subtotal,
  'vehicle_name' => $vehicle['title'],
], 'Booking submitted for review.', 201);
