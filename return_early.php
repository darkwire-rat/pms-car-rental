<?php
// This endpoint used to end the rental itself.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/return_flow.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first']);
    exit;
}

csrf_require();

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = (int) ($data['booking_id'] ?? 0);

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Booking ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Ownership check first.
    $owner = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ? FOR UPDATE");
    $owner->execute([$booking_id]);
    $ownerRow = $owner->fetch(PDO::FETCH_ASSOC);
    if (!$ownerRow || (int) $ownerRow['user_id'] !== (int) $_SESSION['user']['id']) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        exit;
    }

    $result = return_flow_mark_returned($pdo, $booking_id, 'user', (int) $_SESSION['user']['id']);
    if (!$result['ok']) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => $result['error']]);
        exit;
    }
    $booking = $result['booking'];

    $veh = $pdo->prepare("SELECT title FROM vehicles WHERE id = ?");
    $veh->execute([$booking['vehicle_id']]);
    $vehicleName = $veh->fetchColumn() ?: 'your vehicle';

    $originalDropoff = new DateTime($booking['dropoff_at']);
    $actualReturn = new DateTime($booking['actual_return_at']);
    $isEarlyReturn = $actualReturn < $originalDropoff ? 1 : 0;

    // Notifications. "Return submitted" is one of the six approved admin events — it drives the inspection queue adds.
    notify_admin(
        $pdo,
        'return_submitted',
        'Return requested: ' . $vehicleName,
        sprintf('%s requested the return of %s (booking #%d)%s. Awaiting inspection.',
            $_SESSION['user']['name'] ?? ('Customer #' . (int) $_SESSION['user']['id']),
            $vehicleName, $booking_id,
            $isEarlyReturn ? ' early' : ''),
        'admin_transaction_detail.php?id=' . $booking_id,
        $booking_id
    );
    notify_user(
        $pdo,
        (int) $_SESSION['user']['id'],
        'return_processed',
        'Return recorded: ' . $vehicleName,
        'We recorded your return of ' . $vehicleName . '. An admin will inspect it and settle your security deposit shortly.',
        'transactions.php',
        $booking_id
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Return recorded — awaiting inspection',
        'booking_details' => [
            'booking_ref' => $booking['booking_ref'],
            'vehicle_name' => $vehicleName,
            'rental_date' => $booking['rental_date'],
            'return_date' => $booking['return_date'],
            'status' => 'returned',
            'actual_return_date' => $booking['actual_return_at'],
            'early_return' => $isEarlyReturn,
        ]
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to process return: ' . $e->getMessage()
    ]);
}
