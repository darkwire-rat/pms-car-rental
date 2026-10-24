<?php
// Revision: duration validation, billable-day calculation, and availability now go through lib/booking_rules.php instead of this.
error_reporting(E_ALL);

session_start();
require 'db.php';
require_once __DIR__ . '/lib/booking_rules.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/requirements.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

// require login
if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Please log in first']);
  exit;
}
$user_id = (int)$_SESSION['user']['id'];

csrf_require();

// read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// if JSON parse error, return raw body for debugging
if ($data === null) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid JSON', 'raw' => $raw]);
  exit;
}

// extract fields
$vehicle_id    = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : null;
$rental_date   = isset($data['rental_date']) ? trim($data['rental_date']) : null;
$return_date   = isset($data['return_date']) ? trim($data['return_date']) : null;
$pickup_time   = isset($data['pickup_time']) ? trim($data['pickup_time']) : null;
$dropoff_time  = isset($data['dropoff_time']) ? trim($data['dropoff_time']) : null;
$contact_number= isset($data['contact_number']) ? trim($data['contact_number']) : null;
$age           = isset($data['age']) ? (int)$data['age'] : null;
$voucher_code  = isset($data['voucher_code']) ? trim($data['voucher_code']) : null;
$amount_paid   = isset($data['amount_paid']) ? (float)$data['amount_paid'] : null;
$pickup_location_id  = isset($data['pickup_location_id']) ? (int)$data['pickup_location_id'] : 0;
$dropoff_location_id = isset($data['dropoff_location_id']) ? (int)$data['dropoff_location_id'] : 0;

$is_preview = isset($data['is_preview']) && $data['is_preview'];

// Missing-field check happens before date parsing.
$missing = [];
if (!$vehicle_id)     $missing[] = 'vehicle_id';
if (!$rental_date)    $missing[] = 'rental_date';
if (!$return_date)    $missing[] = 'return_date';
if (!$pickup_time)    $missing[] = 'pickup_time';
if (!$dropoff_time)   $missing[] = 'dropoff_time';
if (!$contact_number) $missing[] = 'contact_number';
if (!$age && $age !== 0) $missing[] = 'age';
// No amount_paid at booking time.

if (!empty($missing)) {
  http_response_code(400);
  echo json_encode([
    'error' => 'Missing fields',
    'missing' => $missing,
    'received' => $data
  ]);
  exit;
}

// Basic age validation.
if ($age < 18) {
  http_response_code(400);
  echo json_encode(['error' => 'Driver must be at least 18 years old']);
  exit;
}

// Combine the client's separate date+time fields into single datetimes.
try {
  $pickup_at  = (new DateTime("$rental_date $pickup_time"))->format('Y-m-d H:i:s');
  $dropoff_at = (new DateTime("$return_date $dropoff_time"))->format('Y-m-d H:i:s');
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid date/time format', 'details' => $e->getMessage()]);
  exit;
}

// Minimum-24-hour-rental validation — replaces the old date-only "same day allowed" check.
$validationError = booking_validate_rental_window($pickup_at, $dropoff_at);
if ($validationError !== null) {
  http_response_code(400);
  echo json_encode(['error' => $validationError]);
  exit;
}

// fetch vehicle and price
$sth = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND is_active = 1");
$sth->execute([$vehicle_id]);
$vehicle = $sth->fetch(PDO::FETCH_ASSOC);
if (!$vehicle) {
  http_response_code(404);
  echo json_encode(['error' => 'Vehicle not found or not active']);
  exit;
}

// billable days (approved rule P1: CEIL(hours/24), not calendar-date diff)
$days = booking_billable_days($pickup_at, $dropoff_at);

// compute totals (re-using preview logic)
$rate = (float)$vehicle['price_per_day'];
$subtotal = booking_calculate_rental_fee($rate, $days);
$discount = 0.0;
$voucher_id = null;

if ($voucher_code) {
  $v = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? AND is_active = 1');
  $v->execute([$voucher_code]);
  $voucher = $v->fetch(PDO::FETCH_ASSOC);
  if ($voucher) {
    $voucher_id = $voucher['id'];
    if (!empty($voucher['discount_pct'])) {
      $discount = $subtotal * ($voucher['discount_pct'] / 100);
    } elseif (!empty($voucher['discount_amount'])) {
      $discount = (float)$voucher['discount_amount'];
    }
  } else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid voucher code']);
    exit;
  }
}

// Both branches are required for a real booking; a pair with no matrix row is a hard error, never a silent ₱0.
$location_fee = 0.0;
if (!$is_preview || ($pickup_location_id && $dropoff_location_id)) {
  if (!$pickup_location_id || !$dropoff_location_id) {
    if (!$is_preview) {
      http_response_code(400);
      echo json_encode(['error' => 'Please choose a pickup and drop-off branch.']);
      exit;
    }
  } else {
    $fee = booking_location_fee($pdo, $pickup_location_id, $dropoff_location_id);
    if ($fee === null) {
      http_response_code(400);
      echo json_encode(['error' => 'No location fee is configured for that pickup/drop-off pair.']);
      exit;
    }
    $location_fee = $fee;
  }
}

// Rental total = the revenue figure.
$total = max(0, $subtotal + $location_fee - $discount);
$security_deposit = booking_calculate_security_deposit(
  $subtotal,
  $vehicle['security_deposit'] !== null ? (float) $vehicle['security_deposit'] : null
);

if ($is_preview) {
  echo json_encode([
    'success' => true,
    'subtotal' => $subtotal,
    'location_fee' => $location_fee,
    'discount' => $discount,
    'total' => $total,
    'security_deposit' => $security_deposit,
    'amount_due_at_payment' => $total + $security_deposit,
    'days' => $days,
    'rate' => $rate,
    'vehicle_name' => $vehicle['title'],
    'rental_date' => $rental_date,
    'return_date' => $return_date,
    'pickup_time' => $pickup_time,
    'dropoff_time' => $dropoff_time,
    'voucher_code' => $voucher_code
  ]);
  exit;
}

// Verification requirements + rental-policy acceptance, both enforced server-side.
$reqStatus = requirements_user_status($pdo, $user_id);
if (!$reqStatus['complete']) {
  $need = [];
  if (!$reqStatus['license']) $need[] = "a driver's licence";
  if (!$reqStatus['photo'])   $need[] = 'a verification photo';
  if (!$reqStatus['address']) $need[] = 'your home address';
  http_response_code(400);
  echo json_encode(['error' => 'Please add ' . implode(', ', $need) . ' in your profile before booking.']);
  exit;
}

$policy = requirements_current_policy($pdo);
$policyAcceptedInput = $data['policy_accepted'] ?? false;
$policyVersionInput = trim((string) ($data['policy_version'] ?? ''));
if (!$policy || empty($policyAcceptedInput) || $policyVersionInput !== $policy['version']) {
  http_response_code(400);
  echo json_encode(['error' => 'You must accept the current rental policy to book.']);
  exit;
}

// No payment is collected at booking time.

// everything OK -> insert booking
try {
  $booking_ref = 'B' . time() . rand(1000,9999);

  // Start transaction
  $pdo->beginTransaction();

  try {
    // Lock the vehicle row for the duration of the availability check + insert.
    $lock = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? FOR UPDATE");
    $lock->execute([$vehicle_id]);
    if (!$lock->fetch()) {
      throw new Exception('Vehicle not found.');
    }

    // Datetime-aware, overlap-correct availability check — replaces the old all-time-counted, units_total-mutating logic.
    if (!booking_is_vehicle_available($pdo, $vehicle_id, $pickup_at, $dropoff_at)) {
      $pdo->rollBack();
      http_response_code(409);
      echo json_encode(['error' => 'Vehicle unavailable on selected dates']);
      exit;
    }

    // New bookings start at 'pending_review' and do not hold inventory until an admin approves them.
    $status = 'pending_review';
    $total_amount = $total;

    // Snapshot the verification requirements onto the booking as they stand right now. reserve.php no longer accepts a base64 licence.
    $license_file             = $reqStatus['user']['license_path'];
    $verification_photo_path  = $reqStatus['user']['verification_photo_path'];
    $customer_address_snapshot = requirements_format_address($reqStatus['user']);
    $policy_version_accepted  = $policy['version'];

    // Rental_date/return_date/pickup_time/dropoff_time are now generated columns derived from pickup_at/dropoff_at and cannot be.
    $ins = $pdo->prepare(
      "INSERT INTO bookings
         (booking_ref, user_id, vehicle_id, pickup_at, dropoff_at, days, rate,
          voucher_id, discount, total_amount, rental_fee, security_deposit, paid,
          contact_number, age,
          pickup_location_id, dropoff_location_id, location_fee,
          license_file, verification_photo_path, customer_address_snapshot,
          policy_version_accepted, policy_accepted_at, status, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?, NOW(), ?, NOW())"
    );
    $ins->execute([
      $booking_ref,
      $user_id,
      $vehicle_id,
      $pickup_at,
      $dropoff_at,
      $days,
      $rate,
      $voucher_id,
      $discount,
      $total_amount,
      $total_amount,       // rental_fee = the rental total (revenue figure)
      $security_deposit,
      $contact_number,
      $age,
      $pickup_location_id,
      $dropoff_location_id,
      $location_fee,
      $license_file,
      $verification_photo_path,
      $customer_address_snapshot,
      $policy_version_accepted,
      $status,
    ]);

    // Get the booking ID
    $booking_id = $pdo->lastInsertId();

    if (!$booking_id) {
      throw new Exception('Failed to create booking');
    }

    // Status history — the audit trail this booking's lifecycle never had before this phase.
    $hist = $pdo->prepare(
      "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
       VALUES (?, NULL, ?, 'user', ?, 'Booking submitted by customer.')"
    );
    $hist->execute([$booking_id, $status, $user_id]);

    // Notifications — written inside the same transaction as the booking + history rows.
    $customerName = $_SESSION['user']['name'] ?? ('Customer #' . $user_id);
    notify_admin(
        $pdo,
        'reservation_submitted',
        'New reservation: ' . $vehicle['title'],
        $customerName . ' requested ' . $vehicle['title'] . ' for ' . $rental_date . ' → ' . $return_date . '.',
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

    // Commit booking immediately so optional steps don't fail the booking
    $pdo->commit();

    // Update voucher usage count if voucher was used
    if (!empty($voucher_code) && isset($voucher['usage_limit'])) {
      $new_usage_count = (int)($voucher['usage_count'] ?? 0) + 1;

      // Mark as used by this user and timestamp
      $updateVoucher = $pdo->prepare("
          UPDATE vouchers
          SET used_by = ?, used_at = NOW(), usage_count = ?
          WHERE id = ?
      ");
      $updateVoucher->execute([$user_id, $new_usage_count, $voucher_id]);

      // If reached usage limit, deactivate it
      if ($new_usage_count >= (int)$voucher['usage_limit']) {
          $deactivate = $pdo->prepare("UPDATE vouchers SET is_active = 0 WHERE id = ?");
          $deactivate->execute([$voucher_id]);
      }
    }

    // NOTE: Do not insert a payment or decrement vehicle units here.

    // Return success response with receipt data
    echo json_encode([
      'success' => true,
      'booking_ref' => $booking_ref,
      'total' => $total,
      'amount_paid' => 0,
      'change' => 0,
      'security_deposit' => $security_deposit,
      'amount_due_at_payment' => $total + $security_deposit,
      'days' => $days,
      'rate' => $rate,
      'subtotal' => $subtotal,
      'discount' => $discount,
      'rental_date' => $rental_date,
      'return_date' => $return_date,
      'pickup_time' => $pickup_time,
      'dropoff_time' => $dropoff_time,
      'vehicle_name' => $vehicle['title'],
      'voucher_code' => $voucher_code,
      'booking_id' => $booking_id
    ]);

  } catch (Exception $e) {
    // Rollback the transaction on error
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
  }

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
  exit;
}
