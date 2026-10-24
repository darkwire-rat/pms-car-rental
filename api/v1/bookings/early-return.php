<?php
// Mirrors return_early.php: a customer REQUEST.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/return_flow.php';
require_once __DIR__ . '/../../../lib/notify.php';

$user = api_require_auth($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$booking_id = (int) ($data['booking_id'] ?? 0);
if (!$booking_id) {
  api_error('booking_id is required.', ['booking_id' => 'booking_id is required.'], 422);
}

try {
  $pdo->beginTransaction();

  // Ownership check first.
  $owner = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ? FOR UPDATE");
  $owner->execute([$booking_id]);
  $ownerRow = $owner->fetch();
  if (!$ownerRow || (int) $ownerRow['user_id'] !== (int) $user['id']) {
    $pdo->rollBack();
    api_error('Booking not found.', [], 404);
  }

  $result = return_flow_mark_returned($pdo, $booking_id, 'user', (int) $user['id']);
  if (!$result['ok']) {
    $pdo->rollBack();
    api_error($result['error'], [], 409);
  }
  $booking = $result['booking'];

  $veh = $pdo->prepare("SELECT title FROM vehicles WHERE id = ?");
  $veh->execute([$booking['vehicle_id']]);
  $vehicleName = $veh->fetchColumn() ?: 'your vehicle';

  $originalDropoff = new DateTime($booking['dropoff_at']);
  $actualReturn = new DateTime($booking['actual_return_at']);
  $isEarlyReturn = $actualReturn < $originalDropoff ? 1 : 0;

  // Notifications — same events return_early.php fires. "Return submitted" drives the admin inspection queue.
  notify_admin(
    $pdo,
    'return_submitted',
    'Return requested: ' . $vehicleName,
    sprintf('%s requested the return of %s (booking #%d) via API%s. Awaiting inspection.',
      $user['name'] ?? ('Customer #' . (int) $user['id']),
      $vehicleName, $booking_id,
      $isEarlyReturn ? ' early' : ''),
    'admin_transaction_detail.php?id=' . $booking_id,
    $booking_id
  );
  notify_user(
    $pdo,
    (int) $user['id'],
    'return_processed',
    'Return recorded: ' . $vehicleName,
    'We recorded your return of ' . $vehicleName . '. An admin will inspect it and settle your security deposit shortly.',
    'transactions.php',
    $booking_id
  );

  $pdo->commit();

  api_success([
    'booking_id' => (int) $booking_id,
    'booking_ref' => $booking['booking_ref'],
    'vehicle_name' => $vehicleName,
    'rental_date' => $booking['rental_date'],
    'return_date' => $booking['return_date'],
    'status' => 'returned',
    'actual_return_date' => $booking['actual_return_at'],
    'early_return' => $isEarlyReturn,
  ], 'Return recorded — awaiting inspection.');
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  api_error($e->getMessage(), [], 400);
}
