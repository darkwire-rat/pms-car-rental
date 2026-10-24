<?php
// The admin needs something more from the customer before approving or declining.
session_start();
require 'db.php'; // $pdo
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/messaging.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_require();

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking id']);
    exit;
}

$bookingId = (int) $_POST['id'];
$adminId   = (int) $_SESSION['admin_id'];
$message   = trim($_POST['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Describe what the customer needs to provide.']);
    exit;
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT b.status, b.user_id, b.booking_ref, v.title AS vehicle_title
        FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    $current = $booking['status'];

    if (!in_array($current, ['pending_review', 'info_requested'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This booking is not awaiting review (current status: ' . booking_status_label($current) . ').']);
        exit;
    }

    if ($current !== 'info_requested') {
        $pdo->prepare("UPDATE bookings SET status = 'info_requested' WHERE id = ? LIMIT 1")
            ->execute([$bookingId]);
    }

    $reason = mb_substr('Information requested: ' . $message, 0, 255);
    $pdo->prepare(
        "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by_type, changed_by_id, reason)
         VALUES (?, ?, 'info_requested', 'admin', ?, ?)"
    )->execute([$bookingId, $current, $adminId, $reason]);

    // Booking-scoped conversation — the note becomes its first message.
    $subject = 'Booking ' . ($booking['booking_ref'] ?: ('#' . $bookingId)) . ' — ' . $booking['vehicle_title'];
    $conversationId = messaging_get_or_open_booking_conversation($pdo, (int) $booking['user_id'], $bookingId, $subject);
    messaging_post_message($pdo, $conversationId, 'admin', $adminId, $message);

    notify_user(
        $pdo,
        (int) $booking['user_id'],
        'info_requested',
        'Information needed: ' . $booking['vehicle_title'],
        'To review your booking for ' . $booking['vehicle_title'] . ', we need: ' . $message,
        'my_messages.php?id=' . $conversationId,
        $bookingId
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
