<?php
/* Dashboard reporting —. */

require_once __DIR__ . '/booking_status.php';
require_once __DIR__ . '/booking_rules.php';

/* Fixed categorical color per booking status. */
function reporting_status_chart_colors(): array
{
    return [
        'pending_review' => '#ffc107',
        'info_requested' => '#0dcaf0',
        'approved'       => '#0d6efd',
        'paid'           => '#6f42c1',
        'active'         => '#198754',
        'returned'       => '#6c757d',
        'completed'      => '#20c997',
        'cancelled'      => '#dc3545',
        'declined'       => '#fd7e14',
    ];
}

/* The four "right now" dashboard cards. */
function reporting_point_in_time_cards(PDO $pdo): array
{
    $totalModels = (int) $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    $totalUnits = (int) $pdo->query("SELECT COALESCE(SUM(units_total), 0) FROM vehicles")->fetchColumn();

    $now = date('Y-m-d H:i:s');
    $activeVehicles = $pdo->query("SELECT id, units_total FROM vehicles WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    $availableByVehicle = booking_available_units_batch($pdo, $now, $now);
    $availableUnits = 0;
    foreach ($activeVehicles as $v) {
        $availableUnits += max(0, min((int) $v['units_total'], $availableByVehicle[(int) $v['id']] ?? (int) $v['units_total']));
    }

    return [
        'total_models'     => $totalModels,
        'total_units'      => $totalUnits,
        'available_units'  => $availableUnits,
        'active_rentals'   => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'active'")->fetchColumn(),
        'pending_bookings' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_review'")->fetchColumn(),
        'as_of'            => $now,
    ];
}

/** Dashboard KPI sets used by the admin's compact numerical selector. */
function reporting_dashboard_metric_cards(PDO $pdo, string $set = 'fleet'): array
{
    if ($set === 'bookings') {
        return [
            ['label' => 'Total Bookings', 'value' => (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(), 'icon' => 'fa-calendar-check', 'color' => 'primary'],
            ['label' => 'Pending Review', 'value' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_review'")->fetchColumn(), 'icon' => 'fa-hourglass-half', 'color' => 'warning'],
            ['label' => 'Paid Bookings', 'value' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid'")->fetchColumn(), 'icon' => 'fa-money-check-dollar', 'color' => 'success'],
            ['label' => 'Completed Rentals', 'value' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn(), 'icon' => 'fa-circle-check', 'color' => 'info'],
        ];
    }

    if ($set === 'finance') {
        return [
            ['label' => 'Booking Value', 'value' => (float) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM bookings')->fetchColumn(), 'prefix' => '₱', 'icon' => 'fa-chart-line', 'color' => 'primary'],
            ['label' => 'Paid Value', 'value' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn(), 'prefix' => '₱', 'icon' => 'fa-money-bill-wave', 'color' => 'success'],
            ['label' => 'Average Booking', 'value' => (float) $pdo->query('SELECT COALESCE(AVG(total_amount), 0) FROM bookings')->fetchColumn(), 'prefix' => '₱', 'icon' => 'fa-calculator', 'color' => 'warning'],
            ['label' => 'Refunded Bookings', 'value' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'refunded'")->fetchColumn(), 'icon' => 'fa-rotate-left', 'color' => 'danger'],
        ];
    }

    $cards = reporting_point_in_time_cards($pdo);
    return [
        ['label' => 'Total Units', 'value' => $cards['total_units'], 'suffix' => ' (' . $cards['total_models'] . ' models)', 'icon' => 'fa-car', 'color' => 'primary'],
        ['label' => 'Available for Renting', 'value' => $cards['available_units'], 'icon' => 'fa-circle-check', 'color' => 'success'],
        ['label' => 'Active Rentals', 'value' => $cards['active_rentals'], 'icon' => 'fa-key', 'color' => 'warning'],
        ['label' => 'Pending Bookings', 'value' => $cards['pending_bookings'], 'icon' => 'fa-hourglass-half', 'color' => 'danger'],
    ];
}

/* Revenue for $months trailing months ending at $endMonth. */
function reporting_revenue_by_month(PDO $pdo, string $endMonth, int $months = 12, ?array $statuses = null): array
{
    $end = DateTime::createFromFormat('Y-m-d', $endMonth . '-01');
    $start = (clone $end)->modify('-' . ($months - 1) . ' months');
    $windowStart = $start->format('Y-m-01 00:00:00');
    $windowEnd = (clone $end)->modify('+1 month')->format('Y-m-01 00:00:00');

    $series = [];
    $cursor = clone $start;
    for ($i = 0; $i < $months; $i++) {
        $series[$cursor->format('Y-m')] = 0.0;
        $cursor->modify('+1 month');
    }

    $statusSql = '';
    $statusParams = [];
    if ($statuses) {
        $statusSql = ' AND b.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        $statusParams = $statuses;
    }

    $rentalStmt = $pdo->prepare("
        SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS ym, SUM(p.amount) AS amt
        FROM payments p JOIN bookings b ON b.id = p.booking_id
        WHERE p.purpose = 'rental' AND p.status = 'succeeded'
          AND p.paid_at >= ? AND p.paid_at < ?
          $statusSql
        GROUP BY ym
    ");
    $rentalStmt->execute(array_merge([$windowStart, $windowEnd], $statusParams));
    foreach ($rentalStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($series[$row['ym']])) {
            $series[$row['ym']] += (float) $row['amt'];
        }
    }

    $deductionStmt = $pdo->prepare("
        SELECT DATE_FORMAT(b.deposit_settled_at, '%Y-%m') AS ym,
               SUM(COALESCE(b.overtime_amount, 0) + COALESCE(b.fuel_charge_amount, 0)) AS amt
        FROM bookings b
        WHERE b.deposit_settled_at IS NOT NULL
          AND b.deposit_settled_at >= ? AND b.deposit_settled_at < ?
          $statusSql
        GROUP BY ym
    ");
    $deductionStmt->execute(array_merge([$windowStart, $windowEnd], $statusParams));
    foreach ($deductionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($series[$row['ym']])) {
            $series[$row['ym']] += (float) $row['amt'];
        }
    }

    $damageStmt = $pdo->prepare("
        SELECT DATE_FORMAT(b.deposit_settled_at, '%Y-%m') AS ym, SUM(d.adjusted_amount) AS amt
        FROM booking_damages d JOIN bookings b ON b.id = d.booking_id
        WHERE d.status = 'confirmed' AND d.adjusted_amount IS NOT NULL
          AND b.deposit_settled_at IS NOT NULL
          AND b.deposit_settled_at >= ? AND b.deposit_settled_at < ?
          $statusSql
        GROUP BY ym
    ");
    $damageStmt->execute(array_merge([$windowStart, $windowEnd], $statusParams));
    foreach ($damageStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($series[$row['ym']])) {
            $series[$row['ym']] += (float) $row['amt'];
        }
    }

    foreach ($series as &$amount) {
        $amount = round($amount, 2);
    }
    unset($amount);

    return $series;
}

/* Booking counts by status for bookings CREATED in $month — the "Bookings by Status" doughnut. */
function reporting_bookings_by_status(PDO $pdo, string $month): array
{
    $start = $month . '-01 00:00:00';
    $end = (new DateTime($start))->modify('+1 month')->format('Y-m-d H:i:s');

    $counts = array_fill_keys(booking_status_values(), 0);
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM bookings WHERE created_at >= ? AND created_at < ? GROUP BY status");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['status']] = (int) $row['cnt'];
    }

    return $counts;
}

/* Top vehicles by booking count for bookings CREATED in $month, optionally restricted to one category. */
function reporting_top_vehicles(PDO $pdo, string $month, ?string $category = null, int $limit = 10): array
{
    $start = $month . '-01 00:00:00';
    $end = (new DateTime($start))->modify('+1 month')->format('Y-m-d H:i:s');

    $categorySql = $category !== null && $category !== '' ? ' AND v.category = ?' : '';
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.category, COUNT(*) AS total
        FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.created_at >= ? AND b.created_at < ?
          $categorySql
        GROUP BY v.id, v.title, v.category
        ORDER BY total DESC, v.title ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $start, PDO::PARAM_STR);
    $stmt->bindValue(2, $end, PDO::PARAM_STR);
    $paramIndex = 3;
    if ($categorySql !== '') {
        $stmt->bindValue($paramIndex++, $category, PDO::PARAM_STR);
    }
    $stmt->bindValue($paramIndex, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
