<?php
/* Payment ledger + provider abstraction —. */

require_once __DIR__ . '/booking_rules.php';

/* HMAC secret for webhook signatures. */
function payment_webhook_secret(): string
{
    $env = getenv('PMS_PAYMENT_WEBHOOK_SECRET');
    return ($env !== false && $env !== '') ? $env : 'pms-local-dev-manual-webhook-secret';
}

function payment_config(string $key, $default = null)
{
    $envKey = 'PMS_' . strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $key));
    $env = getenv($envKey);
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $GLOBALS['config'][$key] ?? $default;
}

function payment_provider(): string
{
    return 'paymongo';
}

function payment_sign(string $payload): string
{
    return hash_hmac('sha256', $payload, payment_webhook_secret());
}

/** Constant-time signature check. Missing / empty signature always fails. */
function payment_verify_signature(string $payload, ?string $signature): bool
{
    if ($signature === null || $signature === '') {
        return false;
    }
    return hash_equals(payment_sign($payload), $signature);
}

/* Insert a pending payment row. */
function payment_create(PDO $pdo, int $bookingId, string $purpose, float $amount, string $method = 'manual'): array
{
    try {
        $ref = payment_provider() . '_' . bin2hex(random_bytes(12));
    } catch (Exception $e) {
        $ref = payment_provider() . '_' . uniqid('', true);
    }
    $stmt = $pdo->prepare(
        "INSERT INTO payments (booking_id, purpose, provider, provider_ref, method, amount, status)
         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$bookingId, $purpose, payment_provider(), $ref, $method, $amount]);
    return ['id' => (int) $pdo->lastInsertId(), 'provider_ref' => $ref];
}

/* Create one PayMongo Hosted Checkout Session for rental + deposit. */
function payment_paymongo_create_checkout(PDO $pdo, int $bookingId, string $vehicleTitle, float $rentalAmount, float $depositAmount, ?string $customerEmail = null, string $returnTo = 'web'): array
{
    $secret = trim((string) payment_config('paymongo_secret_key', ''));
    if ($secret === '') {
        throw new RuntimeException('PayMongo secret key is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for PayMongo.');
    }

    // each checkout attempt used to leave its two pending rows behind.
    $pdo->prepare("UPDATE payments SET status = 'cancelled' WHERE booking_id = ? AND provider = 'paymongo' AND status = 'pending'")
        ->execute([$bookingId]);

    $rows = [];
    foreach ([['rental', $rentalAmount], ['deposit', $depositAmount]] as [$purpose, $amount]) {
        if ($amount <= 0) {
            continue;
        }
        $rows[$purpose] = payment_create($pdo, $bookingId, $purpose, $amount, 'paymongo');
    }
    if (!$rows) {
        throw new RuntimeException('No payable amount was found.');
    }

    $baseUrl = payment_config('app_base_url', '');
    // a config copied from the dev machine keeps app_base_url on localhost.
    $configHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
    $requestHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $isLocalHost = fn(string $h) => in_array($h, ['', 'localhost', '127.0.0.1', '::1', '10.0.2.2'], true);
    if ($baseUrl !== '' && $isLocalHost($configHost) && !$isLocalHost($requestHost)) {
        $baseUrl = '';
    }
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $baseUrl = rtrim($baseUrl, '/');
    $sessionReference = 'BOOKING-' . $bookingId . '-' . bin2hex(random_bytes(4));
    $lineItems = [[
        'name' => 'Rental: ' . $vehicleTitle,
        'amount' => (int) round($rentalAmount * 100),
        'currency' => 'PHP',
        'quantity' => 1,
    ]];
    if ($depositAmount > 0) {
        $lineItems[] = [
            'name' => 'Refundable security deposit',
            'amount' => (int) round($depositAmount * 100),
            'currency' => 'PHP',
            'quantity' => 1,
        ];
    }

    $methods = payment_config('paymongo_payment_methods', ['card', 'gcash', 'paymaya', 'grab_pay', 'qrph']);
    if (is_string($methods)) {
        $methods = array_values(array_filter(array_map('trim', explode(',', $methods))));
    }
    $payload = json_encode(['data' => ['attributes' => [
        'line_items' => $lineItems,
        'payment_method_types' => array_values($methods),
        'description' => 'PMS booking #' . $bookingId,
        'reference_number' => $sessionReference,
        'customer_email' => $customerEmail ?: null,
        // App checkouts return via payment_return.php; the website keeps transactions.php.
        'success_url' => $baseUrl . ($returnTo === 'app' ? '/payment_return.php' : '/transactions.php') . '?payment=success&booking_id=' . $bookingId,
        'cancel_url' => $baseUrl . ($returnTo === 'app' ? '/payment_return.php' : '/transactions.php') . '?payment=cancelled&booking_id=' . $bookingId,
    ]]], JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.paymongo.com/v2/checkout_sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($response === false || $curlError !== '') {
        throw new RuntimeException('PayMongo request failed.');
    }
    $decoded = json_decode($response, true);
    $sessionId = $decoded['data']['id'] ?? '';
    $checkoutUrl = $decoded['data']['attributes']['checkout_url'] ?? '';
    if ($status < 200 || $status >= 300 || $sessionId === '' || $checkoutUrl === '') {
        throw new RuntimeException('PayMongo rejected the checkout request.');
    }

    foreach ($rows as $purpose => $row) {
        $pdo->prepare("UPDATE payments SET provider_ref = ?, raw_response = ? WHERE id = ?")
            ->execute([$sessionId . ':' . $purpose, json_encode(['checkout_session_id' => $sessionId, 'reference_number' => $sessionReference]), $row['id']]);
    }
    return ['checkout_url' => $checkoutUrl, 'session_id' => $sessionId];
}

/** Tell the customer + admin a booking has been paid (shared by webhook and sync). */
function payment_notify_paid(PDO $pdo, int $bookingId): void
{
    require_once __DIR__ . '/notify.php';
    $bk = $pdo->prepare("SELECT b.user_id, v.title FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id WHERE b.id = ?");
    $bk->execute([$bookingId]);
    $bk = $bk->fetch(PDO::FETCH_ASSOC);
    if (!$bk) {
        return;
    }
    notify_user($pdo, (int) $bk['user_id'], 'payment_received',
        'Payment confirmed: ' . $bk['title'],
        'We received your payment. Your booking is confirmed and awaiting pickup.',
        'transactions.php', $bookingId);
    notify_admin($pdo, 'payment_received',
        'Payment received: ' . $bk['title'],
        'Booking #' . $bookingId . ' paid.',
        'admin_transaction_detail.php?id=' . $bookingId,
        $bookingId);
}

/* Ask PayMongo directly whether this booking's checkout was paid, and settle it if so. */
function payment_sync_paymongo(PDO $pdo, int $bookingId): bool
{
    try {
        $secret = trim((string) payment_config('paymongo_secret_key', ''));
        if ($secret === '' || !function_exists('curl_init')) {
            return false;
        }
        // Include 'cancelled' rows: a superseded checkout link can still be paid.
        $stmt = $pdo->prepare(
            "SELECT SUBSTRING_INDEX(provider_ref, ':', 1) AS sid
             FROM payments
             WHERE booking_id = ? AND provider = 'paymongo' AND status IN ('pending','cancelled')
               AND provider_ref LIKE 'cs\_%'
             GROUP BY sid ORDER BY MAX(id) DESC LIMIT 5"
        );
        $stmt->execute([$bookingId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($sid));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $secret . ':',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $attrs = json_decode((string) $response, true)['data']['attributes'] ?? null;
            if ($code !== 200 || !is_array($attrs)) {
                continue;
            }
            $paid = ($attrs['payment_intent']['attributes']['status'] ?? '') === 'succeeded';
            foreach ($attrs['payments'] ?? [] as $p) {
                if (($p['attributes']['status'] ?? '') === 'paid') {
                    $paid = true;
                }
            }
            if (!$paid) {
                continue;
            }
            $pdo->beginTransaction();
            payment_apply_paymongo_event($pdo, ['data' => ['attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => ['id' => $sid, 'attributes' => $attrs],
            ]]]);
            $moved = payment_reconcile_booking($pdo, $bookingId, 'system', null);
            if ($moved) {
                payment_notify_paid($pdo, $bookingId);
            }
            $pdo->commit();
            return $moved;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    return false;
}

/** Verify PayMongo's `t=...,te=...,li=...` webhook signature format. */
function payment_verify_paymongo_signature(string $payload, ?string $signature): bool
{
    $secret = trim((string) payment_config('paymongo_webhook_secret', ''));
    if ($secret === '' || !$signature) {
        return false;
    }
    $parts = [];
    foreach (explode(',', $signature) as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        $parts[$key] = $value;
    }
    $timestamp = $parts['t'] ?? '';
    $testSignature = $parts['te'] ?? '';
    $liveSignature = $parts['li'] ?? '';
    if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    $provided = ($parts['livemode'] ?? '') === 'true' ? $liveSignature : $testSignature;
    return $provided !== '' && hash_equals($expected, $provided);
}

/** Apply checkout_session.payment.paid to both rental and deposit ledger rows. */
function payment_apply_paymongo_event(PDO $pdo, array $event): array
{
    $attributes = $event['data']['attributes'] ?? [];
    $eventType = $attributes['type'] ?? '';
    $session = $attributes['data'] ?? [];
    $sessionId = (string) ($session['id'] ?? '');
    if ($eventType !== 'checkout_session.payment.paid' || $sessionId === '') {
        return ['applied' => false, 'payment' => null, 'error' => 'unsupported PayMongo event'];
    }
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE provider = 'paymongo' AND provider_ref LIKE ? FOR UPDATE");
    $stmt->execute([$sessionId . ':%']);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$payments) {
        return ['applied' => false, 'payment' => null, 'error' => 'unknown checkout session'];
    }
    $eventJson = json_encode($event);
    // Record how the customer actually paid instead of leaving the 'paymongo' placeholder.
    $sessionAttrs = $session['attributes'] ?? [];
    $usedMethod = (string) ($sessionAttrs['payment_method_used']
        ?? ($sessionAttrs['payments'][0]['attributes']['source']['type'] ?? ''));
    $usedMethod = preg_match('/^[a-z_]{2,30}$/', $usedMethod) ? $usedMethod : '';
    foreach ($payments as $payment) {
        if ($payment['status'] !== 'succeeded') {
            $pdo->prepare("UPDATE payments SET status = 'succeeded', paid_at = NOW(), raw_response = ?, method = COALESCE(NULLIF(?, ''), method) WHERE id = ?")
                ->execute([$eventJson, $usedMethod, $payment['id']]);
        }
    }
    $payments[0]['status'] = 'succeeded';
    $payments[0]['provider_ref'] = $sessionId;
    return ['applied' => true, 'payment' => $payments[0], 'error' => null];
}

/* The manual provider "processing" a payment: build the signed event a gateway would POST to payment_webhook.php. */
function payment_manual_build_event(string $providerRef, string $status = 'succeeded'): array
{
    $payload = json_encode([
        'provider'     => payment_provider(),
        'provider_ref' => $providerRef,
        'status'       => $status,
        'at'           => gmdate('c'),
    ]);
    return ['payload' => $payload, 'signature' => payment_sign($payload)];
}

/* Apply a webhook event to the ledger. */
function payment_apply_event(PDO $pdo, array $event): array
{
    $ref = (string) ($event['provider_ref'] ?? '');
    $newStatus = (string) ($event['status'] ?? '');
    if ($ref === '' || !in_array($newStatus, ['succeeded', 'failed'], true)) {
        return ['applied' => false, 'payment' => null, 'error' => 'malformed event'];
    }

    $stmt = $pdo->prepare("SELECT * FROM payments WHERE provider = ? AND provider_ref = ? FOR UPDATE");
    $stmt->execute([payment_provider(), $ref]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) {
        return ['applied' => false, 'payment' => null, 'error' => 'unknown payment reference'];
    }

    if ($payment['status'] === $newStatus) {
        return ['applied' => false, 'payment' => $payment, 'error' => null]; // idempotent
    }

    $paidAt = $newStatus === 'succeeded' ? date('Y-m-d H:i:s') : null;
    $pdo->prepare("UPDATE payments SET status = ?, paid_at = ?, raw_response = ? WHERE id = ?")
        ->execute([$newStatus, $paidAt, json_encode($event), $payment['id']]);

    $payment['status'] = $newStatus;
    $payment['paid_at'] = $paidAt;
    return ['applied' => true, 'payment' => $payment, 'error' => null];
}

/* Push a booking to 'paid' once its rental payment has succeeded. */
function payment_reconcile_booking(PDO $pdo, int $bookingId, string $actorType = 'system', ?int $actorId = null): bool
{
    $b = $pdo->prepare("SELECT id, status, security_deposit FROM bookings WHERE id = ? FOR UPDATE");
    $b->execute([$bookingId]);
    $booking = $b->fetch(PDO::FETCH_ASSOC);
    if (!$booking || $booking['status'] !== 'approved') {
        return false;
    }

    $rentalOk = (int) $pdo->query(
        "SELECT COUNT(*) FROM payments WHERE booking_id = " . (int) $bookingId
        . " AND purpose = 'rental' AND status = 'succeeded'"
    )->fetchColumn() > 0;

    $depositExpected = (float) $booking['security_deposit'] > 0;
    $depositOk = !$depositExpected || (int) $pdo->query(
        "SELECT COUNT(*) FROM payments WHERE booking_id = " . (int) $bookingId
        . " AND purpose = 'deposit' AND status = 'succeeded'"
    )->fetchColumn() > 0;

    if (!$rentalOk || !$depositOk) {
        return false;
    }

    $collected = (float) $pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = " . (int) $bookingId
        . " AND status = 'succeeded' AND purpose IN ('rental','deposit')"
    )->fetchColumn();

    $pdo->prepare(
        "UPDATE bookings
         SET status = 'paid', payment_status = 'paid',
             deposit_status = CASE WHEN security_deposit > 0 THEN 'held' ELSE deposit_status END,
             paid = ?
         WHERE id = ?"
    )->execute([(int) round($collected), $bookingId]);

    $pdo->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, 'approved', 'paid', ?, ?, 'Payment received (rental + deposit).')"
    )->execute([$bookingId, $actorType, $actorId]);

    return true;
}

/** Customer-facing name for a payments.method value (gcash -> GCash). */
function payment_method_label(string $method): string
{
    $map = ['gcash' => 'GCash', 'paymaya' => 'Maya', 'maya' => 'Maya', 'grab_pay' => 'GrabPay', 'card' => 'Card', 'qrph' => 'QR Ph'];
    return $map[$method] ?? ucwords(str_replace('_', ' ', $method));
}

/** Every payment row for a booking, newest first (for the receipt / admin detail). */
function payment_list_for_booking(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare(
        "SELECT purpose, provider, provider_ref, method, amount, status, paid_at, created_at
         FROM payments WHERE booking_id = ? ORDER BY id DESC"
    );
    $stmt->execute([$bookingId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Provider_ref is "<checkout session>:<purpose>" for PayMongo rows.
    foreach ($rows as &$row) {
        $row['reference'] = $row['provider_ref'] === null ? null : explode(':', $row['provider_ref'])[0];
        unset($row['provider_ref']);
    }
    unset($row);
    return $rows;
}

/* Lazy payment-window expiry: an 'approved' booking left unpaid for 48h after approved_at auto-cancels on the next read. */
function payment_expire_if_stale(PDO $pdo, array $booking, ?string $now = null): bool
{
    if (($booking['status'] ?? '') !== 'approved'
        || ($booking['payment_status'] ?? '') === 'paid'
        || empty($booking['approved_at'])) {
        return false;
    }

    $reference = $now !== null ? new DateTime($now) : new DateTime();
    $deadline = (new DateTime($booking['approved_at']))->modify('+48 hours');
    if ($reference < $deadline) {
        return false;
    }

    $id = (int) $booking['id'];
    $pdo->beginTransaction();
    try {
        // Re-check under lock — another request may have paid or expired it.
        $chk = $pdo->prepare("SELECT status, payment_status FROM bookings WHERE id = ? FOR UPDATE");
        $chk->execute([$id]);
        $cur = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$cur || $cur['status'] !== 'approved' || $cur['payment_status'] === 'paid') {
            $pdo->commit();
            return false;
        }

        $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(),
                       cancel_reason = 'Payment window (48h) expired.' WHERE id = ?")
            ->execute([$id]);
        $pdo->prepare(
            "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
             VALUES (?, 'approved', 'cancelled', 'system', NULL, 'Payment window (48h) expired.')"
        )->execute([$id]);

        if (function_exists('notify_user') && !empty($booking['user_id'])) {
            notify_user($pdo, (int) $booking['user_id'], 'payment_expired',
                'Booking cancelled — payment window expired',
                'Your approved booking was not paid within 48 hours and has been cancelled. You can book again anytime.',
                'transactions.php', $id);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/* Build the settlement proposal for a returned booking — the read-only "system proposes" half of work. */
function payment_build_settlement_proposal(PDO $pdo, array $booking, ?array $inspection, array $damages): array
{
    $dailyRate = (float) ($booking['rate'] ?? 0);
    $overtime = booking_calculate_overtime((string) $booking['dropoff_at'], (string) $booking['actual_return_at'], $dailyRate);

    $fuelRate = booking_fuel_rate_for_category($pdo, (string) ($booking['vehicle_category'] ?? ''));
    $fuelLevelOut = $booking['fuel_level_out'] ?? null;
    $fuelLevelIn = $inspection['fuel_level_in'] ?? null;
    $fuelCharge = null;
    if ($fuelRate !== null && $fuelLevelOut !== null && $fuelLevelIn !== null) {
        $fuelCharge = booking_calculate_fuel_charge((int) $fuelLevelOut, (int) $fuelLevelIn, $fuelRate);
    }

    $damageTotal = 0.0;
    foreach ($damages as $damage) {
        if ($damage['status'] === 'waived') {
            continue;
        }
        $damageTotal += (float) ($damage['adjusted_amount'] ?? $damage['estimated_amount']);
    }
    $damageTotal = round($damageTotal, 2);

    $totalDeductions = round($overtime + ($fuelCharge ?? 0.0) + $damageTotal, 2);
    $deposit = (float) ($booking['security_deposit'] ?? 0);
    $out = payment_settlement_outcome($totalDeductions, $deposit);

    return [
        'overtime'             => $overtime,
        'fuel_rate'            => $fuelRate,
        'fuel_charge'          => $fuelCharge,
        'fuel_pending'         => $fuelCharge === null,
        'damage_total'         => $damageTotal,
        'total_deductions'     => $totalDeductions,
        'deposit'              => $deposit,
        'outcome'              => $out['outcome'],
        'refund_amount'        => $out['refund_amount'],
        'outstanding_balance'  => $out['outstanding_balance'],
    ];
}

/* The settlement outcome for a total deduction against a deposit. */
function payment_settlement_outcome(float $totalDeductions, float $deposit): array
{
    if ($totalDeductions <= 0.0) {
        return ['outcome' => 'refund', 'refund_amount' => $deposit, 'outstanding_balance' => 0.0];
    }
    if ($totalDeductions < $deposit) {
        return ['outcome' => 'partial_refund', 'refund_amount' => round($deposit - $totalDeductions, 2), 'outstanding_balance' => 0.0];
    }
    return ['outcome' => 'forfeit', 'refund_amount' => 0.0, 'outstanding_balance' => round($totalDeductions - $deposit, 2)];
}

/* Finalize the settlement for a 'returned' booking. */
function settle_deposit(PDO $pdo, int $bookingId, array $lines, int $adminId): array
{
    $damageTotal = 0.0;
    foreach ($lines['damages'] as $d) {
        if ($d['waived']) {
            $pdo->prepare("UPDATE booking_damages SET status = 'waived', adjustment_reason = ? WHERE id = ? AND booking_id = ?")
                ->execute([$d['reason'], $d['id'], $bookingId]);
            continue;
        }
        $pdo->prepare("UPDATE booking_damages SET status = 'confirmed', adjusted_amount = ?, adjustment_reason = ? WHERE id = ? AND booking_id = ?")
            ->execute([$d['final_amount'], $d['reason'], $d['id'], $bookingId]);
        $damageTotal += $d['final_amount'];
    }
    $damageTotal = round($damageTotal, 2);

    $totalDeductions = round($lines['overtime'] + $lines['fuel_charge'] + $damageTotal, 2);

    $depositStmt = $pdo->prepare("SELECT security_deposit FROM bookings WHERE id = ? FOR UPDATE");
    $depositStmt->execute([$bookingId]);
    $deposit = (float) $depositStmt->fetchColumn();
    $out = payment_settlement_outcome($totalDeductions, $deposit);

    $depositStatusMap = ['refund' => 'refunded', 'partial_refund' => 'partially_refunded', 'forfeit' => 'forfeited'];

    $pdo->prepare(
        "UPDATE bookings SET
            status = 'completed',
            overtime_amount = ?, overtime_adjustment_reason = ?,
            fuel_charge_amount = ?, fuel_adjustment_reason = ?,
            deposit_status = ?, deposit_refund_amount = ?, deposit_settled_at = NOW(),
            outstanding_balance = ?
         WHERE id = ?"
    )->execute([
        $lines['overtime'], $lines['overtime_reason'],
        $lines['fuel_charge'], $lines['fuel_reason'],
        $depositStatusMap[$out['outcome']], $out['refund_amount'],
        $out['outstanding_balance'],
        $bookingId,
    ]);

    $pdo->prepare("UPDATE return_inspections SET status = 'finalized', finalized_at = NOW() WHERE booking_id = ?")
        ->execute([$bookingId]);

    $pdo->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, 'returned', 'completed', 'admin', ?, ?)"
    )->execute([
        $bookingId, $adminId,
        'Settlement finalized: ' . $out['outcome'] . ', total deductions ₱' . number_format($totalDeductions, 2) . '.',
    ]);

    if ($out['refund_amount'] > 0) {
        $refundPayment = payment_create($pdo, $bookingId, 'refund', $out['refund_amount'], 'manual');
        $event = payment_manual_build_event($refundPayment['provider_ref'], 'succeeded');
        payment_apply_event($pdo, json_decode($event['payload'], true));
    }

    return [
        'outcome'              => $out['outcome'],
        'total_deductions'     => $totalDeductions,
        'refund_amount'        => $out['refund_amount'],
        'outstanding_balance'  => $out['outstanding_balance'],
    ];
}
