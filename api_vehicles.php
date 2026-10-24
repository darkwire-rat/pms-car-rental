<?php
// JSON vehicle listing/detail for API consumers revision.
require 'db.php';
require_once __DIR__ . '/lib/booking_rules.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$now = (new DateTime())->format('Y-m-d H:i:s');

if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND is_active = 1");
  $stmt->execute([$id]);
  $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$vehicle) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehicle not found']);
    exit;
  }

  $vehicle['available_units'] = booking_available_units($pdo, (int)$vehicle['id'], $now, $now);

  echo json_encode(['success' => true, 'vehicle' => $vehicle]);
  exit;
}

$category = isset($_GET['category']) ? trim($_GET['category']) : null;

if ($category) {
  $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE is_active = 1 AND category = ? ORDER BY title ASC");
  $stmt->execute([$category]);
} else {
  $stmt = $pdo->query("SELECT * FROM vehicles WHERE is_active = 1 ORDER BY title ASC");
}
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availableByVehicle = booking_available_units_batch($pdo, $now, $now);
foreach ($vehicles as &$v) {
  $v['available_units'] = max(0, $availableByVehicle[(int) $v['id']] ?? (int) $v['units_total']);
}
unset($v);

echo json_encode(['success' => true, 'vehicles' => $vehicles]);
