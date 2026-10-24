<?php
/* Booking status vocabulary. */

/** All valid bookings.status values, in workflow order. */
function booking_status_values(): array
{
    return [
        'pending_review', 'info_requested', 'approved', 'paid',
        'active', 'returned', 'completed', 'cancelled', 'declined',
    ];
}

/* Statuses that hold a unit of vehicle inventory. 'pending_review' and 'info_requested' deliberately do NOT hold inventory — a. */
function booking_status_holds_inventory(): array
{
    return ['approved', 'paid', 'active', 'returned'];
}

/* Whether a booking in this status may be hard-deleted from the admin transaction list. */
function booking_status_can_hard_delete(string $status): bool
{
    return in_array($status, ['pending_review', 'info_requested', 'cancelled', 'declined'], true);
}

/* The admin actions available from the transaction detail page for a booking in this status. */
function booking_status_available_actions(string $status): array
{
    $map = [
        'pending_review' => ['approve', 'decline', 'request_info'],
        'info_requested' => ['approve', 'decline', 'request_info'],
        'approved'       => ['record_payment', 'cancel'],
        'paid'           => ['mark_pickup', 'cancel'],
        'active'         => ['mark_return'],
        'returned'       => ['record_inspection', 'finalize_settlement'],
        'completed'      => [],
        'cancelled'      => [],
        'declined'       => [],
    ];

    return $map[$status] ?? [];
}

/** Human-readable label for a status value. */
function booking_status_label(string $status): string
{
    $labels = [
        'pending_review' => 'Pending Review',
        'info_requested' => 'Info Requested',
        'approved'       => 'Approved',
        'paid'           => 'Paid',
        'active'         => 'Active',
        'returned'       => 'Returned',
        'completed'      => 'Completed',
        'cancelled'      => 'Cancelled',
        'declined'       => 'Declined',
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/** Bootstrap badge class for a status value — one mapping, used everywhere a status renders. */
function booking_status_badge_class(string $status): string
{
    $classes = [
        'pending_review' => 'bg-warning text-body',
        'info_requested' => 'bg-info text-body',
        'approved'       => 'bg-primary',
        'paid'           => 'bg-primary',
        'active'         => 'bg-success',
        'returned'       => 'bg-secondary',
        'completed'      => 'bg-success',
        'cancelled'      => 'bg-danger',
        'declined'       => 'bg-danger',
    ];

    return $classes[$status] ?? 'bg-secondary';
}

/** Bootstrap badge class for a payment_status value. */
function booking_payment_status_badge_class(string $paymentStatus): string
{
    $classes = [
        'unpaid'         => 'bg-danger',
        'partially_paid' => 'bg-warning text-body',
        'paid'           => 'bg-success',
        'refunded'       => 'bg-secondary',
    ];

    return $classes[$paymentStatus] ?? 'bg-secondary';
}

/* True if $booking is overdue right now. */
function booking_is_overdue(array $booking, ?string $now = null): bool
{
    if (($booking['status'] ?? null) !== 'active') {
        return false;
    }
    if (empty($booking['dropoff_at'])) {
        return false;
    }

    $reference = $now !== null ? new DateTime($now) : new DateTime();
    $dropoff = new DateTime($booking['dropoff_at']);

    return $reference > $dropoff;
}

/* True if $booking is awaiting payment right now. */
function booking_is_payment_pending(array $booking): bool
{
    return ($booking['status'] ?? null) === 'approved'
        && ($booking['payment_status'] ?? 'unpaid') === 'unpaid';
}
