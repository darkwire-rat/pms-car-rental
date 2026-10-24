<?php
/* The database is the real duplicate guard and the real rating validator. */

/* "Juan D." from "Juan Dela Cruz" — first name in full, last name reduced to an initial. */
function review_format_attribution(string $fullName): string
{
    $parts = array_values(array_filter(preg_split('/\s+/', trim($fullName)), fn($p) => $p !== ''));
    if (count($parts) === 0) {
        return 'Anonymous';
    }
    if (count($parts) === 1) {
        return $parts[0];
    }
    return $parts[0] . ' ' . mb_substr(end($parts), 0, 1) . '.';
}

/* Whether $userId may submit a review for $bookingId right now. */
function review_eligibility(PDO $pdo, int $bookingId, int $userId): array
{
    $stmt = $pdo->prepare("SELECT id, user_id, vehicle_id, status FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        return ['ok' => false, 'error' => 'Booking not found.', 'booking' => null];
    }
    if ((int) $booking['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'This booking does not belong to you.', 'booking' => $booking];
    }
    if ($booking['status'] !== 'completed') {
        return ['ok' => false, 'error' => 'Only completed rentals can be reviewed.', 'booking' => $booking];
    }

    $existing = $pdo->prepare("SELECT id FROM vehicle_reviews WHERE booking_id = ?");
    $existing->execute([$bookingId]);
    if ($existing->fetchColumn() !== false) {
        return ['ok' => false, 'error' => 'This booking has already been reviewed.', 'booking' => $booking];
    }

    return ['ok' => true, 'error' => null, 'booking' => $booking];
}

/* Caller must have already passed review_eligibility. */
function review_create(PDO $pdo, int $bookingId, int $vehicleId, int $userId, int $rating, string $body): int
{
    $pdo->prepare(
        "INSERT INTO vehicle_reviews (booking_id, vehicle_id, user_id, rating, body) VALUES (?, ?, ?, ?, ?)"
    )->execute([$bookingId, $vehicleId, $userId, $rating, $body !== '' ? $body : null]);

    return (int) $pdo->lastInsertId();
}

/* For a set of booking ids, the review each already has, keyed by booking_id. */
function review_map_for_bookings(PDO $pdo, array $bookingIds): array
{
    if (!$bookingIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $stmt = $pdo->prepare("SELECT booking_id, rating, status FROM vehicle_reviews WHERE booking_id IN ($placeholders)");
    $stmt->execute($bookingIds);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['booking_id']] = $row;
    }
    return $out;
}

/* Aggregate rating for a vehicle's VISIBLE reviews only. count=0 returns average=null. */
function review_aggregate_for_vehicle(PDO $pdo, int $vehicleId): array
{
    $stmt = $pdo->prepare("SELECT rating FROM vehicle_reviews WHERE vehicle_id = ? AND status = 'visible'");
    $stmt->execute([$vehicleId]);
    $ratings = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $count = count($ratings);
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($ratings as $r) {
        $distribution[$r]++;
    }

    return [
        'count'        => $count,
        'average'      => $count > 0 ? round(array_sum($ratings) / $count, 1) : null,
        'distribution' => $distribution,
    ];
}

/* Aggregates for EVERY active vehicle in one query — avoids an N+1 when rendering the listing grid. */
function review_aggregates_batch(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT vehicle_id, COUNT(*) AS cnt, AVG(rating) AS avg_rating
         FROM vehicle_reviews WHERE status = 'visible' GROUP BY vehicle_id"
    );
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['vehicle_id']] = [
            'count'   => (int) $row['cnt'],
            'average' => round((float) $row['avg_rating'], 1),
        ];
    }
    return $out;
}

/* Site-wide review summary for the admin dashboard card. */
function review_admin_summary(PDO $pdo): array
{
    $ratings = array_map('intval', $pdo->query("SELECT rating FROM vehicle_reviews WHERE status = 'visible'")->fetchAll(PDO::FETCH_COLUMN));
    $count = count($ratings);
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($ratings as $r) {
        $distribution[$r]++;
    }

    $recentStmt = $pdo->query(
        "SELECT r.rating, r.created_at, u.name AS user_name, v.title AS vehicle_title
         FROM vehicle_reviews r
         JOIN users u ON u.id = r.user_id
         JOIN vehicles v ON v.id = r.vehicle_id
         WHERE r.status = 'visible'
         ORDER BY r.created_at DESC
         LIMIT 3"
    );
    $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recent as &$row) {
        $row['attribution'] = review_format_attribution($row['user_name']);
        unset($row['user_name']);
    }
    unset($row);

    return [
        'count'        => $count,
        'average'      => $count > 0 ? round(array_sum($ratings) / $count, 1) : null,
        'distribution' => $distribution,
        'recent'       => $recent,
    ];
}

/* Every review, newest first, for the admin moderation list — the full customer name and email. */
function review_admin_list(PDO $pdo): array
{
    return $pdo->query(
        "SELECT r.id, r.rating, r.body, r.status, r.admin_response, r.admin_response_at, r.created_at,
                u.name AS user_name, u.email AS user_email, v.title AS vehicle_title
         FROM vehicle_reviews r
         JOIN users u ON u.id = r.user_id
         JOIN vehicles v ON v.id = r.vehicle_id
         ORDER BY r.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/* Toggle a review's public visibility. */
function review_set_status(PDO $pdo, int $reviewId, string $status): bool
{
    if (!in_array($status, ['visible', 'hidden'], true)) {
        throw new InvalidArgumentException("review_set_status(): status must be 'visible' or 'hidden'");
    }
    $stmt = $pdo->prepare("UPDATE vehicle_reviews SET status = ? WHERE id = ?");
    $stmt->execute([$status, $reviewId]);
    return $stmt->rowCount() > 0;
}

/* Set the one admin_response on a review. */
function review_set_admin_response(PDO $pdo, int $reviewId, string $response): bool
{
    $stmt = $pdo->prepare("UPDATE vehicle_reviews SET admin_response = ?, admin_response_at = NOW() WHERE id = ?");
    $stmt->execute([$response, $reviewId]);
    return $stmt->rowCount() > 0;
}

/* Paginated, VISIBLE reviews for a vehicle, newest first, attribution already formatted. */
function review_list_for_vehicle(PDO $pdo, int $vehicleId, int $limit = 10, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        "SELECT r.id, r.rating, r.body, r.admin_response, r.admin_response_at, r.created_at, u.name AS user_name
         FROM vehicle_reviews r JOIN users u ON u.id = r.user_id
         WHERE r.vehicle_id = ? AND r.status = 'visible'
         ORDER BY r.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $vehicleId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['attribution'] = review_format_attribution($row['user_name']);
        unset($row['user_name']);
    }
    unset($row);

    return $rows;
}
