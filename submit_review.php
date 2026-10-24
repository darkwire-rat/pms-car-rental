<?php
// Submits a review for a completed booking.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/reviews.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first']);
    exit;
}

csrf_require();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = (int) ($data['booking_id'] ?? 0);
$rating = (int) ($data['rating'] ?? 0);
$body = trim((string) ($data['body'] ?? ''));

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Booking id is required.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Rating must be between 1 and 5.']);
    exit;
}
if (mb_strlen($body) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Review text must be 2000 characters or fewer.']);
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$eligibility = review_eligibility($pdo, $bookingId, $userId);
if (!$eligibility['ok']) {
    http_response_code(409);
    echo json_encode(['error' => $eligibility['error']]);
    exit;
}

try {
    review_create($pdo, $bookingId, (int) $eligibility['booking']['vehicle_id'], $userId, $rating, $body);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // A double-submit racing past the eligibility check above still can't get past UNIQUE.
    if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
        http_response_code(409);
        echo json_encode(['error' => 'This booking has already been reviewed.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit the review.']);
}
