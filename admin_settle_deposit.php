<?php
// The finalize action: work's "review -> finalise" step, and the only place anything is ever actually deducted.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/booking_rules.php';
require_once __DIR__ . '/lib/return_flow.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$overtimeInput = $_POST['overtime_amount'] ?? '';
$overtimeReasonInput = trim((string) ($_POST['overtime_reason'] ?? ''));
$fuelInput = $_POST['fuel_amount'] ?? '';
$fuelReasonInput = trim((string) ($_POST['fuel_reason'] ?? ''));
$damageWaivedInput = $_POST['damage_waived'] ?? [];
$damageAmountInput = $_POST['damage_amount'] ?? [];
$damageReasonInput = $_POST['damage_reason'] ?? [];

if ($bookingId <= 0 || !is_numeric($overtimeInput) || (float) $overtimeInput < 0
    || !is_numeric($fuelInput) || (float) $fuelInput < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid overtime and fuel amounts are required.']);
    exit;
}

/** Two amounts differ by more than a rounding cent. */
function settlement_amounts_differ(float $a, float $b): bool
{
    return abs($a - $b) > 0.005;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT b.*, v.category AS vehicle_category
        FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.id = ? FOR UPDATE
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found.']);
        exit;
    }
    if ($booking['status'] !== 'returned') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This booking cannot be settled (current status: ' . booking_status_label($booking['status']) . ').']);
        exit;
    }

    $inspection = return_flow_get_inspection($pdo, $bookingId);
    if (!$inspection || $inspection['status'] === 'finalized') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This booking has already been settled.']);
        exit;
    }
    if ($inspection['fuel_level_in'] === null) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Record the fuel-in reading on the inspection before finalizing settlement.']);
        exit;
    }

    $damages = return_flow_list_damages($pdo, $bookingId);
    $proposal = payment_build_settlement_proposal($pdo, $booking, $inspection, $damages);
    if ($proposal['fuel_rate'] === null) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No fuel rate is configured for this vehicle\'s category (' . htmlspecialchars((string) $booking['vehicle_category']) . '). Add one to fuel_rates before finalizing.']);
        exit;
    }

    $overtimeFinal = round((float) $overtimeInput, 2);
    $overtimeChanged = settlement_amounts_differ($overtimeFinal, $proposal['overtime']);
    if ($overtimeChanged && $overtimeReasonInput === '') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A reason is required for the overtime amount you changed (was ₱' . number_format($proposal['overtime'], 2) . ').']);
        exit;
    }

    $fuelFinal = round((float) $fuelInput, 2);
    $fuelChanged = settlement_amounts_differ($fuelFinal, $proposal['fuel_charge']);
    if ($fuelChanged && $fuelReasonInput === '') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A reason is required for the fuel amount you changed (was ₱' . number_format($proposal['fuel_charge'], 2) . ').']);
        exit;
    }

    $lineDamages = [];
    foreach ($damages as $damage) {
        if ($damage['status'] !== 'proposed') {
            continue; // already confirmed/waived by an earlier finalize attempt on this booking
        }
        $id = (int) $damage['id'];
        $waived = !empty($damageWaivedInput[$id]);
        $reason = trim((string) ($damageReasonInput[$id] ?? ''));

        if ($waived) {
            if ($reason === '') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'A reason is required to waive "' . htmlspecialchars($damage['description']) . '".']);
                exit;
            }
            $lineDamages[] = ['id' => $id, 'waived' => true, 'final_amount' => 0.0, 'reason' => $reason];
            continue;
        }

        $amountInput = $damageAmountInput[$id] ?? (string) $damage['estimated_amount'];
        if (!is_numeric($amountInput) || (float) $amountInput < 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid final amount for "' . htmlspecialchars($damage['description']) . '".']);
            exit;
        }
        $finalAmount = round((float) $amountInput, 2);
        $changed = settlement_amounts_differ($finalAmount, (float) $damage['estimated_amount']);
        if ($changed && $reason === '') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A reason is required for the adjusted amount on "' . htmlspecialchars($damage['description']) . '".']);
            exit;
        }
        $lineDamages[] = ['id' => $id, 'waived' => false, 'final_amount' => $finalAmount, 'reason' => $changed ? $reason : null];
    }

    $result = settle_deposit($pdo, $bookingId, [
        'overtime' => $overtimeFinal,
        'overtime_reason' => $overtimeChanged ? $overtimeReasonInput : null,
        'fuel_charge' => $fuelFinal,
        'fuel_reason' => $fuelChanged ? $fuelReasonInput : null,
        'damages' => $lineDamages,
    ], (int) $_SESSION['admin_id']);

    $outcomeText = [
        'refund'         => 'Your full security deposit of ₱' . number_format($result['refund_amount'], 2) . ' has been refunded.',
        'partial_refund' => '₱' . number_format($result['total_deductions'], 2) . ' was deducted from your security deposit; ₱' . number_format($result['refund_amount'], 2) . ' has been refunded.',
        'forfeit'        => 'Your security deposit was fully applied to charges of ₱' . number_format($result['total_deductions'], 2) . '. An outstanding balance of ₱' . number_format($result['outstanding_balance'], 2) . ' has been recorded on your account.',
    ][$result['outcome']];

    $veh = $pdo->prepare("SELECT title FROM vehicles WHERE id = ?");
    $veh->execute([$booking['vehicle_id']]);
    $vehicleTitle = $veh->fetchColumn() ?: 'your vehicle';

    notify_user(
        $pdo,
        (int) $booking['user_id'],
        'settlement_finalized',
        'Rental settled: ' . $vehicleTitle,
        'Overtime ₱' . number_format($overtimeFinal, 2) . ', fuel ₱' . number_format($fuelFinal, 2)
            . ', damage ₱' . number_format(array_sum(array_map(fn($d) => $d['waived'] ? 0 : $d['final_amount'], $lineDamages)), 2)
            . '. ' . $outcomeText,
        'transactions.php',
        $bookingId
    );

    // A distinct notification from settlement_finalized above, not folded into it.
    notify_user(
        $pdo,
        (int) $booking['user_id'],
        'review_invitation',
        'How was your rental of ' . $vehicleTitle . '?',
        'Your rental is complete — we would love to hear about your experience. Leave a review from your Transactions page.',
        'transactions.php',
        $bookingId
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to finalize settlement.']);
}
