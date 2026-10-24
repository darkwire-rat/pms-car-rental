<?php
// Public, no auth required. revision: same Finding A fix as vehicles/index.php — see that file's comment.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/booking_rules.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  api_error('Vehicle id is required.', ['id' => 'Vehicle id is required.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
  api_error('Vehicle not found.', [], 404);
}

$now = (new DateTime())->format('Y-m-d H:i:s');
$vehicle['available_units'] = booking_available_units($pdo, (int) $vehicle['id'], $now, $now);

api_success(['vehicle' => $vehicle]);
