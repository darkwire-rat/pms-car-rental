<?php
// One page that shows everything about a booking, and the place approval now happens.
session_start();
require 'db.php'; // $pdo
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/booking_rules.php';
require_once __DIR__ . '/lib/messaging.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/return_flow.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$bookingId = (int) ($_GET['id'] ?? 0);
$csrfToken = csrf_token();

// P8: lazily expire this booking if its 48h payment window has passed.
$pre = $pdo->prepare("SELECT id, user_id, status, payment_status, approved_at FROM bookings WHERE id = ?");
$pre->execute([$bookingId]);
if ($preRow = $pre->fetch(PDO::FETCH_ASSOC)) {
    payment_expire_if_stale($pdo, $preRow);
}

$stmt = $pdo->prepare("
    SELECT b.*, u.name AS user_name, u.email AS user_email,
           v.title AS vehicle_name, v.category AS vehicle_category, v.price_per_day,
           pl.name AS pickup_location_name, dl.name AS dropoff_location_name,
           t.transaction_ref, t.amount AS transaction_amount, t.paid_at AS transaction_paid_at
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    LEFT JOIN locations pl ON pl.id = b.pickup_location_id
    LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
    LEFT JOIN transactions t ON t.booking_id = b.id
    WHERE b.id = ?
    ORDER BY t.id DESC
    LIMIT 1
");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

$history = [];
if ($booking) {
    $h = $pdo->prepare("
        SELECT from_status, to_status, changed_by_type, changed_by_id, reason, created_at
        FROM booking_status_history
        WHERE booking_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $h->execute([$bookingId]);
    $history = $h->fetchAll();
}

// Booking-scoped conversation (Phase 6), if one has been opened.
$conversation = null;
if ($booking) {
    $c = $pdo->prepare("
        SELECT c.id, c.last_message_at,
               (SELECT COUNT(*) FROM conversation_messages m
                WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.read_at IS NULL) AS unread,
               (SELECT m2.body FROM conversation_messages m2
                WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_body
        FROM conversations c
        WHERE c.booking_id = ?
        ORDER BY c.id DESC LIMIT 1
    ");
    $c->execute([$bookingId]);
    $conversation = $c->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Phase 9: the payment ledger for this booking.
$payments = $booking ? payment_list_for_booking($pdo, $bookingId) : [];

// Phase 10: the draft (or finalized) return inspection, if one exists yet.
$inspection = $booking ? return_flow_get_inspection($pdo, $bookingId) : null;

// Phase 11: damage entries reported against this booking (each with its photos).
$damages = $booking ? return_flow_list_damages($pdo, $bookingId) : [];

// Read-only settlement preview, recomputed on every load from the data above.
$proposal = ($booking && $inspection) ? payment_build_settlement_proposal($pdo, $booking, $inspection, $damages) : null;

// Derived values (never stored — Analysis sec 7.3).
$isOverdue = $booking ? booking_is_overdue($booking) : false;
if ($isOverdue) {
    // Lazy, at-most-once overdue notification — checked on read, same pattern as payment_expire_if_stale.
    return_flow_notify_overdue_once($pdo, array_merge($booking, ['vehicle_title' => $booking['vehicle_name']]));
}
$durationLabel = $booking ? booking_format_duration(booking_duration_hours($booking['pickup_at'], $booking['dropoff_at'])) : '';
$billableDays = $booking ? booking_billable_days($booking['pickup_at'], $booking['dropoff_at']) : 0;
$actions = $booking ? booking_status_available_actions($booking['status']) : [];
$pickupWindow = $booking ? return_flow_pickup_window($booking) : ['allowed' => false, 'error' => null];

function fmt_dt(?string $v): string
{
    if (!$v || $v === '0000-00-00 00:00:00') {
        return '—';
    }
    return date('M j, Y · g:i A', strtotime($v));
}

// Human phase note for actions not yet wired (Analysis sec 5.5).
$actionPhase = [
    'cancel' => 'Admin-side cancellation arrives in a later phase.',
];
$canRecordPayment = $booking
    && $booking['status'] === 'approved'
    && ($booking['payment_status'] ?? 'unpaid') !== 'paid';
?>
<!doctype html>
<html lang="en">
<head>
  <script>
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <title>PMS — Booking <?php echo $booking ? '#' . (int) $booking['id'] : 'Not Found'; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">

<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
  <?php $topbarTitle = 'Booking Detail'; $topbarBreadcrumb = 'Transactions / Booking Detail'; include 'includes/admin_topbar.php'; ?>

  <div class="container-fluid py-4">
    <a href="view-all-data.php" class="btn btn-sm btn-outline-secondary mb-3">
      <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Back to Transactions
    </a>

    <?php if (!$booking): ?>
      <div class="alert alert-warning">Booking #<?php echo (int) $bookingId; ?> was not found.</div>
    <?php else: ?>

    <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="pageAlert">
      <div class="d-flex justify-content-between align-items-start">
        <span class="js-modal-alert-text"></span>
        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
      </div>
    </div>

    <!-- Header -->
    <div class="card shadow-sm border-0 mb-4" id="transactionDetail"
         data-id="<?php echo (int) $booking['id']; ?>"
         data-customer="<?php echo htmlspecialchars($booking['user_name'], ENT_QUOTES); ?>"
         data-vehicle="<?php echo htmlspecialchars($booking['vehicle_name'], ENT_QUOTES); ?>"
         data-pickup="<?php echo htmlspecialchars(fmt_dt($booking['pickup_at']), ENT_QUOTES); ?>"
         data-dropoff="<?php echo htmlspecialchars(fmt_dt($booking['dropoff_at']), ENT_QUOTES); ?>"
         data-amount="<?php echo htmlspecialchars(number_format((float) $booking['total_amount'], 2), ENT_QUOTES); ?>">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
          <div>
            <h2 class="h4 mb-1">
              Booking #<?php echo (int) $booking['id']; ?>
              <?php if (!empty($booking['booking_ref'])): ?>
                <span class="text-body-secondary fs-6">(<?php echo htmlspecialchars($booking['booking_ref']); ?>)</span>
              <?php endif; ?>
            </h2>
            <p class="text-body-secondary mb-0 small">Submitted <?php echo fmt_dt($booking['created_at']); ?></p>
          </div>
          <div class="text-end">
            <span class="badge <?php echo booking_status_badge_class($booking['status']); ?>">
              <?php echo htmlspecialchars(booking_status_label($booking['status'])); ?>
            </span>
            <span class="badge <?php echo booking_payment_status_badge_class($booking['payment_status']); ?>">
              Payment: <?php echo htmlspecialchars(ucfirst($booking['payment_status'])); ?>
            </span>
            <?php if ($isOverdue): ?>
              <span class="badge bg-danger">Overdue</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Actions</h3></div>
      <div class="card-body">
        <?php if (in_array('approve', $actions, true)): ?>
          <button type="button" class="btn btn-success me-2 mb-2" id="btnApproveBooking">
            <i class="fas fa-check me-1" aria-hidden="true"></i> Approve
          </button>
          <button type="button" class="btn btn-outline-danger me-2 mb-2" data-bs-toggle="modal" data-bs-target="#declineModal">
            <i class="fas fa-xmark me-1" aria-hidden="true"></i> Decline
          </button>
          <button type="button" class="btn btn-outline-primary me-2 mb-2" data-bs-toggle="modal" data-bs-target="#requestInfoModal">
            <i class="fas fa-circle-question me-1" aria-hidden="true"></i> Request information
          </button>
        <?php endif; ?>

        <?php if ($canRecordPayment): ?>
          <button type="button" class="btn btn-success me-2 mb-2" id="btnRecordPayment"
                  data-total="<?php echo number_format((float) $booking['total_amount'], 2, '.', ''); ?>"
                  data-deposit="<?php echo number_format((float) $booking['security_deposit'], 2, '.', ''); ?>">
            <i class="fas fa-money-check-dollar me-1" aria-hidden="true"></i>
            Record payment (₱<?php echo number_format((float) $booking['total_amount'] + (float) $booking['security_deposit'], 2); ?>)
          </button>
          <p class="text-body-secondary small mb-0">Simulated (manual) provider — records the rental fee + refundable deposit as received.</p>
        <?php endif; ?>

        <?php if (in_array('mark_pickup', $actions, true)): ?>
          <button type="button" class="btn btn-success me-2 mb-2" data-bs-toggle="modal" data-bs-target="#markPickupModal"
                  <?php echo !$pickupWindow['allowed'] ? 'disabled' : ''; ?>
                  title="<?php echo htmlspecialchars($pickupWindow['error'] ?? '', ENT_QUOTES); ?>">
            <i class="fas fa-key me-1" aria-hidden="true"></i> Mark picked up
          </button>
          <?php if ($pickupWindow['allowed']): ?>
            <p class="text-body-secondary small mb-0">Records the pickup timestamp and the fuel level given to the customer.</p>
          <?php else: ?>
            <p class="text-danger small mb-0"><i class="fas fa-clock me-1" aria-hidden="true"></i><?php echo htmlspecialchars($pickupWindow['error']); ?></p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('mark_return', $actions, true)): ?>
          <button type="button" class="btn btn-success me-2 mb-2" id="btnMarkReturn">
            <i class="fas fa-flag-checkered me-1" aria-hidden="true"></i> Mark returned
          </button>
          <p class="text-body-secondary small mb-0">Records the vehicle as returned now, then opens the inspection.</p>
        <?php endif; ?>

        <?php if (in_array('record_inspection', $actions, true)): ?>
          <a href="#inspection" class="btn btn-primary me-2 mb-2">
            <i class="fas fa-clipboard-check me-1" aria-hidden="true"></i>
            <?php echo ($inspection && $inspection['status'] === 'finalized') ? 'View inspection' : 'Return inspection'; ?>
          </a>
          <?php if ($inspection && $inspection['status'] === 'draft'): ?>
            <span class="badge bg-warning text-body">Draft saved</span>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('finalize_settlement', $actions, true)): ?>
          <a href="#settlement" class="btn btn-outline-primary me-2 mb-2">
            <i class="fas fa-file-invoice-dollar me-1" aria-hidden="true"></i>
            <?php echo ($inspection && $inspection['status'] === 'finalized') ? 'View settlement' : 'Finalize settlement'; ?>
          </a>
        <?php endif; ?>

        <?php
        $pendingActions = array_values(array_filter($actions, fn($a) => isset($actionPhase[$a])));
        if ($pendingActions):
        ?>
          <div class="<?php echo in_array('approve', $actions, true) ? 'mt-3' : ''; ?>">
            <?php foreach ($pendingActions as $a): ?>
              <button type="button" class="btn btn-outline-secondary me-2 mb-2" disabled
                      title="<?php echo htmlspecialchars($actionPhase[$a], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $a))); ?>
              </button>
            <?php endforeach; ?>
            <p class="text-body-secondary small mb-0">Later-phase actions — not yet available.</p>
          </div>
        <?php endif; ?>

        <?php if (!$actions): ?>
          <p class="text-body-secondary mb-0">This booking is <?php echo htmlspecialchars(booking_status_label($booking['status'])); ?> — no further action.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="row g-4">
      <!-- Customer -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Customer</h3></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?php echo htmlspecialchars($booking['user_name']); ?></dd>
              <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php echo htmlspecialchars($booking['user_email']); ?></dd>
              <dt class="col-sm-4">Contact</dt><dd class="col-sm-8"><?php echo htmlspecialchars($booking['contact_number'] ?: '—'); ?></dd>
              <dt class="col-sm-4">Age given</dt><dd class="col-sm-8"><?php echo (int) $booking['age']; ?></dd>
              <dt class="col-sm-4">Address</dt><dd class="col-sm-8 text-body-secondary">Collected from Phase 7.</dd>
            </dl>
          </div>
        </div>
      </div>

      <!-- Rental -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Rental</h3></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4">Vehicle</dt><dd class="col-sm-8"><?php echo htmlspecialchars($booking['vehicle_name']); ?> <span class="text-body-secondary">(<?php echo htmlspecialchars($booking['vehicle_category']); ?>)</span></dd>
              <dt class="col-sm-4">Pick-up</dt><dd class="col-sm-8"><?php echo fmt_dt($booking['pickup_at']); ?></dd>
              <dt class="col-sm-4">Drop-off</dt><dd class="col-sm-8"><?php echo fmt_dt($booking['dropoff_at']); ?></dd>
              <dt class="col-sm-4">Duration</dt><dd class="col-sm-8"><?php echo htmlspecialchars($durationLabel); ?> · <?php echo (int) $billableDays; ?> billable day<?php echo $billableDays === 1 ? '' : 's'; ?></dd>
              <dt class="col-sm-4">Picked up</dt>
              <dd class="col-sm-8"><?php echo fmt_dt($booking['picked_up_at']); ?>
                <?php if ($booking['fuel_level_out'] !== null): ?><span class="text-body-secondary">· fuel out <?php echo (int) $booking['fuel_level_out']; ?>/8</span><?php endif; ?>
              </dd>
              <dt class="col-sm-4">Returned</dt>
              <dd class="col-sm-8"><?php echo fmt_dt($booking['actual_return_at']); ?>
                <?php if ($isOverdue): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
              </dd>
              <dt class="col-sm-4">Locations</dt>
              <dd class="col-sm-8">
                <?php if (!empty($booking['pickup_location_name'])): ?>
                  <?php echo htmlspecialchars($booking['pickup_location_name']); ?>
                  &rarr; <?php echo htmlspecialchars($booking['dropoff_location_name'] ?? $booking['pickup_location_name']); ?>
                <?php else: ?>
                  <span class="text-body-secondary">Not recorded</span>
                <?php endif; ?>
              </dd>
            </dl>
          </div>
        </div>
      </div>

      <!-- Financial -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Financial</h3></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-6">Rate / day</dt><dd class="col-sm-6">₱<?php echo number_format((float) $booking['price_per_day'], 2); ?></dd>
              <dt class="col-sm-6">Billed days</dt><dd class="col-sm-6"><?php echo (int) $booking['days']; ?></dd>
              <dt class="col-sm-6">Location fee</dt><dd class="col-sm-6">₱<?php echo number_format((float) ($booking['location_fee'] ?? 0), 2); ?></dd>
              <dt class="col-sm-6">Discount</dt><dd class="col-sm-6">₱<?php echo number_format((float) $booking['discount'], 2); ?></dd>
              <dt class="col-sm-6">Rental total <span class="text-body-secondary">(revenue)</span></dt><dd class="col-sm-6 fw-semibold">₱<?php echo number_format((float) $booking['total_amount'], 2); ?></dd>
              <dt class="col-sm-6">Security deposit <span class="text-body-secondary">(liability)</span></dt><dd class="col-sm-6">₱<?php echo number_format((float) ($booking['security_deposit'] ?? 0), 2); ?> · <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $booking['deposit_status'] ?? 'not_collected'))); ?></dd>
              <dt class="col-sm-6">Payment status</dt>
              <dd class="col-sm-6">
                <span class="badge <?php echo booking_payment_status_badge_class($booking['payment_status'] ?? 'unpaid'); ?>"><?php echo htmlspecialchars(ucfirst($booking['payment_status'] ?? 'unpaid')); ?></span>
                <?php if (($booking['status'] ?? '') === 'approved' && ($booking['payment_status'] ?? '') !== 'paid' && !empty($booking['approved_at'])): ?>
                  <div class="small text-warning">Pay-by: <?php echo fmt_dt(date('Y-m-d H:i:s', strtotime($booking['approved_at'] . ' +48 hours'))); ?></div>
                <?php endif; ?>
              </dd>
              <?php if ((float) ($booking['refund_amount'] ?? 0) > 0): ?>
                <dt class="col-sm-6">Refund</dt><dd class="col-sm-6">₱<?php echo number_format((float) $booking['refund_amount'], 2); ?> (<?php echo (int) $booking['refund_percentage']; ?>%) · <?php echo fmt_dt($booking['cancelled_at']); ?></dd>
              <?php endif; ?>
            </dl>

            <?php if ($payments): ?>
              <hr>
              <div class="small fw-semibold mb-1">Payment ledger</div>
              <table class="table table-sm mb-0">
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td><?php echo htmlspecialchars(ucfirst($p['purpose'])); ?></td>
                      <td>₱<?php echo number_format((float) $p['amount'], 2); ?></td>
                      <td><span class="badge <?php echo $p['status'] === 'succeeded' ? 'bg-success' : ($p['status'] === 'failed' ? 'bg-danger' : 'bg-secondary'); ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                      <td class="text-body-secondary"><?php echo htmlspecialchars($p['provider'] . '/' . ($p['method'] ?? '—')); ?></td>
                      <td class="text-body-secondary small"><?php echo fmt_dt($p['paid_at'] ?: $p['created_at']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Requirements -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Requirements</h3></div>
          <div class="card-body">
            <?php
              // Snapshots taken at submit time.
              $licSnap   = $booking['license_file'] ?? null;
              $photoSnap = $booking['verification_photo_path'] ?? null;
              $addrSnap  = trim((string) ($booking['customer_address_snapshot'] ?? ''));
            ?>
            <ul class="list-unstyled mb-0">
              <li class="mb-2">
                <?php if ($licSnap): ?>
                  <i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i>
                  Driver's licence
                  <a href="secure_file.php?type=license&booking=<?php echo (int) $booking['id']; ?>" target="_blank" rel="noopener" class="ms-1">view</a>
                <?php else: ?>
                  <i class="fas fa-circle-xmark text-danger me-1" aria-hidden="true"></i> Driver's licence not on file
                <?php endif; ?>
              </li>
              <li class="mb-2">
                <?php if ($photoSnap): ?>
                  <i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i>
                  Verification photo
                  <a href="secure_file.php?type=verification&booking=<?php echo (int) $booking['id']; ?>" target="_blank" rel="noopener" class="ms-1">view</a>
                <?php else: ?>
                  <i class="fas fa-circle-xmark text-danger me-1" aria-hidden="true"></i> Verification photo not on file
                <?php endif; ?>
              </li>
              <li class="mb-2">
                <?php if ($addrSnap !== ''): ?>
                  <i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i> Home address
                  <div class="small text-body-secondary ms-4" style="white-space:pre-line;"><?php echo htmlspecialchars($addrSnap); ?></div>
                <?php else: ?>
                  <i class="fas fa-circle-xmark text-danger me-1" aria-hidden="true"></i> Home address not recorded
                <?php endif; ?>
              </li>
              <li class="mb-0">
                <?php if (!empty($booking['policy_version_accepted'])): ?>
                  <i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i>
                  Rental policy accepted (v<?php echo htmlspecialchars($booking['policy_version_accepted']); ?>, <?php echo fmt_dt($booking['policy_accepted_at']); ?>)
                <?php else: ?>
                  <i class="fas fa-circle-xmark text-danger me-1" aria-hidden="true"></i> Rental policy not accepted
                <?php endif; ?>
              </li>
            </ul>
            <p class="text-body-secondary small mb-0 mt-3">Documents open through <code>secure_file.php</code> (admin-or-owner only).</p>
          </div>
        </div>
      </div>

      <!-- Communication -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Communication</h3></div>
          <div class="card-body">
            <?php if ($conversation): ?>
              <p class="mb-1">
                <a href="admin_messages.php?id=<?php echo (int) $conversation['id']; ?>">Open conversation</a>
                <?php if ($conversation['unread'] > 0): ?>
                  <span class="badge bg-danger ms-1"><?php echo (int) $conversation['unread']; ?> new</span>
                <?php endif; ?>
              </p>
              <p class="small text-body-secondary mb-0">
                Last message <?php echo fmt_dt($conversation['last_message_at']); ?>:
                “<?php echo htmlspecialchars(mb_strimwidth((string) $conversation['last_body'], 0, 90, '…')); ?>”
              </p>
            <?php else: ?>
              <p class="text-body-secondary mb-0">No conversation yet. "Request information" above opens a booking-scoped thread with the customer.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Timeline -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-body fw-bold"><h3 class="h6 mb-0">Status timeline</h3></div>
          <div class="card-body">
            <?php if (!$history): ?>
              <p class="text-body-secondary mb-0">No status changes recorded.</p>
            <?php else: ?>
              <ol class="list-unstyled mb-0">
                <?php foreach ($history as $row): ?>
                  <li class="mb-3">
                    <div>
                      <span class="badge <?php echo booking_status_badge_class($row['to_status']); ?>"><?php echo htmlspecialchars(booking_status_label($row['to_status'])); ?></span>
                      <span class="text-body-secondary small ms-1"><?php echo fmt_dt($row['created_at']); ?></span>
                    </div>
                    <div class="small text-body-secondary">
                      <?php
                      echo htmlspecialchars(ucfirst($row['changed_by_type']));
                      if ($row['from_status']) {
                          echo ' · from ' . htmlspecialchars(booking_status_label($row['from_status']));
                      }
                      ?>
                    </div>
                    <?php if (!empty($row['reason'])): ?>
                      <div class="small"><?php echo htmlspecialchars($row['reason']); ?></div>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($inspection): ?>
    <!-- Return inspection (Phase 10) -->
    <div class="row g-4 mt-0" id="inspection">
      <div class="col-12">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-body fw-bold d-flex justify-content-between align-items-center">
            <h3 class="h6 mb-0">Return Inspection</h3>
            <span class="badge <?php echo $inspection['status'] === 'finalized' ? 'bg-success' : 'bg-warning text-body'; ?>">
              <?php echo $inspection['status'] === 'finalized' ? 'Finalized' : 'Draft'; ?>
            </span>
          </div>
          <div class="card-body">
            <div id="inspectionAlert" class="alert alert-danger d-none" role="alert"></div>
            <form id="inspectionForm" data-booking-id="<?php echo (int) $booking['id']; ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="actualReturnAt">Actual return date/time</label>
                  <input type="datetime-local" class="form-control" id="actualReturnAt"
                         value="<?php echo $booking['actual_return_at'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($booking['actual_return_at']))) : ''; ?>"
                         <?php echo $inspection['status'] === 'finalized' ? 'disabled' : ''; ?>>
                  <div class="form-text">Pre-filled from when the return was marked; correct it here if needed.</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="fuelLevelOutDisplay">Fuel out (at pickup)</label>
                  <input type="text" class="form-control" id="fuelLevelOutDisplay" value="<?php echo $booking['fuel_level_out'] !== null ? ((int) $booking['fuel_level_out'] . '/8') : '—'; ?>" disabled>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="fuelLevelIn">Fuel in (now)</label>
                  <select class="form-select" id="fuelLevelIn" <?php echo $inspection['status'] === 'finalized' ? 'disabled' : ''; ?>>
                    <option value="">Select…</option>
                    <?php for ($i = 8; $i >= 0; $i--): ?>
                      <option value="<?php echo $i; ?>" <?php echo (string) $inspection['fuel_level_in'] === (string) $i ? 'selected' : ''; ?>><?php echo $i; ?>/8</option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label" for="conditionNotes">Vehicle condition notes</label>
                  <textarea class="form-control" id="conditionNotes" rows="2" <?php echo $inspection['status'] === 'finalized' ? 'disabled' : ''; ?>><?php echo htmlspecialchars((string) $inspection['condition_notes']); ?></textarea>
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="hasDamage" <?php echo $inspection['has_damage'] ? 'checked' : ''; ?> <?php echo $inspection['status'] === 'finalized' ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="hasDamage">Damage found</label>
                  </div>
                </div>
                <div class="col-12" id="damageNotesWrap" style="<?php echo $inspection['has_damage'] ? '' : 'display:none;'; ?>">
                  <label class="form-label" for="damageNotes">Damage description</label>
                  <textarea class="form-control" id="damageNotes" rows="2" <?php echo $inspection['status'] === 'finalized' ? 'disabled' : ''; ?>><?php echo htmlspecialchars((string) $inspection['damage_notes']); ?></textarea>
                </div>
              </div>
              <div class="mt-3 d-flex align-items-center gap-2">
                <?php if ($inspection['status'] !== 'finalized'): ?>
                  <button type="submit" class="btn btn-primary">Save draft</button>
                  <span class="text-body-secondary small">Report any damage and finalize settlement in the Settlement card below.</span>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Reported damage (Phase 11) -->
    <div class="row g-4 mt-0">
      <div class="col-12">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-body fw-bold">Reported Damage</div>
          <div class="card-body">
            <?php if ($damages): ?>
              <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr><th>Description</th><th>Estimated</th><th>Status</th><th>Evidence</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($damages as $d): ?>
                    <tr>
                      <td><?php echo nl2br(htmlspecialchars($d['description'])); ?></td>
                      <td>₱<?php echo number_format((float) $d['estimated_amount'], 2); ?></td>
                      <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($d['status'])); ?></span></td>
                      <td>
                        <?php if ($d['photos']): ?>
                          <?php foreach ($d['photos'] as $p): ?>
                            <a href="secure_file.php?type=damage&photo=<?php echo (int) $p['id']; ?>" target="_blank" rel="noopener" class="d-inline-block me-1">
                              <img src="secure_file.php?type=damage&photo=<?php echo (int) $p['id']; ?>" alt="Damage evidence" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">
                            </a>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <span class="text-body-secondary small">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-body-secondary small mb-3">No damage reported yet.</p>
            <?php endif; ?>

            <?php if ($inspection['status'] !== 'finalized'): ?>
            <hr>
            <form id="reportDamageForm" data-booking-id="<?php echo (int) $booking['id']; ?>">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="damageDescription">Describe the damage</label>
                  <textarea class="form-control" id="damageDescription" rows="2" required></textarea>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="damageEstimatedAmount">Estimated cost (₱)</label>
                  <input type="number" class="form-control" id="damageEstimatedAmount" min="0" step="0.01" required>
                </div>
                <div class="col-md-8">
                  <label class="form-label" for="damagePhotos">Evidence photos (optional)</label>
                  <input type="file" class="form-control" id="damagePhotos" accept="image/jpeg,image/png" multiple>
                  <div class="form-text">JPG or PNG, up to 3MB each. You can attach more than one.</div>
                </div>
              </div>
              <div class="mt-3">
                <button type="submit" class="btn btn-outline-danger">Report damage</button>
              </div>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($proposal): ?>
    <!-- Settlement (Phase 11): finalized summary, fuel-pending preview, or the editable finalize form -->
    <div class="row g-4 mt-0" id="settlement">
      <div class="col-12">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-body fw-bold d-flex justify-content-between align-items-center">
            Settlement
            <?php if ($inspection['status'] === 'finalized'): ?>
              <span class="badge bg-success">Finalized</span>
            <?php else: ?>
              <span class="badge bg-secondary">Not yet finalized</span>
            <?php endif; ?>
          </div>
          <div class="card-body">

            <?php if ($inspection['status'] === 'finalized'): ?>
              <!-- Finalized: the actually-applied amounts, from the booking's persisted columns -->
              <div class="table-responsive mb-3">
                <table class="table table-sm mb-0">
                  <tbody>
                    <tr>
                      <th class="fw-normal text-body-secondary">Overtime</th>
                      <td>
                        ₱<?php echo number_format((float) $booking['overtime_amount'], 2); ?>
                        <?php if ($booking['overtime_adjustment_reason']): ?>
                          <div class="text-body-secondary small">Adjusted: <?php echo htmlspecialchars($booking['overtime_adjustment_reason']); ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <tr>
                      <th class="fw-normal text-body-secondary">Fuel</th>
                      <td>
                        ₱<?php echo number_format((float) $booking['fuel_charge_amount'], 2); ?>
                        <?php if ($booking['fuel_adjustment_reason']): ?>
                          <div class="text-body-secondary small">Adjusted: <?php echo htmlspecialchars($booking['fuel_adjustment_reason']); ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php foreach ($damages as $d): ?>
                    <tr>
                      <th class="fw-normal text-body-secondary">Damage: <?php echo htmlspecialchars($d['description']); ?></th>
                      <td>
                        <?php if ($d['status'] === 'waived'): ?>
                          <span class="badge bg-secondary">Waived</span>
                        <?php else: ?>
                          ₱<?php echo number_format((float) ($d['adjusted_amount'] ?? $d['estimated_amount']), 2); ?>
                        <?php endif; ?>
                        <?php if ($d['adjustment_reason']): ?>
                          <div class="text-body-secondary small"><?php echo htmlspecialchars($d['adjustment_reason']); ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                      <th class="fw-normal text-body-secondary">Security deposit</th>
                      <td>₱<?php echo number_format((float) $booking['security_deposit'], 2); ?> · <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $booking['deposit_status']))); ?></td>
                    </tr>
                    <?php if ((float) $booking['deposit_refund_amount'] > 0): ?>
                    <tr>
                      <th class="fw-normal text-body-secondary">Refunded</th>
                      <td>₱<?php echo number_format((float) $booking['deposit_refund_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ((float) $booking['outstanding_balance'] > 0): ?>
                    <tr>
                      <th class="fw-normal text-body-secondary">Outstanding balance</th>
                      <td>₱<?php echo number_format((float) $booking['outstanding_balance'], 2); ?> <span class="text-body-secondary small">(recorded — not charged, D6)</span></td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <p class="text-body-secondary small mb-0">Settled <?php echo htmlspecialchars(date('M j, Y \a\t g:i A', strtotime($booking['deposit_settled_at']))); ?>. Booking status: Completed.</p>

            <?php elseif ($proposal['fuel_pending']): ?>
              <!-- Read-only preview: fuel-in not recorded yet, finalize is blocked -->
              <div class="table-responsive mb-3">
                <table class="table table-sm mb-0">
                  <tbody>
                    <tr><th class="fw-normal text-body-secondary">Overtime</th><td>₱<?php echo number_format($proposal['overtime'], 2); ?></td></tr>
                    <tr><th class="fw-normal text-body-secondary">Fuel</th><td><span class="text-body-secondary">Pending fuel-in reading</span></td></tr>
                    <tr><th class="fw-normal text-body-secondary">Damage</th><td>₱<?php echo number_format($proposal['damage_total'], 2); ?></td></tr>
                    <tr><th class="fw-normal text-body-secondary">Security deposit</th><td>₱<?php echo number_format($proposal['deposit'], 2); ?></td></tr>
                  </tbody>
                </table>
              </div>
              <div class="alert alert-warning mb-0">Record the fuel-in reading on the inspection above before finalizing settlement.</div>

            <?php else: ?>
              <!-- Editable finalize form: proposal defaults, admin can override any line WITH a reason -->
              <div id="settleAlert" class="alert alert-danger d-none" role="alert"></div>
              <p class="text-body-secondary small">Every line below defaults to the computed proposal. Change an amount and you must give a reason — nothing is deducted, refunded, or recorded until you finalize.</p>
              <form id="settleForm" data-booking-id="<?php echo (int) $booking['id']; ?>">
                <div class="table-responsive mb-3">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr><th>Line</th><th>Proposed</th><th style="width:140px;">Final amount (₱)</th><th>Reason (required if changed)</th></tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Overtime</td>
                        <td class="text-body-secondary">₱<?php echo number_format($proposal['overtime'], 2); ?></td>
                        <td><input type="number" class="form-control form-control-sm" id="settleOvertime" min="0" step="0.01" value="<?php echo number_format($proposal['overtime'], 2, '.', ''); ?>"></td>
                        <td><input type="text" class="form-control form-control-sm" id="settleOvertimeReason"></td>
                      </tr>
                      <tr>
                        <td>Fuel</td>
                        <td class="text-body-secondary">₱<?php echo number_format($proposal['fuel_charge'], 2); ?></td>
                        <td><input type="number" class="form-control form-control-sm" id="settleFuel" min="0" step="0.01" value="<?php echo number_format($proposal['fuel_charge'], 2, '.', ''); ?>"></td>
                        <td><input type="text" class="form-control form-control-sm" id="settleFuelReason"></td>
                      </tr>
                      <?php foreach ($damages as $d): if ($d['status'] !== 'proposed') continue; ?>
                      <tr class="settle-damage-row" data-id="<?php echo (int) $d['id']; ?>">
                        <td>Damage: <?php echo htmlspecialchars($d['description']); ?></td>
                        <td class="text-body-secondary">₱<?php echo number_format((float) $d['estimated_amount'], 2); ?></td>
                        <td><input type="number" class="form-control form-control-sm settle-damage-amount" min="0" step="0.01" value="<?php echo number_format((float) $d['estimated_amount'], 2, '.', ''); ?>"></td>
                        <td>
                          <input type="text" class="form-control form-control-sm settle-damage-reason mb-1">
                          <div class="form-check">
                            <input class="form-check-input settle-damage-waive" type="checkbox" id="settleWaive<?php echo (int) $d['id']; ?>">
                            <label class="form-check-label small" for="settleWaive<?php echo (int) $d['id']; ?>">Waive (requires a reason)</label>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <p class="text-body-secondary small">Proposed total deductions: ₱<?php echo number_format($proposal['total_deductions'], 2); ?> against a ₱<?php echo number_format($proposal['deposit'], 2); ?> deposit.</p>
                <button type="submit" class="btn btn-danger">Finalize Settlement</button>
              </form>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</main>

<?php if ($booking): ?>
<!-- Mark pickup modal -->
<div class="modal fade" id="markPickupModal" tabindex="-1" aria-labelledby="markPickupModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="markPickupForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="markPickupModalLabel">Mark booking #<?php echo (int) $booking['id']; ?> picked up</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
            <div class="d-flex justify-content-between align-items-start">
              <span class="js-modal-alert-text"></span>
              <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
            </div>
          </div>
          <label class="form-label" for="fuelLevelOut">Fuel level given to the customer</label>
          <select class="form-select" id="fuelLevelOut" required>
            <option value="">Select…</option>
            <?php for ($i = 8; $i >= 0; $i--): ?>
              <option value="<?php echo $i; ?>"><?php echo $i; ?>/8</option>
            <?php endfor; ?>
          </select>
          <div class="form-text">Recorded in eighths of a tank — compared against fuel-in at return (Phase 11).</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Mark picked up</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Decline modal -->
<div class="modal fade" id="declineModal" tabindex="-1" aria-labelledby="declineModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="declineForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="declineModalLabel">Decline booking #<?php echo (int) $booking['id']; ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
            <div class="d-flex justify-content-between align-items-start">
              <span class="js-modal-alert-text"></span>
              <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
            </div>
          </div>
          <label class="form-label" for="declineReason">Reason (shown on the timeline)</label>
          <textarea class="form-control" id="declineReason" name="reason" rows="3" maxlength="255" required></textarea>
          <div class="invalid-feedback" id="declineReasonFeedback"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Decline booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Request information modal -->
<div class="modal fade" id="requestInfoModal" tabindex="-1" aria-labelledby="requestInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="requestInfoForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="requestInfoModalLabel">Request information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
            <div class="d-flex justify-content-between align-items-start">
              <span class="js-modal-alert-text"></span>
              <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
            </div>
          </div>
          <label class="form-label" for="requestInfoMessage">What does the customer need to provide?</label>
          <textarea class="form-control" id="requestInfoMessage" name="message" rows="3" maxlength="255" required></textarea>
          <div class="invalid-feedback" id="requestInfoMessageFeedback"></div>
          <p class="form-text">Sent to the customer as a message in a booking-scoped conversation, recorded on the timeline, and the booking moves to "Info Requested".</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Send request</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
<script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
</body>
</html>
