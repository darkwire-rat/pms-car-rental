<?php
// "The selected vehicle is lost at the login gate".
session_start();
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

csrf_require();

$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid vehicle id is required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND is_active = 1");
$stmt->execute([$vehicleId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Vehicle not found.']);
    exit;
}

$_SESSION['pending_vehicle_id'] = $vehicleId;
echo json_encode(['success' => true]);
