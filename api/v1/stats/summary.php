<?php
// The same 3 counts index.php's homepage stats bar shows, for the app's homepage to mirror it.
require_once __DIR__ . '/../common/bootstrap.php';

$totalVehicles = (int) $pdo->query("SELECT COUNT(*) AS total FROM vehicles")->fetch()['total'];
$totalUsers = (int) $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'];
$completedRentals = (int) $pdo->query("SELECT COUNT(*) AS total FROM bookings WHERE status='completed'")->fetch()['total'];

api_success([
  'total_vehicles' => $totalVehicles,
  'total_users' => $totalUsers,
  'completed_rentals' => $completedRentals,
]);
