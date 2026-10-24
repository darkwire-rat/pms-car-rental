<?php
// Public, read-only JSON: a page of a vehicle's VISIBLE reviews, newest first.
require 'db.php';
require_once __DIR__ . '/lib/reviews.php';
header('Content-Type: application/json');

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 5;

if ($vehicleId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'vehicle_id is required.']);
    exit;
}

$aggregate = review_aggregate_for_vehicle($pdo, $vehicleId);
$reviews = review_list_for_vehicle($pdo, $vehicleId, $perPage, ($page - 1) * $perPage);

echo json_encode([
    'success'     => true,
    'aggregate'   => $aggregate,
    'reviews'     => $reviews,
    'page'        => $page,
    'per_page'    => $perPage,
    'total_pages' => max(1, (int) ceil($aggregate['count'] / $perPage)),
]);
