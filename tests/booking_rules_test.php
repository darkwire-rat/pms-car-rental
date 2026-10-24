<?php
// CLI regression test for lib/booking_rules.php and lib/booking_status.php.

require_once __DIR__ . '/../lib/booking_rules.php';

$failures = 0;
$checks = 0;

function check(string $label, $actual, $expected): void
{
    global $failures, $checks;
    $checks++;
    $ok = $actual === $expected;
    if (!$ok) {
        $failures++;
    }
    printf("[%s] %s\n    actual:   %s\n    expected: %s\n", $ok ? 'PASS' : 'FAIL', $label, var_export($actual, true), var_export($expected, true));
}

echo "=== booking_duration_hours ===\n";
check('exactly 24h', booking_duration_hours('2026-08-20 16:00:00', '2026-08-21 16:00:00'), 24.0);
check('30h', booking_duration_hours('2026-08-20 16:00:00', '2026-08-21 22:00:00'), 30.0);
check('zero (booking 69 pre-remediation shape)', booking_duration_hours('2026-08-21 17:31:00', '2026-08-21 17:31:00'), 0.0);
check('negative (booking 46 pre-remediation shape)', booking_duration_hours('2026-08-14 12:06:00', '2026-08-14 00:06:00'), -12.0);

echo "\n=== booking_billable_days (approved rule P1) ===\n";
check('exactly 24h -> 1 day', booking_billable_days('2026-08-20 16:00:00', '2026-08-21 16:00:00'), 1);
check('24h 1min -> 2 days (brief\'s own example threshold)', booking_billable_days('2026-08-20 16:00:00', '2026-08-21 16:01:00'), 2);
check('30h -> 2 days', booking_billable_days('2026-08-20 16:00:00', '2026-08-21 22:00:00'), 2);
check('47h59m -> 2 days', booking_billable_days('2026-08-20 16:00:00', '2026-08-22 15:59:00'), 2);
check('48h -> 2 days', booking_billable_days('2026-08-20 16:00:00', '2026-08-22 16:00:00'), 2);
check('48h1m -> 3 days', booking_billable_days('2026-08-20 16:00:00', '2026-08-22 16:01:00'), 3);
check('zero/negative -> floors at 1 (validation, not pricing, rejects these)', booking_billable_days('2026-08-21 17:31:00', '2026-08-21 17:31:00'), 1);

echo "\n=== booking_format_duration (Phase 3 duration readout) ===\n";
check('exactly 24h', booking_format_duration(24.0), '1 day');
check('30h -> 1 day 6 hours', booking_format_duration(30.0), '1 day 6 hours');
check('25.5h -> 1 day 1 hour 30 minutes', booking_format_duration(25.5), '1 day 1 hour 30 minutes');
check('48h -> 2 days', booking_format_duration(48.0), '2 days');
check('6h -> 6 hours (no day part)', booking_format_duration(6.0), '6 hours');
check('zero -> 0 hours', booking_format_duration(0.0), '0 hours');
check('negative -> 0 hours', booking_format_duration(-12.0), '0 hours');

echo "\n=== booking_validate_rental_window (brief's own examples, Step 7) ===\n";
$now = '2026-08-01 00:00:00'; // fixed reference so "past" checks are stable regardless of when this runs
check('VALID: Aug 20 4PM -> Aug 21 4PM', booking_validate_rental_window('2026-08-20 16:00:00', '2026-08-21 16:00:00', $now), null);
check('INVALID: Aug 20 4PM -> Aug 20 8PM (same-day, brief\'s own invalid example)', booking_validate_rental_window('2026-08-20 16:00:00', '2026-08-20 20:00:00', $now) !== null, true);
check('INVALID: dropoff before pickup', booking_validate_rental_window('2026-08-20 16:00:00', '2026-08-19 16:00:00', $now) !== null, true);
check('INVALID: pickup in the past', booking_validate_rental_window('2026-07-01 16:00:00', '2026-07-02 16:00:00', $now) !== null, true);
check('VALID: 23h59m is invalid (boundary)', booking_validate_rental_window('2026-08-20 16:00:00', '2026-08-21 15:59:00', $now) !== null, true);
check('VALID: exactly 24h0m is valid (boundary)', booking_validate_rental_window('2026-08-20 16:00:00', '2026-08-21 16:00:00', $now), null);

echo "\n=== booking_calculate_rental_fee ===\n";
check('3400/day x 2 days', booking_calculate_rental_fee(3400, 2), 6800.0);
check('5500/day x 1 day', booking_calculate_rental_fee(5500, 1), 5500.0);

echo "\n=== booking_calculate_security_deposit (approved rule P5) ===\n";
check('₱800/day scooter -> floor ₱1,000', booking_calculate_security_deposit(800.0), 1000.0);
check('₱5,500 x 2 days = ₱11,000 -> 30% = 3300 -> ceil ₱500 = ₱3,500', booking_calculate_security_deposit(11000.0), 3500.0);
check('₱8,990 x 2 = ₱17,980 -> 30% = 5394 -> cap ₱5,000', booking_calculate_security_deposit(17980.0), 5000.0);
check('₱3,400 -> 30% = 1020 -> ceil ₱500 = ₱1,500', booking_calculate_security_deposit(3400.0), 1500.0);
check('exactly on a ₱500 boundary stays put (₱10,000 subtotal -> 3000)', booking_calculate_security_deposit(10000.0), 3000.0);
check('vehicle override wins outright', booking_calculate_security_deposit(20000.0, 2500.0), 2500.0);

echo "\n=== booking_calculate_refund_percentage (approved rule P7) ===\n";
$pickup = '2026-08-20 16:00:00';
check('48h before -> 100%', booking_calculate_refund_percentage($pickup, '2026-08-18 16:00:00'), 100);
check('exactly 24h before -> 100%', booking_calculate_refund_percentage($pickup, '2026-08-19 16:00:00'), 100);
check('23h59m before -> 50%', booking_calculate_refund_percentage($pickup, '2026-08-19 16:01:00'), 50);
check('1h before -> 50%', booking_calculate_refund_percentage($pickup, '2026-08-20 15:00:00'), 50);
check('at pickup time -> 0%', booking_calculate_refund_percentage($pickup, '2026-08-20 16:00:00'), 0);
check('after pickup -> 0%', booking_calculate_refund_percentage($pickup, '2026-08-21 16:00:00'), 0);

echo "\n=== booking_calculate_overtime (Phase 11, approved rules P2/P3/P4) ===\n";
$dueAt = '2026-08-21 16:00:00';
$rate = 3400.0;
check('on time -> 0', booking_calculate_overtime($dueAt, $dueAt, $rate), 0.0);
check('45 min late -> 0 (within 60-min grace, P2)', booking_calculate_overtime($dueAt, '2026-08-21 16:45:00', $rate), 0.0);
check('exactly 60 min late -> 0 (grace boundary, inclusive)', booking_calculate_overtime($dueAt, '2026-08-21 17:00:00', $rate), 0.0);
check('61 min late -> 2 started hours (derived from the 3h case below: grace is a threshold, not a subtracted offset)', booking_calculate_overtime($dueAt, '2026-08-21 17:01:00', $rate), round($rate * 0.10 * 2, 2));
check('3 h late -> 3 x 10% of daily rate (spec table)', booking_calculate_overtime($dueAt, '2026-08-21 19:00:00', $rate), round($rate * 0.10 * 3, 2));
check('exactly 6 h late -> still hourly, 6 x 10%', booking_calculate_overtime($dueAt, '2026-08-21 22:00:00', $rate), round($rate * 0.10 * 6, 2));
check('6 h 1 min late -> switches to 1 extra day, not 6h1m of hourly', booking_calculate_overtime($dueAt, '2026-08-21 22:01:00', $rate), round($rate * 1, 2));
check('8 h late -> 1 extra full day, not 8 hourly units (spec table)', booking_calculate_overtime($dueAt, '2026-08-22 00:00:00', $rate), round($rate * 1, 2));
check('29 h 59 min late -> still only 1 extra day (2nd 24h block not complete)', booking_calculate_overtime($dueAt, '2026-08-22 21:59:00', $rate), round($rate * 1, 2));
check('30 h late -> 2 extra days (spec table)', booking_calculate_overtime($dueAt, '2026-08-22 22:00:00', $rate), round($rate * 2, 2));
check('early return -> 0', booking_calculate_overtime($dueAt, '2026-08-21 15:00:00', $rate), 0.0);

echo "\n=== booking_calculate_fuel_charge (Phase 11, approved rule P6) ===\n";
check('8/8 out, 5/8 in -> 3 x rate (spec table)', booking_calculate_fuel_charge(8, 5, 100.0), 300.0);
check('returned fuller (5 out, 8 in) -> 0, never negative (spec table)', booking_calculate_fuel_charge(5, 8, 100.0), 0.0);
check('same level -> 0', booking_calculate_fuel_charge(6, 6, 100.0), 0.0);
check('full tank both ends -> 0', booking_calculate_fuel_charge(8, 8, 100.0), 0.0);

echo "\n=== booking_status.php ===\n";
check('holds-inventory list', booking_status_holds_inventory(), ['approved', 'paid', 'active', 'returned']);
check('pending_review label', booking_status_label('pending_review'), 'Pending Review');
check('unknown status falls back gracefully', booking_status_label('some_future_status'), 'Some future status');
check('overdue: active + past dropoff', booking_is_overdue(['status' => 'active', 'dropoff_at' => '2026-08-01 00:00:00'], '2026-08-02 00:00:00'), true);
check('overdue: active + future dropoff -> false', booking_is_overdue(['status' => 'active', 'dropoff_at' => '2026-08-03 00:00:00'], '2026-08-02 00:00:00'), false);
check('overdue: not active -> false even if dropoff passed', booking_is_overdue(['status' => 'completed', 'dropoff_at' => '2026-08-01 00:00:00'], '2026-08-02 00:00:00'), false);
check('payment pending: approved + unpaid -> true', booking_is_payment_pending(['status' => 'approved', 'payment_status' => 'unpaid']), true);
check('payment pending: approved + paid -> false', booking_is_payment_pending(['status' => 'approved', 'payment_status' => 'paid']), false);

echo "\n=== booking_status_can_hard_delete (Phase 4, Analysis R15) ===\n";
check('pending_review -> deletable', booking_status_can_hard_delete('pending_review'), true);
check('info_requested -> deletable', booking_status_can_hard_delete('info_requested'), true);
check('cancelled -> deletable', booking_status_can_hard_delete('cancelled'), true);
check('declined -> deletable', booking_status_can_hard_delete('declined'), true);
check('approved -> blocked', booking_status_can_hard_delete('approved'), false);
check('paid -> blocked', booking_status_can_hard_delete('paid'), false);
check('active -> blocked', booking_status_can_hard_delete('active'), false);
check('completed -> blocked', booking_status_can_hard_delete('completed'), false);

echo "\n=== booking_status_available_actions (Phase 4, Analysis sec 5.5) ===\n";
check('pending_review actions', booking_status_available_actions('pending_review'), ['approve', 'decline', 'request_info']);
check('info_requested actions', booking_status_available_actions('info_requested'), ['approve', 'decline', 'request_info']);
check('approved actions', booking_status_available_actions('approved'), ['record_payment', 'cancel']);
check('returned actions (Phase 11: finalize_settlement added)', booking_status_available_actions('returned'), ['record_inspection', 'finalize_settlement']);
check('completed actions (none)', booking_status_available_actions('completed'), []);
check('unknown status -> no actions', booking_status_available_actions('nonsense'), []);

echo "\n=== DB-backed availability (against live pms_connection data) ===\n";
require_once __DIR__ . '/../db.php'; // provides $pdo

// Chevrolet Cruze: units_total should now be 6.
$avail = booking_available_units($pdo, 19, '2027-01-01 10:00:00', '2027-01-03 10:00:00');
check('Chevrolet Cruze, far-future window, no holds -> all 6 units free', $avail, 6);

// The historical bug Finding A exists to fix.
check('Finding A is fixed: past booking does not block future availability', $avail === 6, true);

// Overlap correctness — verified against a disposable fixture inserted and rolled back inside this test.
$pdo->beginTransaction();
$pdo->exec("
    INSERT INTO bookings (booking_ref, user_id, vehicle_id, pickup_at, dropoff_at, days, rate,
                           total_amount, rental_fee, security_deposit, contact_number, age, paid,
                           status, payment_status, created_at)
    VALUES ('FIXTURE-AVAIL-TEST', 3, 19, '2027-02-01 10:00:00', '2027-02-03 10:00:00', 2, 5500,
            11000, 11000, 3500, '0999', 30, 0, 'approved', 'unpaid', NOW())
");
$fixtureBookingId = (int) $pdo->lastInsertId();

$availOverlap = booking_available_units($pdo, 19, '2027-02-01 00:00:00', '2027-02-04 00:00:00');
check('Chevrolet Cruze, window overlapping a held (approved) booking -> 5 free (6-1)', $availOverlap, 5);

$availNoOverlap = booking_available_units($pdo, 19, '2027-03-01 00:00:00', '2027-03-03 00:00:00');
check('Chevrolet Cruze, window not overlapping the held booking -> 6 free', $availNoOverlap, 6);

$availExcludingItself = booking_available_units($pdo, 19, '2027-02-01 00:00:00', '2027-02-04 00:00:00', $fixtureBookingId);
check('excludeBookingId frees the unit a booking holds against itself -> 6 free', $availExcludingItself, 6);

$pdo->rollBack(); // discard the fixture — never touches real data

echo "\n=== batch availability matches per-vehicle for the same window ===\n";
$batch = booking_available_units_batch($pdo, '2027-01-01 10:00:00', '2027-01-03 10:00:00');
check('batch result for vehicle 19 matches single-vehicle result', $batch[19] ?? null, $avail);

echo "\n=== booking_location_fee (Phase 8, approved rule P9, against seeded matrix) ===\n";
check('same branch (1->1) -> 0', booking_location_fee($pdo, 1, 1), 0.0);
check('adjacent (1->2) -> 300', booking_location_fee($pdo, 1, 2), 300.0);
check('non-adjacent (1->5) -> 500', booking_location_fee($pdo, 1, 5), 500.0);
check('symmetric (5->1) -> 500', booking_location_fee($pdo, 5, 1), 500.0);
check('no such pair (1->999) -> null (caller must error, never silent 0)', booking_location_fee($pdo, 1, 999), null);
check('active locations seeded (5)', count(booking_active_locations($pdo)), 5);

echo "\n=== booking_fuel_rate_for_category (Phase 11, seeded fuel_rates) ===\n";
check('Scooter rate', booking_fuel_rate_for_category($pdo, 'Scooter'), 150.0);
check('Sedan rate', booking_fuel_rate_for_category($pdo, 'Sedan'), 300.0);
check('Van rate', booking_fuel_rate_for_category($pdo, 'Van'), 500.0);
check('unknown category -> null (caller must error, never silent 0)', booking_fuel_rate_for_category($pdo, 'Spaceship'), null);

echo "\n============================================\n";
printf("%d checks, %d failed\n", $checks, $failures);
exit($failures > 0 ? 1 : 0);
