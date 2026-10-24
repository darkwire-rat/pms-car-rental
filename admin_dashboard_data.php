<?php
// JSON refresh for the three Business Overview charts when the period/category/status filter form on admin-dashboard.php is.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/reporting.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid month.']);
    exit;
}

$category = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
$metricSet = $_GET['metric_set'] ?? 'fleet';
if (!is_string($metricSet) || !in_array($metricSet, ['fleet', 'bookings', 'finance'], true)) {
    $metricSet = 'fleet';
}

$statuses = isset($_GET['statuses']) && is_array($_GET['statuses'])
    ? array_values(array_intersect($_GET['statuses'], booking_status_values()))
    : [];
if ($statuses === []) {
    $statuses = null; // no selection = no restriction (reporting_revenue_by_month()'s default)
}

$revenueByMonth = reporting_revenue_by_month($pdo, $month, 12, $statuses);
$bookingsByStatus = reporting_bookings_by_status($pdo, $month);
$topVehicles = reporting_top_vehicles($pdo, $month, $category, 10);
$statusColors = reporting_status_chart_colors();
$metrics = reporting_dashboard_metric_cards($pdo, $metricSet);

$monthLabel = date('F Y', strtotime($month . '-01'));

echo json_encode([
    'success' => true,
    'metrics' => $metrics,
    'month'   => $month,
    'month_label' => $monthLabel,
    'window_label' => date('M Y', strtotime($month . '-01')),
    'revenue' => [
        'labels' => array_map(fn($ym) => date('M Y', strtotime($ym . '-01')), array_keys($revenueByMonth)),
        'data'   => array_values($revenueByMonth),
        'total'  => array_sum($revenueByMonth),
    ],
    'status' => [
        'raw'    => array_keys($bookingsByStatus),
        'labels' => array_map('booking_status_label', array_keys($bookingsByStatus)),
        'data'   => array_values($bookingsByStatus),
        'colors' => array_map(fn($s) => $statusColors[$s] ?? '#adb5bd', array_keys($bookingsByStatus)),
        'total'  => array_sum($bookingsByStatus),
    ],
    'top_vehicles' => [
        'labels' => array_column($topVehicles, 'title'),
        'data'   => array_map('intval', array_column($topVehicles, 'total')),
    ],
]);
