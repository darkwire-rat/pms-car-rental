<?php
/* Pickup, return & inspection —. */

require_once __DIR__ . '/booking_status.php';

/** True if $level is a valid eighths-of-a-tank reading (0-8) or null. */
function return_flow_valid_fuel_level($level): bool
{
    return $level === null || (is_numeric($level) && (int) $level >= 0 && (int) $level <= 8);
}

/* Return the allowed pickup window for a paid booking. */
function return_flow_pickup_window(array $booking, ?DateTimeImmutable $now = null): array
{
    if (($booking['status'] ?? null) !== 'paid' || empty($booking['pickup_at'])) {
        return ['allowed' => false, 'error' => 'This booking is not ready for pickup.'];
    }

    $pickupAt = new DateTimeImmutable($booking['pickup_at']);
    $windowEnd = $pickupAt->modify('+1 hour');
    $current = $now ?? new DateTimeImmutable();

    if ($current->format('Y-m-d') !== $pickupAt->format('Y-m-d')) {
        return [
            'allowed' => false,
            'error' => 'Pickup can only be processed on the booked date: ' . $pickupAt->format('M j, Y') . '.',
            'starts_at' => $pickupAt,
            'ends_at' => $windowEnd,
        ];
    }
    if ($current < $pickupAt || $current > $windowEnd) {
        return [
            'allowed' => false,
            'error' => 'Pickup can only be processed between ' . $pickupAt->format('g:i A') . ' and ' . $windowEnd->format('g:i A') . ' on the booked date.',
            'starts_at' => $pickupAt,
            'ends_at' => $windowEnd,
        ];
    }

    return ['allowed' => true, 'error' => null, 'starts_at' => $pickupAt, 'ends_at' => $windowEnd];
}

/* Mark a 'paid' booking picked up: records picked_up_at + fuel_level_out, moves it to 'active'. */
function return_flow_mark_pickup(PDO $pdo, int $bookingId, int $adminId, int $fuelOut): array
{
    if ($fuelOut < 0 || $fuelOut > 8) {
        return ['ok' => false, 'error' => 'Fuel level must be between 0 and 8 (eighths of a tank).', 'booking' => null, 'invalid_input' => true];
    }

    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        return ['ok' => false, 'error' => 'Booking not found.', 'booking' => null, 'invalid_input' => false];
    }
    if ($booking['status'] !== 'paid') {
        return ['ok' => false, 'error' => 'This booking is not paid (current status: ' . booking_status_label($booking['status']) . ').', 'booking' => $booking, 'invalid_input' => false];
    }

    $pickupWindow = return_flow_pickup_window($booking);
    if (!$pickupWindow['allowed']) {
        return ['ok' => false, 'error' => $pickupWindow['error'], 'booking' => $booking, 'invalid_input' => false];
    }

    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE bookings SET status = 'active', picked_up_at = ?, fuel_level_out = ? WHERE id = ?")
        ->execute([$now, $fuelOut, $bookingId]);
    $pdo->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, 'paid', 'active', 'admin', ?, ?)"
    )->execute([$bookingId, $adminId, 'Pickup marked. Fuel out: ' . $fuelOut . '/8.']);

    $booking['status'] = 'active';
    $booking['picked_up_at'] = $now;
    $booking['fuel_level_out'] = $fuelOut;
    return ['ok' => true, 'error' => null, 'booking' => $booking];
}

/* Mark an 'active' booking returned: records actual_return_at. */
function return_flow_mark_returned(PDO $pdo, int $bookingId, string $actorType, ?int $actorId, ?string $actualReturnAt = null): array
{
    if (!in_array($actorType, ['admin', 'user'], true)) {
        throw new InvalidArgumentException("return_flow_mark_returned(): actorType must be 'admin' or 'user'");
    }

    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        return ['ok' => false, 'error' => 'Booking not found.', 'booking' => null];
    }
    if ($booking['status'] !== 'active') {
        return ['ok' => false, 'error' => 'This booking is not active (current status: ' . booking_status_label($booking['status']) . ').', 'booking' => $booking];
    }

    $returnedAt = $actualReturnAt ?? date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE bookings SET status = 'returned', actual_return_at = ? WHERE id = ?")
        ->execute([$returnedAt, $bookingId]);
    $pdo->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, 'active', 'returned', ?, ?, ?)"
    )->execute([$bookingId, $actorType, $actorId,
        $actorType === 'user' ? 'Return requested by customer.' : 'Return marked by admin.']);

    // Idempotent: a booking already routed through here keeps its one draft.
    $pdo->prepare("INSERT INTO return_inspections (booking_id, status) VALUES (?, 'draft')
                   ON DUPLICATE KEY UPDATE booking_id = booking_id")
        ->execute([$bookingId]);

    $booking['status'] = 'returned';
    $booking['actual_return_at'] = $returnedAt;
    return ['ok' => true, 'error' => null, 'booking' => $booking];
}

/** The draft (or finalized) inspection for a booking, or null if none yet. */
function return_flow_get_inspection(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM return_inspections WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* Save the draft inspection fields. */
function return_flow_save_inspection_draft(PDO $pdo, int $bookingId, ?int $fuelLevelIn, string $conditionNotes, bool $hasDamage, string $damageNotes, int $adminId): array
{
    $pdo->prepare(
        "INSERT INTO return_inspections (booking_id, fuel_level_in, condition_notes, has_damage, damage_notes, created_by_admin_id)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           fuel_level_in = VALUES(fuel_level_in),
           condition_notes = VALUES(condition_notes),
           has_damage = VALUES(has_damage),
           damage_notes = VALUES(damage_notes),
           created_by_admin_id = VALUES(created_by_admin_id)"
    )->execute([$bookingId, $fuelLevelIn, $conditionNotes, $hasDamage ? 1 : 0, $damageNotes, $adminId]);

    return return_flow_get_inspection($pdo, $bookingId);
}

/* Record a reported damage entry. */
function return_flow_add_damage(PDO $pdo, int $bookingId, string $description, float $estimatedAmount, int $adminId): int
{
    $pdo->prepare(
        "INSERT INTO booking_damages (booking_id, description, estimated_amount, created_by_admin_id)
         VALUES (?, ?, ?, ?)"
    )->execute([$bookingId, $description, $estimatedAmount, $adminId]);

    return (int) $pdo->lastInsertId();
}

/** Attach an evidence photo to an existing damage entry. */
function return_flow_add_damage_photo(PDO $pdo, int $damageId, string $filePath): void
{
    $pdo->prepare("INSERT INTO booking_damage_photos (damage_id, file_path) VALUES (?, ?)")
        ->execute([$damageId, $filePath]);
}

/* Every damage entry for a booking, oldest first, each with its `photos` array nested. */
function return_flow_list_damages(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare("SELECT * FROM booking_damages WHERE booking_id = ? ORDER BY id");
    $stmt->execute([$bookingId]);
    $damages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$damages) {
        return [];
    }

    $ids = array_column($damages, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $photoStmt = $pdo->prepare("SELECT * FROM booking_damage_photos WHERE damage_id IN ($placeholders) ORDER BY id");
    $photoStmt->execute($ids);
    $photosByDamage = [];
    foreach ($photoStmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
        $photosByDamage[(int) $photo['damage_id']][] = $photo;
    }

    foreach ($damages as &$damage) {
        $damage['photos'] = $photosByDamage[(int) $damage['id']] ?? [];
    }
    unset($damage);

    return $damages;
}

/* Lazy overdue notification. */
function return_flow_notify_overdue_once(PDO $pdo, array $booking): bool
{
    if (!booking_is_overdue($booking)) {
        return false;
    }
    $bookingId = (int) $booking['id'];

    $exists = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE booking_id = ? AND type = 'return_overdue'");
    $exists->execute([$bookingId]);
    if ((int) $exists->fetchColumn() > 0) {
        return false;
    }

    require_once __DIR__ . '/notify.php';
    $vehicleTitle = $booking['vehicle_title'] ?? ('vehicle #' . ($booking['vehicle_id'] ?? '?'));
    notify_admin(
        $pdo,
        'return_overdue',
        'Overdue return: ' . $vehicleTitle,
        'Booking #' . $bookingId . ' was due back ' . date('M j, g:i A', strtotime($booking['dropoff_at'])) . ' and has not been returned.',
        'admin_transaction_detail.php?id=' . $bookingId,
        $bookingId
    );
    return true;
}
