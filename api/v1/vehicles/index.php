<?php
// Public, no auth required. revision: this endpoint carried the same defect as vehicles.php before this phase.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/booking_rules.php';

$category = isset($_GET['category']) ? trim($_GET['category']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

$sql = "SELECT * FROM vehicles WHERE is_active = 1";
$params = [];
if ($category) {
  $sql .= " AND category = ?";
  $params[] = $category;
}
if ($search) {
  $sql .= " AND title LIKE ?";
  $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY title ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

$now = (new DateTime())->format('Y-m-d H:i:s');
$availableByVehicle = booking_available_units_batch($pdo, $now, $now);

foreach ($vehicles as &$v) {
  $v['available_units'] = max(0, $availableByVehicle[(int) $v['id']] ?? (int) $v['units_total']);
}
unset($v);

api_success(['vehicles' => $vehicles]);
