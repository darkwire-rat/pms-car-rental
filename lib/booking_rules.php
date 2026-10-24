<?php
/* Shared booking business rules. */

require_once __DIR__ . '/booking_status.php';

// Duration & minimum-rental rules.

/* Hours between pickup and dropoff. */
function booking_duration_hours(string $pickupAt, string $dropoffAt): float
{
    $pickup = new DateTime($pickupAt);
    $dropoff = new DateTime($dropoffAt);

    return ($dropoff->getTimestamp() - $pickup->getTimestamp()) / 3600;
}

/* Billable rental days, per approved rule P1: CEIL. */
function booking_billable_days(string $pickupAt, string $dropoffAt): int
{
    $hours = booking_duration_hours($pickupAt, $dropoffAt);
    if ($hours <= 0) {
        return 1;
    }

    return max(1, (int) ceil($hours / 24));
}

/* Validates a rental window against R1/R3 and the pre-existing "pickup cannot be in the past" rule this app already enforced. */
function booking_validate_rental_window(string $pickupAt, string $dropoffAt, ?string $now = null): ?string
{
    $pickup = new DateTime($pickupAt);
    $dropoff = new DateTime($dropoffAt);
    $reference = $now !== null ? new DateTime($now) : new DateTime();

    if ($pickup < $reference) {
        return 'Pickup date/time cannot be in the past.';
    }

    $minDropoff = (clone $pickup)->modify('+24 hours');
    if ($dropoff < $minDropoff) {
        return sprintf(
            'The minimum rental is 24 hours. With pickup on %s, the earliest return is %s.',
            $pickup->format('M j, Y \a\t g:i A'),
            $minDropoff->format('M j, Y \a\t g:i A')
        );
    }

    return null;
}

/* Human-readable duration label, e.g. "1 day 6 hours" or "2 days 30 minutes". */
function booking_format_duration(float $hours): string
{
    $totalMinutes = (int) round($hours * 60);
    if ($totalMinutes <= 0) {
        return '0 hours';
    }

    $days = intdiv($totalMinutes, 1440);
    $remainder = $totalMinutes % 1440;
    $hrs = intdiv($remainder, 60);
    $mins = $remainder % 60;

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
    }
    if ($hrs > 0) {
        $parts[] = $hrs . ' ' . ($hrs === 1 ? 'hour' : 'hours');
    }
    if ($mins > 0) {
        $parts[] = $mins . ' ' . ($mins === 1 ? 'minute' : 'minutes');
    }

    return implode(' ', $parts);
}

// Pricing.

/** Rental fee for a booking, given its daily rate and billable days (P1). */
function booking_calculate_rental_fee(float $ratePerDay, int $billableDays): float
{
    return round($ratePerDay * $billableDays, 2);
}

// Location fee — a relocation surcharge from the explicit `location_fees` matrix.

/** Active branches, ordered for a dropdown. [[id, name, city], ...]. */
function booking_active_locations(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, name, city FROM locations WHERE is_active = 1 ORDER BY sort_order, name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/* Fee for a pickup→dropoff branch pair, from the `location_fees` matrix, or null if that pair has no matrix row. */
function booking_location_fee(PDO $pdo, int $pickupLocationId, int $dropoffLocationId): ?float
{
    $stmt = $pdo->prepare(
        "SELECT fee FROM location_fees WHERE pickup_location_id = ? AND dropoff_location_id = ?"
    );
    $stmt->execute([$pickupLocationId, $dropoffLocationId]);
    $fee = $stmt->fetchColumn();
    return $fee === false ? null : (float) $fee;
}

/* Security deposit for a booking, per approved rule P5. */
function booking_calculate_security_deposit(float $rentalSubtotal, ?float $vehicleOverride = null): float
{
    if ($vehicleOverride !== null) {
        return round($vehicleOverride, 2);
    }

    $raw = $rentalSubtotal * 0.30;
    $rounded = ceil($raw / 500) * 500;      // up to the nearest ₱500
    $bounded = min(5000.0, max(1000.0, $rounded));

    return round($bounded, 2);
}

/* Cancellation refund percentage, per approved rule P7. */
function booking_calculate_refund_percentage(string $pickupAt, ?string $now = null): int
{
    $pickup = new DateTime($pickupAt);
    $reference = $now !== null ? new DateTime($now) : new DateTime();

    if ($reference >= $pickup) {
        return 0; // rental has already started
    }

    $hoursUntil = ($pickup->getTimestamp() - $reference->getTimestamp()) / 3600;

    return $hoursUntil >= 24 ? 100 : 50;
}

// Overtime & fuel settlement — the two pure charge formulas the return-inspection finalize flow proposes.

/* Overtime charge for a late return, per approved rules P2/P3/P4. */
function booking_calculate_overtime(string $dueAt, string $actualAt, float $dailyRate): float
{
    $lateHours = booking_duration_hours($dueAt, $actualAt);

    if ($lateHours <= 1.0) {
        return 0.0; // on time, early, or within the 60-minute grace (P2)
    }

    if ($lateHours <= 6.0) {
        $startedHours = (int) ceil($lateHours); // P3
        return round($dailyRate * 0.10 * $startedHours, 2);
    }

    $extraDays = 1 + (int) floor(($lateHours - 6.0) / 24.0); // P4
    return round($dailyRate * $extraDays, 2);
}

/* Fuel charge for a return, per approved rule P6: per eighth of a tank missing, at a per-category rate. */
function booking_calculate_fuel_charge(int $fuelOut, int $fuelIn, float $ratePerEighth): float
{
    $missing = max(0, $fuelOut - $fuelIn);
    return round($missing * $ratePerEighth, 2);
}

/* The per-eighth fuel rate for a vehicle category, from `fuel_rates`, or null if that category has no row. */
function booking_fuel_rate_for_category(PDO $pdo, string $category): ?float
{
    $stmt = $pdo->prepare("SELECT rate_per_eighth FROM fuel_rates WHERE category = ?");
    $stmt->execute([$category]);
    $rate = $stmt->fetchColumn();
    return $rate === false ? null : (float) $rate;
}

// Availability — datetime-aware, overlap-correct, and the single source both the web app and the API use.

/* Units of $vehicleId available for the half-open window [$pickupAt, $dropoffAt). */
function booking_available_units(PDO $pdo, int $vehicleId, string $pickupAt, string $dropoffAt, ?int $excludeBookingId = null): int
{
    $holdStatuses = booking_status_holds_inventory();
    $statusPlaceholders = implode(',', array_fill(0, count($holdStatuses), '?'));

    $sql = "
        SELECT v.units_total - COALESCE((
            SELECT COUNT(*) FROM bookings b
            WHERE b.vehicle_id = v.id
              AND b.status IN ($statusPlaceholders)
              AND b.pickup_at < ?
              AND b.dropoff_at > ?"
              . ($excludeBookingId !== null ? ' AND b.id != ?' : '') . '
        ), 0) AS available
        FROM vehicles v
        WHERE v.id = ?
    ';

    $params = array_merge($holdStatuses, [$dropoffAt, $pickupAt]);
    if ($excludeBookingId !== null) {
        $params[] = $excludeBookingId;
    }
    $params[] = $vehicleId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['available'] : 0;
}

/** True if at least one unit of $vehicleId is free for the given window. */
function booking_is_vehicle_available(PDO $pdo, int $vehicleId, string $pickupAt, string $dropoffAt, ?int $excludeBookingId = null): bool
{
    return booking_available_units($pdo, $vehicleId, $pickupAt, $dropoffAt, $excludeBookingId) > 0;
}

/* Available units for EVERY active vehicle, for a single window, in one query. */
function booking_available_units_batch(PDO $pdo, string $pickupAt, string $dropoffAt): array
{
    $holdStatuses = booking_status_holds_inventory();
    $statusPlaceholders = implode(',', array_fill(0, count($holdStatuses), '?'));

    $sql = "
        SELECT v.id, v.units_total - COALESCE(bc.held, 0) AS available
        FROM vehicles v
        LEFT JOIN (
            SELECT vehicle_id, COUNT(*) AS held
            FROM bookings
            WHERE status IN ($statusPlaceholders)
              AND pickup_at < ?
              AND dropoff_at > ?
            GROUP BY vehicle_id
        ) bc ON bc.vehicle_id = v.id
    ";

    $params = array_merge($holdStatuses, [$dropoffAt, $pickupAt]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['id']] = (int) $row['available'];
    }

    return $out;
}
