<?php
session_start();
require 'db.php';
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/return_flow.php';

/* The two guard clauses below previously did `echo "<p>...</p>"; exit;`. */
function receipt_error_page(string $title, string $message, int $status): void
{
    http_response_code($status);
    ?>
<!doctype html>
<html lang="en">
<head>
  <script>
    // Same no-flash theme bootstrap as the main receipt document below.
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> — PMS Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body>
  <main class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 32rem;">
      <div class="card-body text-center p-4">
        <i class="fa-solid fa-circle-exclamation fa-2x text-body-secondary mb-3" aria-hidden="true"></i>
        <h1 class="h4 mb-2"><?= htmlspecialchars($title) ?></h1>
        <p class="text-body-secondary mb-4"><?= htmlspecialchars($message) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
          <a href="transactions.php" class="btn btn-primary">View My Bookings</a>
          <a href="vehicles.php" class="btn btn-outline-secondary">Browse Vehicles</a>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
    <?php
    exit;
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
  // Not logged in — redirect to login page
  header('Location: login.php');
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ref = isset($_GET['ref']) ? trim($_GET['ref']) : null;

if (!$id && !$ref) {
  receipt_error_page(
    'Receipt unavailable',
    'This link is missing a booking reference, so there is nothing to show. Open the receipt from your bookings list instead.',
    400
  );
}

// Prepare query; allow lookup by id or booking_ref
if ($id) {
  $stmt = $pdo->prepare("SELECT b.*, v.title AS vehicle_title, pl.name AS pickup_location_name, dl.name AS dropoff_location_name
        FROM bookings b
        LEFT JOIN vehicles v ON b.vehicle_id = v.id
        LEFT JOIN locations pl ON pl.id = b.pickup_location_id
        LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
        WHERE b.id = ? AND b.user_id = ? LIMIT 1");
  $stmt->execute([$id, $user['id']]);
} else {
  $stmt = $pdo->prepare("SELECT b.*, v.title AS vehicle_title, pl.name AS pickup_location_name, dl.name AS dropoff_location_name
        FROM bookings b
        LEFT JOIN vehicles v ON b.vehicle_id = v.id
        LEFT JOIN locations pl ON pl.id = b.pickup_location_id
        LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
        WHERE b.booking_ref = ? AND b.user_id = ? LIMIT 1");
  $stmt->execute([$ref, $user['id']]);
}

$booking = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) {
  receipt_error_page(
    'Booking not found',
    "We couldn't find that booking, or it doesn't belong to your account.",
    404
  );
}
// Determine paid amount: prefer bookings.paid (new column). If not present, fall back to latest transaction.
$amount_paid = null;
$total_due = isset($booking['total_amount']) ? (float)$booking['total_amount'] : 0.0;
if (array_key_exists('paid', $booking) && $booking['paid'] !== null && $booking['paid'] !== '') {
  $amount_paid = (float)$booking['paid'];
  // Bookings.paid is rental + security deposit collected together.
  $total_due += (float) ($booking['security_deposit'] ?? 0);
} else {
  // Fallback: look up most recent transaction for this booking.
  $txnStmt = $pdo->prepare("SELECT * FROM transactions WHERE booking_id = ? ORDER BY paid_at DESC LIMIT 1");
  $txnStmt->execute([$booking['id']]);
  $txn = $txnStmt->fetch(PDO::FETCH_ASSOC);
  if ($txn && isset($txn['amount'])) {
    $amount_paid = (float)$txn['amount'];
  }
}

$change = null;
if ($amount_paid !== null) {
  $change = $amount_paid - $total_due;
}

// Phase 9: payment + deposit picture.
$payments = payment_list_for_booking($pdo, (int) $booking['id']);
$rentalPaid = 0.0; $depositPaid = 0.0; $refunded = 0.0; $lastMethod = null; $lastRef = null;
foreach ($payments as $p) {
  if ($p['status'] !== 'succeeded') continue;
  if ($p['purpose'] === 'rental')  { $rentalPaid  += (float) $p['amount']; $lastMethod = $p['method']; }
  if ($p['purpose'] === 'deposit') { $depositPaid += (float) $p['amount']; }
  if ($p['purpose'] === 'refund')  { $refunded    += (float) $p['amount']; }
}

// Itemized settlement breakdown, once the booking has actually been through finalize.
$isSettled = !empty($booking['deposit_settled_at']);
$settlementDamages = $isSettled ? return_flow_list_damages($pdo, (int) $booking['id']) : [];

// A completed booking gets the "Receipt" framing; anything else gets "Booking Confirmation".
$is_completed = strtolower($booking['status'] ?? 'pending_review') === 'completed';
$doc_heading = $is_completed ? 'Booking Receipt' : 'Booking Confirmation';
$doc_note = $is_completed
  ? 'Please present this receipt and a valid ID at pickup.'
  : 'Please present this confirmation and a valid ID at pickup.';
?>
<!doctype html>
<html lang="en">
<head>
  <script>
    // No-flash theme bootstrap.
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($doc_heading) ?> — <?= htmlspecialchars($booking['booking_ref']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
  <style>
    body { padding-top: 60px; }
    .receipt-card { max-width: 900px; margin: 0 auto; }
  </style>
</head>
<body class="receipt-page"><!-- .receipt-page scopes css/styles.css's @media print block to this page only (BUGS.md item 26) -->
  <?php include 'includes/client_navbar.php'; ?>

  <main class="container py-5">
    <div class="receipt-card card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h1 class="h4 mb-0"><?= htmlspecialchars($doc_heading) ?></h1>
            <small class="text-body-secondary">Reference: <?= htmlspecialchars($booking['booking_ref']) ?></small>
          </div>
          <div class="text-end">
            <h2 class="h5 mb-0">PMS Car Rental</h2>
            <small class="text-body-secondary">Contact: (02) 1234 5678</small>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <p class="mb-1"><strong>Vehicle</strong><br><?= htmlspecialchars($booking['vehicle_title'] ?? 'N/A') ?></p>
            <p class="mb-1"><strong>Rental Period</strong><br><?= htmlspecialchars($booking['rental_date']) ?> → <?= htmlspecialchars($booking['return_date']) ?></p>
            <p class="mb-1"><strong>Pickup Time</strong><br><?= htmlspecialchars($booking['pickup_time'] ?: 'N/A') ?></p>
            <p class="mb-1"><strong>Drop-off Time</strong><br><?= htmlspecialchars($booking['dropoff_time'] ?: 'N/A') ?></p>
            <?php if (!empty($booking['pickup_location_name'])): ?>
              <p class="mb-1"><strong>Pick-up / Drop-off Branch</strong><br>
                <?= htmlspecialchars($booking['pickup_location_name']) ?>
                &rarr; <?= htmlspecialchars($booking['dropoff_location_name'] ?? $booking['pickup_location_name']) ?></p>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <p class="mb-1"><strong>Days</strong><br><?= htmlspecialchars($booking['days']) ?></p>
            <p class="mb-1"><strong>Rate per Day</strong><br>₱<?= number_format($booking['rate'], 2) ?></p>
            <?php if ((float) ($booking['location_fee'] ?? 0) > 0): ?>
              <p class="mb-1"><strong>Location Fee</strong><br>₱<?= number_format($booking['location_fee'], 2) ?></p>
            <?php endif; ?>
            <?php if ((float) ($booking['discount'] ?? 0) > 0): ?>
              <p class="mb-1"><strong>Discount</strong><br>&minus;₱<?= number_format($booking['discount'], 2) ?></p>
            <?php endif; ?>
            <p class="mb-1"><strong>Total</strong><br>₱<?= number_format($booking['total_amount'], 2) ?></p>
          </div>
        </div>

        <hr>
        <div class="row">
          <div class="col-md-6">
            <p><strong>Contact Number</strong><br><?= htmlspecialchars($booking['contact_number'] ?? '-') ?></p>
            <p><strong>Driver Age</strong><br><?= htmlspecialchars($booking['age'] ?? '-') ?></p>
          </div>
          <div class="col-md-6 text-end">
            <?php
            // This block used to render unconditionally, so a brand-new booking.
            $hasPaymentActivity = ($booking['payment_status'] ?? 'unpaid') !== 'unpaid';
            ?>
            <p><strong>Amount Paid</strong><br>
            <?php if ($hasPaymentActivity && $amount_paid !== null): ?>
              ₱<?= number_format($amount_paid, 2) ?>
            <?php else: ?>
              <span class="text-body-secondary">Not paid yet</span>
            <?php endif; ?>
            </p>

            <?php if ($hasPaymentActivity && $change !== null): ?>
              <p class="mb-0"><strong>Change</strong><br>
                <?= $change >= 0 ? '₱' . number_format($change, 2) : '<span class="text-danger">-₱' . number_format(abs($change), 2) . ' (underpaid)</span>' ?>
              </p>
            <?php endif; ?>

            <p class="mb-0"><strong>Status</strong><br><?= htmlspecialchars(booking_status_label($booking['status'] ?? 'pending_review')) ?></p>
          </div>
        </div>

        <hr>
        <div class="row">
          <div class="col-md-6">
            <p class="mb-1"><strong>Rental Total</strong><br>₱<?= number_format((float) ($booking['rental_fee'] ?? $booking['total_amount']), 2) ?></p>
            <p class="mb-1"><strong>Payment Status</strong><br>
              <span class="badge <?= booking_payment_status_badge_class($booking['payment_status'] ?? 'unpaid') ?>"><?= htmlspecialchars(ucfirst($booking['payment_status'] ?? 'unpaid')) ?></span>
              <?php if ($lastMethod): ?><span class="text-body-secondary ms-1">(<?= htmlspecialchars($lastMethod) ?>)</span><?php endif; ?>
            </p>
          </div>
          <div class="col-md-6 text-md-end">
            <div class="border rounded p-2 bg-body-tertiary d-inline-block text-start">
              <div class="fw-semibold">Security Deposit <span class="text-body-secondary">(refundable)</span></div>
              <div>Amount: ₱<?= number_format((float) ($booking['security_deposit'] ?? 0), 2) ?></div>
              <div>Status: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $booking['deposit_status'] ?? 'not_collected'))) ?></div>
              <?php if ((float) ($booking['deposit_refund_amount'] ?? 0) > 0): ?>
                <div>Refunded: ₱<?= number_format((float) $booking['deposit_refund_amount'], 2) ?></div>
              <?php endif; ?>
              <small class="text-body-secondary d-block mt-1">Returned after a clean return &amp; inspection; overtime, fuel or damage charges are deducted from it first.</small>
            </div>
          </div>
        </div>

        <?php if ($isSettled): ?>
          <hr>
          <div class="mb-1">
            <h3 class="h6 fw-semibold mb-2">Settlement Breakdown</h3>
            <ul class="list-unstyled small mb-2">
              <li class="mb-1">
                Overtime: ₱<?= number_format((float) ($booking['overtime_amount'] ?? 0), 2) ?>
                <?php if (!empty($booking['overtime_adjustment_reason'])): ?>
                  <span class="text-body-secondary">— <?= htmlspecialchars($booking['overtime_adjustment_reason']) ?></span>
                <?php endif; ?>
              </li>
              <li class="mb-1">
                Fuel: ₱<?= number_format((float) ($booking['fuel_charge_amount'] ?? 0), 2) ?>
                <?php if (!empty($booking['fuel_adjustment_reason'])): ?>
                  <span class="text-body-secondary">— <?= htmlspecialchars($booking['fuel_adjustment_reason']) ?></span>
                <?php endif; ?>
              </li>
              <?php foreach ($settlementDamages as $d): ?>
                <?php if ($d['status'] === 'waived'): ?>
                  <li class="mb-1 text-body-secondary">Damage — <?= htmlspecialchars($d['description']) ?>: waived<?php if (!empty($d['adjustment_reason'])): ?> (<?= htmlspecialchars($d['adjustment_reason']) ?>)<?php endif; ?></li>
                <?php else: ?>
                  <li class="mb-1">
                    Damage — <?= htmlspecialchars($d['description']) ?>: ₱<?= number_format((float) ($d['adjusted_amount'] ?? $d['estimated_amount']), 2) ?>
                    <?php foreach ($d['photos'] as $p): ?>
                      <a href="secure_file.php?type=damage&photo=<?= (int) $p['id'] ?>" target="_blank" rel="noopener" class="ms-1">[photo]</a>
                    <?php endforeach; ?>
                    <?php if (!empty($d['adjustment_reason'])): ?>
                      <div class="text-body-secondary">— <?= htmlspecialchars($d['adjustment_reason']) ?></div>
                    <?php endif; ?>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ul>
            <?php if ((float) ($booking['outstanding_balance'] ?? 0) > 0): ?>
              <div class="alert alert-warning py-2 mb-0 small">
                Outstanding balance: ₱<?= number_format((float) $booking['outstanding_balance'], 2) ?> — recorded on your account, not automatically charged.
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($refunded > 0 || (float) ($booking['refund_amount'] ?? 0) > 0): ?>
          <div class="alert alert-info mt-3 mb-0">
            <strong>Refund:</strong> ₱<?= number_format((float) ($booking['refund_amount'] ?? $refunded), 2) ?>
            <?php if (!empty($booking['refund_percentage'])): ?>(<?= (int) $booking['refund_percentage'] ?>% of the rental<?= (float) ($booking['deposit_refund_amount'] ?? 0) > 0 ? ' + full deposit' : '' ?>)<?php endif; ?>
            <?php if (!empty($booking['cancelled_at'])): ?><span class="text-body-secondary">· cancelled <?= htmlspecialchars(date('M j, Y', strtotime($booking['cancelled_at']))) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="mt-4 text-center text-body-secondary">
          <small><?= htmlspecialchars($doc_note) ?></small>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <a href="vehicles.php" class="btn btn-outline-secondary">Exit</a>
        <a href="transactions.php" class="btn btn-link">View All Transactions</a>
      </div>
    </div>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
</body>
</html>
