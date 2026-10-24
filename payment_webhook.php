<?php
// The ONLY unauthenticated endpoint in the app, and the plan's single most severe risk.
require 'db.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/notify.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (payment_provider() === 'paymongo') {
    if (!payment_verify_paymongo_signature($raw, $signature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid PayMongo signature']);
        exit;
    }
    $event = json_decode($raw, true);
    if (!is_array($event)) {
        http_response_code(400);
        echo json_encode(['error' => 'Malformed payload']);
        exit;
    }
    try {
        $pdo->beginTransaction();
        $result = payment_apply_paymongo_event($pdo, $event);
        if ($result['payment'] === null) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => $result['error'] ?? 'Unknown payment']);
            exit;
        }
        $paidBookingId = (int) $result['payment']['booking_id'];
        $movedToPaid = payment_reconcile_booking($pdo, $paidBookingId, 'system', null);
        if ($movedToPaid) {
            // only the manual-provider branch below used to notify, so PayMongo payments never told the customer anything.
            payment_notify_paid($pdo, $paidBookingId);
        }
        $pdo->commit();
        echo json_encode(['received' => true, 'applied' => $result['applied'], 'booking_paid' => $movedToPaid]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'PayMongo webhook processing failed']);
    }
    exit;
}

$signature = $_SERVER['HTTP_X_PMS_SIGNATURE'] ?? '';

if (!payment_verify_signature($raw, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed payload']);
    exit;
}

try {
    $pdo->beginTransaction();

    $result = payment_apply_event($pdo, $event);
    if ($result['payment'] === null) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => $result['error'] ?? 'Unknown payment']);
        exit;
    }

    $movedToPaid = false;
    if ($result['payment']['status'] === 'succeeded') {
        $movedToPaid = payment_reconcile_booking($pdo, (int) $result['payment']['booking_id'], 'system', null);
        if ($movedToPaid) {
            $bk = $pdo->prepare("SELECT b.user_id, v.title FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id WHERE b.id = ?");
            $bk->execute([(int) $result['payment']['booking_id']]);
            $bk = $bk->fetch(PDO::FETCH_ASSOC);
            if ($bk) {
                notify_user($pdo, (int) $bk['user_id'], 'payment_received',
                    'Payment confirmed: ' . $bk['title'],
                    'We received your payment. Your booking is confirmed and awaiting pickup.',
                    'transactions.php', (int) $result['payment']['booking_id']);
                notify_admin($pdo, 'payment_received',
                    'Payment received: ' . $bk['title'],
                    'Booking #' . (int) $result['payment']['booking_id'] . ' paid.',
                    'admin_transaction_detail.php?id=' . (int) $result['payment']['booking_id'],
                    (int) $result['payment']['booking_id']);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['received' => true, 'applied' => $result['applied'], 'booking_paid' => $movedToPaid]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed']);
}
