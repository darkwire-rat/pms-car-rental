<?php
// Body {booking_id, rating, body}.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/reviews.php';

$user = api_require_auth($pdo);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = (int) ($data['booking_id'] ?? 0);
$rating = (int) ($data['rating'] ?? 0);
$body = trim((string) ($data['body'] ?? ''));

if ($bookingId <= 0) {
  api_error('Booking id is required.', ['booking_id' => 'Booking id is required.'], 422);
}
if ($rating < 1 || $rating > 5) {
  api_error('Rating must be between 1 and 5.', ['rating' => 'Rating must be between 1 and 5.'], 422);
}
if (mb_strlen($body) > 2000) {
  api_error('Review text must be 2000 characters or fewer.', ['body' => 'Review text must be 2000 characters or fewer.'], 422);
}

$eligibility = review_eligibility($pdo, $bookingId, (int) $user['id']);
if (!$eligibility['ok']) {
  api_error($eligibility['error'], [], 409);
}

try {
  review_create($pdo, $bookingId, (int) $eligibility['booking']['vehicle_id'], (int) $user['id'], $rating, $body);
  api_success([], 'Review submitted.', 201);
} catch (PDOException $e) {
  // A double-submit racing past the eligibility check above still can't get past UNIQUE.
  if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
    api_error('This booking has already been reviewed.', [], 409);
  }
  throw $e;
}
