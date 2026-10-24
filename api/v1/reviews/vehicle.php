<?php
// Same data as vehicle_reviews_list.php.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/reviews.php';

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 5;

if ($vehicleId <= 0) {
  api_error('vehicle_id is required.', ['vehicle_id' => 'vehicle_id is required.'], 422);
}

$aggregate = review_aggregate_for_vehicle($pdo, $vehicleId);
$reviews = review_list_for_vehicle($pdo, $vehicleId, $perPage, ($page - 1) * $perPage);

api_success([
  'aggregate' => $aggregate,
  'reviews' => $reviews,
  'page' => $page,
  'per_page' => $perPage,
  'total_pages' => max(1, (int) ceil($aggregate['count'] / $perPage)),
]);
