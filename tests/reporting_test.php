<?php
// CLI test for lib/reporting.php —.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/reporting.php';
require_once __DIR__ . '/../lib/payment.php';

$failures = 0; $checks = 0;
function check($label, $actual, $expected) {
    global $failures, $checks; $checks++;
    $ok = $actual === $expected;
    if (!$ok) { $failures++; }
    printf("[%s] %s\n    actual: %s  expected: %s\n", $ok ? 'PASS' : 'FAIL', $label, var_export($actual, true), var_export($expected, true));
}

$pdo->beginTransaction();

function makeBooking(PDO $pdo, array $overrides = []): int
{
    static $n = 0;
    $n++;
    $defaults = [
        'booking_ref' => 'RPTTEST-' . $n,
        'vehicle_id' => 19,
        'pickup_at' => '2026-03-01 10:00:00',
        'dropoff_at' => '2026-03-03 10:00:00',
        'status' => 'approved',
        'created_at' => '2026-03-15 12:00:00',
    ];
    $b = array_merge($defaults, $overrides);
    $stmt = $pdo->prepare(
        "INSERT INTO bookings (booking_ref, user_id, vehicle_id, pickup_at, dropoff_at, days, rate,
                                total_amount, rental_fee, security_deposit, contact_number, age, paid,
                                status, payment_status, created_at)
         VALUES (?, 3, ?, ?, ?, 2, 5500, 11000, 11000, 3500, '0999', 30, 0, ?, 'unpaid', ?)"
    );
    $stmt->execute([$b['booking_ref'], $b['vehicle_id'], $b['pickup_at'], $b['dropoff_at'], $b['status'], $b['created_at']]);
    return (int) $pdo->lastInsertId();
}

echo "=== reporting_bookings_by_status ===\n";
makeBooking($pdo, ['status' => 'pending_review', 'created_at' => '2026-03-05 09:00:00']);
makeBooking($pdo, ['status' => 'pending_review', 'created_at' => '2026-03-10 09:00:00']);
makeBooking($pdo, ['status' => 'approved', 'created_at' => '2026-03-20 09:00:00']);
makeBooking($pdo, ['status' => 'cancelled', 'created_at' => '2026-02-28 09:00:00']); // just outside the month
makeBooking($pdo, ['status' => 'cancelled', 'created_at' => '2026-04-01 09:00:00']); // just outside the month

$byStatus = reporting_bookings_by_status($pdo, '2026-03');
check('pending_review count for the month', $byStatus['pending_review'], 2);
check('approved count for the month', $byStatus['approved'], 1);
check('cancelled excluded (created outside the month)', $byStatus['cancelled'], 0);
check('every status key present', array_key_exists('completed', $byStatus), true);

echo "\n=== reporting_top_vehicles ===\n";
makeBooking($pdo, ['vehicle_id' => 19, 'created_at' => '2026-03-06 09:00:00']);
makeBooking($pdo, ['vehicle_id' => 19, 'created_at' => '2026-03-07 09:00:00']);
makeBooking($pdo, ['vehicle_id' => 20, 'created_at' => '2026-03-08 09:00:00']);

$top = reporting_top_vehicles($pdo, '2026-03', null, 10);
$topIds = array_column($top, 'id');
$v19Index = array_search(19, $topIds, true);
$v20Index = array_search(20, $topIds, true);
check('vehicle 19 outranks vehicle 20 (more bookings this month)', $v19Index !== false && $v20Index !== false && $v19Index < $v20Index, true);

$topSedanOnly = reporting_top_vehicles($pdo, '2026-03', 'Sedan', 10);
$allSedan = true;
foreach ($topSedanOnly as $row) {
    if ($row['category'] !== 'Sedan') { $allSedan = false; }
}
check('category filter restricts to Sedan only', $allSedan, true);

echo "\n=== reporting_revenue_by_month ===\n";
// Booking A: rental payment succeeded in March 2026.
$bidA = makeBooking($pdo, ['created_at' => '2026-03-01 08:00:00']);
$refA = payment_create($pdo, $bidA, 'rental', 11000.0);
$evA = payment_manual_build_event($refA['provider_ref'], 'succeeded');
payment_apply_event($pdo, json_decode($evA['payload'], true));
// Backdate paid_at into March explicitly -- payment_apply_event() stamps "now".
$pdo->prepare("UPDATE payments SET paid_at = '2026-03-10 10:00:00' WHERE id = ?")->execute([$refA['id']]);

// Booking B: settled (overtime + fuel + confirmed damage) in April 2026.
$bidB = makeBooking($pdo, ['created_at' => '2026-01-01 08:00:00']);
$pdo->prepare("UPDATE bookings SET overtime_amount = 500, fuel_charge_amount = 300, deposit_settled_at = '2026-04-05 10:00:00' WHERE id = ?")->execute([$bidB]);
$pdo->prepare("INSERT INTO booking_damages (booking_id, description, estimated_amount, adjusted_amount, status) VALUES (?, 'Scuff', 200, 200, 'confirmed')")->execute([$bidB]);
$pdo->prepare("INSERT INTO booking_damages (booking_id, description, estimated_amount, adjusted_amount, status) VALUES (?, 'Waived one', 999, NULL, 'waived')")->execute([$bidB]);

$revenue = reporting_revenue_by_month($pdo, '2026-04', 12);
check('every month in the trailing-12 window is present', count($revenue), 12);
check('March 2026 revenue = the rental payment', $revenue['2026-03'], 11000.0);
check('April 2026 revenue = overtime + fuel + confirmed damage (waived excluded)', $revenue['2026-04'], 1000.0);
check('a month with no activity is 0.0, not missing', $revenue['2026-02'], 0.0);
check('security deposit itself never counted (booking A total_amount excludes it already, per Phase 9)', $revenue['2026-03'], 11000.0);

// Status-inclusion filter: restrict to a status neither A nor B is currently in.
$revenueFiltered = reporting_revenue_by_month($pdo, '2026-04', 12, ['completed']);
check('status filter excludes bookings not in that status', $revenueFiltered['2026-03'], 0.0);

echo "\n=== reporting_point_in_time_cards ===\n";
$before = reporting_point_in_time_cards($pdo);
check('as_of is a parseable datetime', strtotime($before['as_of']) !== false, true);
check('total_units >= total_models (every model has >= 1 unit)', $before['total_units'] >= $before['total_models'], true);

// Delta test: an 'approved' booking on vehicle 19 spanning right now must reduce available_units_now by exactly 1.
$now = new DateTime();
$spanStart = (clone $now)->modify('-1 hour')->format('Y-m-d H:i:s');
$spanEnd = (clone $now)->modify('+25 hours')->format('Y-m-d H:i:s'); // >= 24h minimum rental (P1 / chk_min_duration)
makeBooking($pdo, ['vehicle_id' => 19, 'pickup_at' => $spanStart, 'dropoff_at' => $spanEnd, 'status' => 'approved']);
$after = reporting_point_in_time_cards($pdo);
check('a held booking spanning now reduces available_units by exactly 1', $before['available_units'] - $after['available_units'], 1);
check('active_rentals unaffected by an approved (not active) booking', $after['active_rentals'], $before['active_rentals']);

// Delta test: an 'active' booking increases active_rentals by exactly 1.
makeBooking($pdo, ['status' => 'active']);
$afterActive = reporting_point_in_time_cards($pdo);
check('an active booking increases active_rentals by exactly 1', $afterActive['active_rentals'] - $after['active_rentals'], 1);

$pdo->rollBack(); // discard every fixture

echo "\n============================================\n";
printf("%d checks, %d failed\n", $checks, $failures);
exit($failures > 0 ? 1 : 0);
