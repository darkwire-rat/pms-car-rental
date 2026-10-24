<?php
// CLI test for lib/payment.php —.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/payment.php';

$failures = 0; $checks = 0;
function check($label, $actual, $expected) {
    global $failures, $checks; $checks++;
    $ok = $actual === $expected;
    if (!$ok) { $failures++; }
    printf("[%s] %s\n    actual: %s  expected: %s\n", $ok ? 'PASS' : 'FAIL', $label, var_export($actual, true), var_export($expected, true));
}

echo "=== signatures (R8) ===\n";
$payload = json_encode(['provider_ref' => 'x', 'status' => 'succeeded']);
$sig = payment_sign($payload);
check('valid signature verifies', payment_verify_signature($payload, $sig), true);
check('tampered payload fails', payment_verify_signature($payload . ' ', $sig), false);
check('wrong signature fails', payment_verify_signature($payload, 'deadbeef'), false);
check('empty signature fails', payment_verify_signature($payload, ''), false);
check('null signature fails', payment_verify_signature($payload, null), false);

echo "\n=== payment_build_settlement_proposal (Phase 11, D6) ===\n";
// Pure against hand-built arrays except the fuel_rates lookup -- no booking fixture needed for these.
$onTimeBooking = [
    'dropoff_at' => '2026-08-21 16:00:00', 'actual_return_at' => '2026-08-21 16:00:00',
    'rate' => 3400.0, 'fuel_level_out' => 8, 'vehicle_category' => 'Sedan', 'security_deposit' => 3500.0,
];
$fullFuelInspection = ['fuel_level_in' => 8];

$p1 = payment_build_settlement_proposal($pdo, $onTimeBooking, $fullFuelInspection, []);
check('on time, full fuel, no damage -> 0 deductions', $p1['total_deductions'], 0.0);
check('on time, full fuel, no damage -> outcome refund', $p1['outcome'], 'refund');
check('on time, full fuel, no damage -> full deposit refunded', $p1['refund_amount'], 3500.0);
check('on time, full fuel, no damage -> no outstanding balance', $p1['outstanding_balance'], 0.0);
check('fuel rate resolved for Sedan', $p1['fuel_rate'], 300.0);
check('fuel charge computed, not pending', $p1['fuel_pending'], false);

$p2 = payment_build_settlement_proposal($pdo, $onTimeBooking, $fullFuelInspection, [
    ['status' => 'proposed', 'estimated_amount' => 1000.0, 'adjusted_amount' => null],
]);
check('damage < deposit -> partial_refund', $p2['outcome'], 'partial_refund');
check('damage < deposit -> refund = deposit - damage', $p2['refund_amount'], 2500.0);
check('damage < deposit -> no outstanding balance', $p2['outstanding_balance'], 0.0);

$p3 = payment_build_settlement_proposal($pdo, $onTimeBooking, $fullFuelInspection, [
    ['status' => 'proposed', 'estimated_amount' => 5000.0, 'adjusted_amount' => null],
]);
check('damage > deposit -> forfeit (D6: recorded, never auto-charged)', $p3['outcome'], 'forfeit');
check('damage > deposit -> refund_amount 0', $p3['refund_amount'], 0.0);
check('damage > deposit -> outstanding_balance = damage - deposit', $p3['outstanding_balance'], 1500.0);

$p4 = payment_build_settlement_proposal($pdo, $onTimeBooking, ['fuel_level_in' => null], []);
check('fuel-in not yet recorded -> fuel_charge null, not zero', $p4['fuel_charge'], null);
check('fuel-in not yet recorded -> fuel_pending true', $p4['fuel_pending'], true);
check('fuel-in not yet recorded -> excluded from deductions (not blocking)', $p4['total_deductions'], 0.0);

$p5 = payment_build_settlement_proposal($pdo, $onTimeBooking, $fullFuelInspection, [
    ['status' => 'waived', 'estimated_amount' => 2000.0, 'adjusted_amount' => null],
    ['status' => 'proposed', 'estimated_amount' => 500.0, 'adjusted_amount' => null],
]);
check('waived damage excluded from total', $p5['damage_total'], 500.0);

$p6 = payment_build_settlement_proposal($pdo, $onTimeBooking, $fullFuelInspection, [
    ['status' => 'confirmed', 'estimated_amount' => 1000.0, 'adjusted_amount' => 700.0],
]);
check('admin-adjusted amount overrides the estimate', $p6['damage_total'], 700.0);

$lateBooking = $onTimeBooking;
$lateBooking['actual_return_at'] = '2026-08-22 00:00:00'; // 8h late -> 1 extra day (P4)
$p7 = payment_build_settlement_proposal($pdo, $lateBooking, $fullFuelInspection, []);
check('8h late -> overtime = 1 extra day', $p7['overtime'], 3400.0);
check('8h late, no other charges -> partial_refund', $p7['outcome'], 'partial_refund');
check('8h late -> refund = deposit - overtime', $p7['refund_amount'], 100.0);

$unknownCategoryBooking = $onTimeBooking;
$unknownCategoryBooking['vehicle_category'] = 'Spaceship';
$p8 = payment_build_settlement_proposal($pdo, $unknownCategoryBooking, $fullFuelInspection, []);
check('unknown category -> fuel_rate null', $p8['fuel_rate'], null);
check('unknown category -> fuel_charge null (never a silent 0)', $p8['fuel_charge'], null);

echo "\n=== ledger create / apply / idempotency ===\n";
$pdo->beginTransaction();
$ins = $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, vehicle_id, pickup_at, dropoff_at, days, rate, total_amount, rental_fee, security_deposit, contact_number, age, paid, status, payment_status, approved_at, created_at)
    VALUES ('PAYTEST', 3, 19, '2027-12-01 10:00:00', '2027-12-03 10:00:00', 2, 5500, 11000, 11000, 3500, '0999', 30, 0, 'approved', 'unpaid', NOW(), NOW())");
$ins->execute();
$bid = (int) $pdo->lastInsertId();

$rental = payment_create($pdo, $bid, 'rental', 11000.0);
check('payment_create returns a ref', str_starts_with($rental['provider_ref'], 'manual_'), true);

$ev = payment_manual_build_event($rental['provider_ref'], 'succeeded');
check('manual event self-verifies', payment_verify_signature($ev['payload'], $ev['signature']), true);

$r1 = payment_apply_event($pdo, json_decode($ev['payload'], true));
check('first apply -> applied', $r1['applied'], true);
check('payment now succeeded', $r1['payment']['status'], 'succeeded');

$r2 = payment_apply_event($pdo, json_decode($ev['payload'], true));
check('second apply -> NOT applied (idempotent)', $r2['applied'], false);

$deposit = payment_create($pdo, $bid, 'deposit', 3500.0);
$dev = payment_manual_build_event($deposit['provider_ref'], 'succeeded');
payment_apply_event($pdo, json_decode($dev['payload'], true));

$moved = payment_reconcile_booking($pdo, $bid, 'system', null);
check('reconcile moves approved -> paid', $moved, true);
$st = $pdo->query("SELECT status, payment_status, deposit_status, paid FROM bookings WHERE id = $bid")->fetch(PDO::FETCH_ASSOC);
check('booking status = paid', $st['status'], 'paid');
check('payment_status = paid', $st['payment_status'], 'paid');
check('deposit_status = held', $st['deposit_status'], 'held');
check('paid column = rental + deposit', (int) $st['paid'], 14500);

$moved2 = payment_reconcile_booking($pdo, $bid, 'system', null);
check('reconcile again -> no-op (booking not approved)', $moved2, false);

$unknown = payment_apply_event($pdo, ['provider_ref' => 'nope', 'status' => 'succeeded']);
check('unknown ref -> not applied, no payment', $unknown['payment'], null);

$pdo->rollBack(); // discard the throwaway booking + payments

echo "\n=== settle_deposit (Phase 11, finalize) ===\n";
$pdo->beginTransaction();

function makeReturnedFixture(PDO $pdo, string $ref, string $actualReturnAt): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO bookings (booking_ref, user_id, vehicle_id, pickup_at, dropoff_at, actual_return_at, picked_up_at, fuel_level_out,
                                days, rate, total_amount, rental_fee, security_deposit, contact_number, age, paid, status, payment_status, created_at)
         VALUES (?, 3, 19, '2027-12-01 10:00:00', '2027-12-03 10:00:00', ?, '2027-12-01 10:00:00', 8,
                 2, 5500, 11000, 11000, 3500, '0999', 30, 14500, 'returned', 'paid', NOW())"
    );
    $stmt->execute([$ref, $actualReturnAt]);
    return (int) $pdo->lastInsertId();
}

// Case A: no deductions -> full refund, a payments row created, status -> completed.
$bidA = makeReturnedFixture($pdo, 'SETTLETEST-A', '2027-12-03 10:00:00');
$pdo->prepare("INSERT INTO return_inspections (booking_id, fuel_level_in, status) VALUES (?, 8, 'draft')")->execute([$bidA]);
$resultA = settle_deposit($pdo, $bidA, [
    'overtime' => 0.0, 'overtime_reason' => null,
    'fuel_charge' => 0.0, 'fuel_reason' => null,
    'damages' => [],
], 1);
check('no deductions -> outcome refund', $resultA['outcome'], 'refund');
check('no deductions -> refund_amount = full deposit', $resultA['refund_amount'], 3500.0);
check('no deductions -> outstanding_balance 0', $resultA['outstanding_balance'], 0.0);

$stA = $pdo->query("SELECT status, deposit_status, deposit_refund_amount FROM bookings WHERE id = $bidA")->fetch(PDO::FETCH_ASSOC);
check('status -> completed', $stA['status'], 'completed');
check('deposit_status -> refunded', $stA['deposit_status'], 'refunded');
check('deposit_refund_amount persisted', (float) $stA['deposit_refund_amount'], 3500.0);

$refundRowA = $pdo->query("SELECT amount, status FROM payments WHERE booking_id = $bidA AND purpose = 'refund'")->fetch(PDO::FETCH_ASSOC);
check('refund payments row amount', (float) $refundRowA['amount'], 3500.0);
check('refund payments row succeeded', $refundRowA['status'], 'succeeded');

$inspA = $pdo->query("SELECT status, finalized_at FROM return_inspections WHERE booking_id = $bidA")->fetch(PDO::FETCH_ASSOC);
check('inspection status -> finalized', $inspA['status'], 'finalized');
check('inspection finalized_at set', $inspA['finalized_at'] !== null, true);

// Case B: damage exceeds deposit -> forfeit, outstanding balance, NO refund row.
$bidB = makeReturnedFixture($pdo, 'SETTLETEST-B', '2027-12-03 10:00:00');
$dmgStmt = $pdo->prepare("INSERT INTO booking_damages (booking_id, description, estimated_amount, status) VALUES (?, 'Big dent', 5000.00, 'proposed')");
$dmgStmt->execute([$bidB]);
$dmgId = (int) $pdo->lastInsertId();

$resultB = settle_deposit($pdo, $bidB, [
    'overtime' => 0.0, 'overtime_reason' => null,
    'fuel_charge' => 0.0, 'fuel_reason' => null,
    'damages' => [['id' => $dmgId, 'waived' => false, 'final_amount' => 5000.0, 'reason' => null]],
], 1);
check('damage > deposit -> outcome forfeit', $resultB['outcome'], 'forfeit');
check('damage > deposit -> outstanding_balance', $resultB['outstanding_balance'], 1500.0);
check('damage > deposit -> refund_amount 0', $resultB['refund_amount'], 0.0);

$refundRowBCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE booking_id = $bidB AND purpose = 'refund'")->fetchColumn();
check('no refund row when refund_amount is 0', $refundRowBCount, 0);

$dmgRowB = $pdo->query("SELECT status, adjusted_amount FROM booking_damages WHERE id = $dmgId")->fetch(PDO::FETCH_ASSOC);
check('damage confirmed at its final amount', $dmgRowB['status'], 'confirmed');
check('damage adjusted_amount persisted', (float) $dmgRowB['adjusted_amount'], 5000.0);

// Case C: admin waives a damage entry with a reason -> excluded from deductions.
$bidC = makeReturnedFixture($pdo, 'SETTLETEST-C', '2027-12-03 10:00:00');
$dmgStmtC = $pdo->prepare("INSERT INTO booking_damages (booking_id, description, estimated_amount, status) VALUES (?, 'Pre-existing scuff', 800.00, 'proposed')");
$dmgStmtC->execute([$bidC]);
$dmgIdC = (int) $pdo->lastInsertId();

$resultC = settle_deposit($pdo, $bidC, [
    'overtime' => 0.0, 'overtime_reason' => null,
    'fuel_charge' => 0.0, 'fuel_reason' => null,
    'damages' => [['id' => $dmgIdC, 'waived' => true, 'final_amount' => 0.0, 'reason' => 'Pre-existing, confirmed via prior inspection photos.']],
], 1);
check('waived damage -> outcome refund (excluded from deductions)', $resultC['outcome'], 'refund');
check('waived damage -> total_deductions 0', $resultC['total_deductions'], 0.0);

$dmgRowC = $pdo->query("SELECT status, adjustment_reason FROM booking_damages WHERE id = $dmgIdC")->fetch(PDO::FETCH_ASSOC);
check('waived damage status', $dmgRowC['status'], 'waived');
check('waived damage reason persisted', $dmgRowC['adjustment_reason'], 'Pre-existing, confirmed via prior inspection photos.');

// Case D: admin overrides overtime with a reason -> persisted, used in the total.
$bidD = makeReturnedFixture($pdo, 'SETTLETEST-D', '2027-12-03 18:00:00'); // 8h late -> computed default would be 5500
$resultD = settle_deposit($pdo, $bidD, [
    'overtime' => 2000.0, 'overtime_reason' => 'Customer called ahead; goodwill reduction from the computed 5500.',
    'fuel_charge' => 0.0, 'fuel_reason' => null,
    'damages' => [],
], 1);
check('overridden overtime used in total, not the computed default', $resultD['total_deductions'], 2000.0);

$stD = $pdo->query("SELECT overtime_amount, overtime_adjustment_reason FROM bookings WHERE id = $bidD")->fetch(PDO::FETCH_ASSOC);
check('overtime_amount persisted at the override', (float) $stD['overtime_amount'], 2000.0);
check('overtime_adjustment_reason persisted', $stD['overtime_adjustment_reason'], 'Customer called ahead; goodwill reduction from the computed 5500.');

$pdo->rollBack(); // discard every settle_deposit fixture

echo "\n============================================\n";
printf("%d checks, %d failed\n", $checks, $failures);
exit($failures > 0 ? 1 : 0);
