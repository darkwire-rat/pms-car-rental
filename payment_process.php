<?php
// The "pay now" action for an approved booking: collects the rental total + the security deposit in one step.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/notify.php';
header('Content-Type: application/json');

$isAdmin = !empty($_SESSION['admin_id']);
$userId  = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
if (!$isAdmin && !$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Please sign in.']);
    exit;
}

csrf_require();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$bookingId = (int) ($data['booking_id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Booking id is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT b.*, v.title AS vehicle_title
        FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found.']);
        exit;
    }
    if (!$isAdmin && (int) $booking['user_id'] !== $userId) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'This is not your booking.']);
        exit;
    }
    if ($booking['status'] !== 'approved') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This booking is not awaiting payment (status: ' . $booking['status'] . ').']);
        exit;
    }
    if ($booking['payment_status'] === 'paid') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This booking is already paid.']);
        exit;
    }

    $rentalAmount  = (float) $booking['total_amount'];
    $depositAmount = (float) $booking['security_deposit'];

    if (payment_provider() === 'paymongo') {
        $checkout = payment_paymongo_create_checkout(
            $pdo,
            $bookingId,
            (string) $booking['vehicle_title'],
            $rentalAmount,
            $depositAmount,
            $_SESSION['user']['email'] ?? null
        );
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'provider' => 'paymongo',
            'checkout_url' => $checkout['checkout_url'],
        ]);
        exit;
    }

    // Skip lines that already succeeded (a retry after a partial failure).
    $have = $pdo->prepare("SELECT purpose FROM payments WHERE booking_id = ? AND status = 'succeeded' AND purpose IN ('rental','deposit')");
    $have->execute([$bookingId]);
    $done = $have->fetchAll(PDO::FETCH_COLUMN);

    $lines = [];
    if (!in_array('rental', $done, true)) {
        $lines[] = ['purpose' => 'rental', 'amount' => $rentalAmount];
    }
    if ($depositAmount > 0 && !in_array('deposit', $done, true)) {
        $lines[] = ['purpose' => 'deposit', 'amount' => $depositAmount];
    }

    foreach ($lines as $line) {
        $created = payment_create($pdo, $bookingId, $line['purpose'], $line['amount']);
        // Manual provider "processes" it: build the signed event a gateway would POST, verify our own signature, apply it.
        $ev = payment_manual_build_event($created['provider_ref'], 'succeeded');
        if (!payment_verify_signature($ev['payload'], $ev['signature'])) {
            throw new RuntimeException('Signature self-check failed.');
        }
        $result = payment_apply_event($pdo, json_decode($ev['payload'], true));
        if (!$result['payment'] || $result['payment']['status'] !== 'succeeded') {
            throw new RuntimeException('Payment did not settle.');
        }
    }

    $actorType = $isAdmin ? 'admin' : 'user';
    $actorId   = $isAdmin ? (int) $_SESSION['admin_id'] : $userId;
    $moved = payment_reconcile_booking($pdo, $bookingId, $actorType, $actorId);

    if ($moved) {
        notify_admin($pdo, 'payment_received',
            'Payment received: ' . $booking['vehicle_title'],
            'Booking #' . $bookingId . ' paid (rental ₱' . number_format($rentalAmount, 2)
                . ($depositAmount > 0 ? ' + deposit ₱' . number_format($depositAmount, 2) : '') . ').',
            'admin_transaction_detail.php?id=' . $bookingId, $bookingId);
        notify_user($pdo, (int) $booking['user_id'], 'payment_received',
            'Payment confirmed: ' . $booking['vehicle_title'],
            'We received your payment. Your booking is confirmed and awaiting pickup.',
            'transactions.php', $bookingId);
    }

    $pdo->commit();
    echo json_encode([
        'success'    => true,
        'paid'       => $moved,
        'rental'     => $rentalAmount,
        'deposit'    => $depositAmount,
        'collected'  => $rentalAmount + $depositAmount,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Payment failed. Please try again.']);
}
