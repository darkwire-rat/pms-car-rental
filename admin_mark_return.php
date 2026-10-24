<?php
// Marks an 'active' booking returned: records actual_return_at, moves it to 'returned', opens the draft inspection.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/return_flow.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

$bookingId = (int) ($_POST['id'] ?? 0);
$actualReturnAt = trim((string) ($_POST['actual_return_at'] ?? ''));

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Booking id is required.']);
    exit;
}

$returnAt = null;
if ($actualReturnAt !== '') {
    try {
        $returnAt = (new DateTime($actualReturnAt))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid return date/time.']);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $result = return_flow_mark_returned($pdo, $bookingId, 'admin', (int) $_SESSION['admin_id'], $returnAt);
    if (!$result['ok']) {
        $pdo->rollBack();
        http_response_code($result['booking'] ? 409 : 404);
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit;
    }

    $veh = $pdo->prepare("SELECT title FROM vehicles WHERE id = ?");
    $veh->execute([$result['booking']['vehicle_id']]);
    $vehicleTitle = $veh->fetchColumn() ?: 'your vehicle';

    notify_user(
        $pdo,
        (int) $result['booking']['user_id'],
        'return_processed',
        'Return recorded: ' . $vehicleTitle,
        'We have recorded the return of ' . $vehicleTitle . '. It will be inspected shortly.',
        'transactions.php',
        $bookingId
    );

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark return.']);
}
