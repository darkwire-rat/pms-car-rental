<?php
// Posts the one admin_response on a review.
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
$response = trim((string) ($_POST['response'] ?? ''));

if ($reviewId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid review id is required.']);
    exit;
}
if ($response === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A response is required.']);
    exit;
}
if (mb_strlen($response) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Response must be 2000 characters or fewer.']);
    exit;
}

try {
    if (!review_set_admin_response($pdo, $reviewId, $response)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Review not found.']);
        exit;
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the response.']);
}
