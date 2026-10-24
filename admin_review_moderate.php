<?php
// Toggles a review's public visibility.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/reviews.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

$reviewId = (int) ($_POST['review_id'] ?? 0);
$action = $_POST['action'] ?? '';
$status = $action === 'hide' ? 'hidden' : ($action === 'unhide' ? 'visible' : null);

if ($reviewId <= 0 || $status === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid review id and action (hide/unhide) are required.']);
    exit;
}

try {
    if (!review_set_status($pdo, $reviewId, $status)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Review not found.']);
        exit;
    }
    echo json_encode(['success' => true, 'status' => $status]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update the review.']);
}
