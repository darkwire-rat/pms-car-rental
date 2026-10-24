<?php
// Marks a 'paid' booking picked up: records picked_up_at + fuel_level_out, moves it to 'active'.
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
$fuelOut = $_POST['fuel_level_out'] ?? null;

if ($bookingId <= 0 || $fuelOut === null || $fuelOut === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Booking id and fuel level are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $result = return_flow_mark_pickup($pdo, $bookingId, (int) $_SESSION['admin_id'], (int) $fuelOut);
    if (!$result['ok']) {
        $pdo->rollBack();
        if ($result['invalid_input']) {
            http_response_code(400);
        } else {
            http_response_code($result['booking'] ? 409 : 404);
        }
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit;
    }

    $veh = $pdo->prepare("SELECT title FROM vehicles WHERE id = ?");
    $veh->execute([$result['booking']['vehicle_id']]);
    $vehicleTitle = $veh->fetchColumn() ?: 'your vehicle';

    notify_user(
        $pdo,
        (int) $result['booking']['user_id'],
        'pickup_recorded',
        'Pickup recorded: ' . $vehicleTitle,
        'Your rental of ' . $vehicleTitle . ' is now active. Have a safe trip!',
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
    echo json_encode(['success' => false, 'error' => 'Failed to mark pickup.']);
}
