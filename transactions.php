  
<?php
session_start();
require 'db.php';
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/messaging.php';
require_once __DIR__ . '/lib/requirements.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/reviews.php';
require_once __DIR__ . '/lib/csrf.php';

// Now also set centrally in db.php for every entry point in the app, not just this page — see the comment there.
date_default_timezone_set('Asia/Manila');

$user = $_SESSION['user'] ?? null;

if ($user) {
  // Fetch user's bookings
  $stmt = $pdo->prepare("
    SELECT
      b.id, b.user_id, b.status, b.payment_status, b.approved_at,
      b.booking_ref, b.rental_date, b.return_date, b.pickup_time, b.dropoff_time,
      b.dropoff_at, b.picked_up_at, b.actual_return_at,
      b.days, b.rate, b.discount, b.total_amount, b.security_deposit,
      b.deposit_status, b.refund_amount, b.refund_percentage,
      b.deposit_refund_amount, b.outstanding_balance,
      b.contact_number, b.created_at,
      v.title AS vehicle_title,
      (SELECT t.transaction_ref FROM transactions t WHERE t.booking_id = b.id ORDER BY t.id DESC LIMIT 1) AS transaction_ref
    FROM bookings b
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.user_id = ?
    ORDER BY b.rental_date DESC
  ");
  $stmt->execute([$user['id']]);
  $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // P8: lazily expire any approved-but-unpaid booking whose 48h payment window has passed, then re-read.
  $expiredAny = false;
  foreach ($bookings as $b) {
    // Self-heal a missed webhook before deciding the window has expired.
    if ($b['status'] === 'approved' && $b['payment_status'] !== 'paid' && payment_sync_paymongo($pdo, (int) $b['id'])) {
      $expiredAny = true; // forces the re-read below
      continue;
    }
    if (payment_expire_if_stale($pdo, $b)) {
      $expiredAny = true;
    }
  }
  if ($expiredAny) {
    $stmt->execute([$user['id']]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  $csrfToken = csrf_token();

  // Separate into active and completed, driven by actual status.
  $today = date('Y-m-d');
  $active = [];
  $completed = [];
  $preDispatchStatuses = ['pending_review', 'info_requested', 'approved', 'paid'];

  foreach ($bookings as $b) {
    $status = strtolower($b['status'] ?? 'pending_review');
    if ($status === 'active' || (in_array($status, $preDispatchStatuses, true) && $b['return_date'] >= $today)) {
      $active[] = $b;
    } else {
      $completed[] = $b;
    }
  }

  // Which completed bookings already have a review, one query rather than one per row.
  $reviewMap = review_map_for_bookings($pdo, array_column($completed, 'id'));

  // Dashboard summary metrics — derived from $active/$completed only.
  $paidOrBeyond = array_filter(
    array_merge($active, $completed),
    fn($b) => ($b['payment_status'] ?? 'unpaid') !== 'unpaid'
  );
  $totalSpent = array_sum(array_column($paidOrBeyond, 'total_amount'));
  // Query is ORDER BY rental_date DESC, so the last element of $active is the nearest upcoming rental.
  $nextRental = !empty($active) ? $active[count($active) - 1] : null;

  // Upcoming-pickup reminder — a daily-recurring derived fact.
  $alerts = [];
  $now = time();

  foreach ($active as $a) {
    $rentalStart = strtotime($a['rental_date'] . ' ' . ($a['pickup_time'] ?: '00:00:00'));
    $hoursUntil = ($rentalStart - $now) / 3600;
    if ($hoursUntil >= 0 && $hoursUntil <= 24) {
      $isToday = $a['rental_date'] === $today;
      $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-calendar-check',
        'message' => 'Reminder: Your rental of ' . htmlspecialchars($a['vehicle_title'] ?? 'your vehicle') .
          ($isToday ? ' starts today!' : ' starts tomorrow!'),
      ];
    }
  }

  // Messaging (Phase 6) — the customer's conversations, most-recent first.
  $convStmt = $pdo->prepare("
    SELECT c.id, c.subject, c.last_message_at, c.booking_id,
           b.booking_ref,
           (SELECT COUNT(*) FROM conversation_messages m
            WHERE m.conversation_id = c.id AND m.sender_type = 'admin' AND m.read_at IS NULL) AS unread
    FROM conversations c
    LEFT JOIN bookings b ON b.id = c.booking_id
    WHERE c.user_id = ?
    ORDER BY (unread > 0) DESC, c.last_message_at DESC
    LIMIT 10
  ");
  $convStmt->execute([$user['id']]);
  $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);

  // Verification requirements for the profile section (Phase 7).
  $reqStatus = requirements_user_status($pdo, (int) $user['id']);
  $reqUser = $reqStatus['user'];
}
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
  <title>PMS Car Rental — Transactions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>

  <div style="height:80px"></div>

  <main class="container py-5">
    <div class="text-center mb-4" data-reveal>
      <h1 class="fw-bold display-5 font-poppins">My Dashboard</h1>
      <p class="text-body-secondary">View your current and past rentals at a glance</p>
    </div>

    <?php if (!$user): ?>
      <!-- Not logged in -->
      <div class="text-center my-5">
        <div class="display-6 mb-3 text-body-secondary">No transactions yet</div>
        <p class="mb-4">Sign up or log in to view your transaction history.</p>
        <button class="btn btn-outline-primary rounded-pill btn-login me-2">Log In</button>
        <button class="btn btn-primary rounded-pill btn-signup">Sign Up</button>
      </div>

    <?php elseif (empty($bookings)): ?>
      <!-- Logged in but no bookings -->
      <div class="text-center my-5">
        <div class="display-6 mb-3 text-body-secondary">No transactions yet</div>
        <p class="mb-4">You have not made any bookings yet.</p>
        <a href="vehicles.php" class="btn btn-primary rounded-pill">Book a Vehicle</a>
      </div>

    <?php else: ?>
      <!-- Summary Cards -->
      <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3">Overview</h2>
        <div class="row g-3 g-lg-4" data-reveal-stagger>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-car dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Active Rentals</h3>
              <div class="h2 dashboard-stat-value mb-0"><?= count($active) ?></div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-check-circle dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Completed Rentals</h3>
              <div class="h2 dashboard-stat-value mb-0"><?= count($completed) ?></div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-money-bill-wave dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Total Spent</h3>
              <div class="h2 dashboard-stat-value mb-0">₱<?= number_format($totalSpent, 2) ?></div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-calendar-alt dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Next Rental</h3>
              <div class="h4 dashboard-stat-value mb-0">
                <?= $nextRental ? htmlspecialchars(date('M j, Y', strtotime($nextRental['rental_date']))) : 'None scheduled' ?>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- NOTIFICATIONS / BOOKING STATUS ALERTS -->
      <?php if (!empty($alerts)): ?>
        <section class="mb-5" data-reveal>
          <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert">
              <i class="fas <?= $alert['icon'] ?> me-2" aria-hidden="true"></i><?= $alert['message'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <!-- MESSAGES (Phase 6) -->
      <?php if (!empty($conversations)): ?>
        <section class="mb-5" data-reveal>
          <h2 class="h4 fw-semibold mb-3"><i class="fas fa-comments" aria-hidden="true"></i> Messages</h2>
          <div class="list-group">
            <?php foreach ($conversations as $c): ?>
              <a href="my_messages.php?id=<?= (int) $c['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span class="text-truncate <?= $c['unread'] > 0 ? 'fw-bold' : '' ?>"><?= htmlspecialchars($c['subject']) ?></span>
                <span class="text-nowrap ms-2">
                  <?php if ($c['unread'] > 0): ?><span class="badge bg-danger rounded-pill me-2"><?= (int) $c['unread'] ?></span><?php endif; ?>
                  <small class="text-body-secondary"><?= htmlspecialchars(date('M j', strtotime($c['last_message_at']))) ?></small>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
          <a href="my_messages.php" class="btn btn-sm btn-outline-secondary mt-2">All messages</a>
        </section>
      <?php endif; ?>

      <!-- ACTIVE RENTALS -->
      <?php if (!empty($active)): ?>
        <section class="mb-5" data-reveal>
        <h2 class="h4 fw-semibold mb-3 text-primary"><i class="fas fa-car" aria-hidden="true"></i> Active & Upcoming Rentals</h2>
        <div id="bookingActionAlert"></div>
        <div class="list-group">
          <?php foreach ($active as $a): ?>
            <?php
              // Time-window label only — date-string comparison avoids same-day return_date being misread as "past" due to time-of-day.
              if ($today < $a['rental_date']) {
                $time_status = 'Upcoming';
                // Text-body required: white badge text on Bootstrap's bg-info measures ~1.96:1 contrast, far below WCAG AA's 4.5:1.
                $time_badge_class = 'bg-info text-body';
              } else {
                $time_status = 'Active';
                $time_badge_class = 'bg-success';
              }

              // Booking status badge — $active only ever contains the pre-dispatch statuses.
              $booking_status = strtolower($a['status'] ?? 'pending_review');
              $booking_badge_class = booking_status_badge_class($booking_status);
              $booking_label = booking_status_label($booking_status);
              $isOverdueRow = booking_is_overdue($a);
            ?>
            <div class="list-group-item">
              <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-2">
                <div class="text-truncate">
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h3 class="h6 mb-0 text-truncate"><?= htmlspecialchars($a['vehicle_title'] ?? 'Unknown Vehicle') ?></h3>
                    <span class="badge <?= $time_badge_class ?>" role="status"><?= $time_status ?></span>
                    <span class="badge <?= $booking_badge_class ?>" role="status"><?= $booking_label ?></span>
                    <?php // a paid booking's status label is already "Paid" — a second identical payment badge read as a duplicate. ?>
                    <?php if (strcasecmp(strip_tags((string) $booking_label), (string) $a['payment_status']) !== 0): ?>
                    <span class="badge <?= booking_payment_status_badge_class($a['payment_status']) ?>" role="status"><?= htmlspecialchars(ucfirst($a['payment_status'])) ?></span>
                    <?php endif; ?>
                    <?php if ($isOverdueRow): ?><span class="badge bg-danger" role="status">Overdue</span><?php endif; ?>
                  </div>
                  <small class="text-body-secondary">
                    <?= htmlspecialchars($a['rental_date']) ?> → <?= htmlspecialchars($a['return_date']) ?><br>
                    <?= htmlspecialchars($a['pickup_time'] ?: '-') ?> - <?= htmlspecialchars($a['dropoff_time'] ?: '-') ?>
                    <?php if ($booking_status === 'approved' && $a['payment_status'] !== 'paid' && !empty($a['approved_at'])): ?>
                      <br><span class="text-warning">Pay by <?= htmlspecialchars(date('M j, g:i A', strtotime($a['approved_at'] . ' +48 hours'))) ?> or the booking auto-cancels.</span>
                    <?php endif; ?>
                    <?php if ($booking_status === 'active' && !empty($a['picked_up_at'])): ?>
                      <br>Picked up <?= htmlspecialchars(date('M j, g:i A', strtotime($a['picked_up_at']))) ?>
                    <?php endif; ?>
                  </small>
                </div>
                <div class="text-md-end">
                  <div class="fw-bold text-success">₱<?= number_format($a['total_amount'], 2) ?></div>
                  <small class="text-body-secondary">Ref: <?= htmlspecialchars($a['booking_ref']) ?></small>
                </div>
              </div>
              <div class="mt-2 d-grid d-md-flex gap-2">
                <?php if ($booking_status === 'approved' && $a['payment_status'] !== 'paid'): ?>
                  <button type="button" class="btn btn-success py-3 js-pay-now"
                          data-id="<?= (int) $a['id'] ?>"
                          data-total="<?= number_format((float) $a['total_amount'], 2, '.', '') ?>"
                          data-deposit="<?= number_format((float) $a['security_deposit'], 2, '.', '') ?>"
                          data-vehicle="<?= htmlspecialchars($a['vehicle_title'] ?? '', ENT_QUOTES) ?>">
                    <i class="fas fa-credit-card me-1" aria-hidden="true"></i> Pay Now
                  </button>
                <?php endif; ?>
                <?php if ($booking_status === 'active'): ?>
                  <button type="button" class="btn btn-outline-warning py-3" onclick="showReturnEarlyModal('<?= $a['id'] ?>')" aria-label="Return <?= htmlspecialchars($a['vehicle_title']) ?> early">
                    Return Vehicle
                  </button>
                <?php elseif (in_array($booking_status, ['pending_review', 'info_requested', 'approved', 'paid'], true)): ?>
                  <button type="button" class="btn btn-outline-danger py-3" onclick="showCancelModal('<?= $a['id'] ?>', '<?= htmlspecialchars($a['vehicle_title']) ?>', '<?= $a['rental_date'] ?>')" aria-label="Cancel booking for <?= htmlspecialchars($a['vehicle_title']) ?>">
                    Cancel Booking
                  </button>
                <?php endif; ?>
                <?php if (in_array($booking_status, ['approved', 'paid'], true)): ?>
                  <a href="receipt.php?ref=<?= urlencode($a['booking_ref']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary py-3" aria-label="View booking confirmation for booking <?= htmlspecialchars($a['booking_ref']) ?>">
                    <i class="fas fa-file-invoice me-1" aria-hidden="true"></i> View Booking Confirmation
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        </section>
      <?php endif; ?>

      <!-- COMPLETED RENTALS -->
      <?php if (!empty($completed)): ?>
        <section class="mb-5" data-reveal>
        <h2 class="h4 fw-semibold mb-3 text-secondary"><i class="fas fa-check-circle" aria-hidden="true"></i> Completed Rentals</h2>
        <div class="list-group">
          <?php foreach ($completed as $c): ?>
            <?php
              $c_status = strtolower($c['status'] ?? 'pending_review');
              $c_badge_class = booking_status_badge_class($c_status);
              $c_label = booking_status_label($c_status);
            ?>
            <div class="list-group-item">
              <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-2">
                <div class="text-truncate">
                  <h3 class="h6 mb-1 text-truncate"><?= htmlspecialchars($c['vehicle_title'] ?? 'Unknown Vehicle') ?> <span class="badge <?= $c_badge_class ?> ms-2" role="status"><?= $c_label ?></span></h3>
                  <small class="text-body-secondary">
                    <?= htmlspecialchars($c['rental_date']) ?> → <?= htmlspecialchars($c['return_date']) ?><br>
                    <?= htmlspecialchars($c['pickup_time'] ?: '-') ?> - <?= htmlspecialchars($c['dropoff_time'] ?: '-') ?>
                  </small>
                </div>
                <div class="text-md-end">
                  <div class="fw-bold text-body">₱<?= number_format($c['total_amount'], 2) ?></div>
                </div>
              </div>
              <?php if ($c_status === 'completed'): ?>
              <?php if ((float) ($c['outstanding_balance'] ?? 0) > 0): ?>
                <div class="mt-1 small text-warning-emphasis">
                  <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
                  Outstanding balance: ₱<?= number_format((float) $c['outstanding_balance'], 2) ?> (recorded — see receipt)
                </div>
              <?php elseif ((float) ($c['deposit_refund_amount'] ?? 0) > 0): ?>
                <div class="mt-1 small text-body-secondary">
                  Deposit refunded: ₱<?= number_format((float) $c['deposit_refund_amount'], 2) ?>
                </div>
              <?php endif; ?>
              <div class="mt-2 d-grid d-md-flex gap-2">
                <a href="receipt.php?ref=<?= urlencode($c['booking_ref']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary py-3" aria-label="View receipt for booking <?= htmlspecialchars($c['booking_ref']) ?>">
                  <i class="fas fa-receipt me-1" aria-hidden="true"></i> View Receipt
                </a>
                <?php if (isset($reviewMap[$c['id']])): ?>
                  <span class="btn btn-outline-success py-3 disabled" aria-disabled="true">
                    <i class="fas fa-star me-1" aria-hidden="true"></i>
                    You rated this <?= (int) $reviewMap[$c['id']]['rating'] ?>/5
                  </span>
                <?php else: ?>
                  <button type="button" class="btn btn-primary py-3" onclick="showReviewModal('<?= (int) $c['id'] ?>', '<?= htmlspecialchars($c['vehicle_title'] ?? 'this vehicle', ENT_QUOTES) ?>')" aria-label="Leave a review for booking <?= htmlspecialchars($c['booking_ref']) ?>">
                    <i class="fas fa-star me-1" aria-hidden="true"></i> Leave a Review
                  </button>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($user): ?>
      <!-- VERIFICATION & ADDRESS (Phase 7) -->
      <section class="mb-5" id="requirements" data-reveal>
        <h2 class="h4 fw-semibold mb-3"><i class="fas fa-id-card" aria-hidden="true"></i> Verification &amp; Address</h2>
        <?php if (!$reqStatus['complete']): ?>
          <div class="alert alert-warning">Complete all three below before you can submit a booking.</div>
        <?php else: ?>
          <div class="alert alert-success">Your profile has everything needed to book.
            <?php if (($reqUser['verification_status'] ?? 'none') === 'pending'): ?>
              Your verification photo is awaiting review.
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="row g-3 g-lg-4">
          <div class="col-lg-6">
            <div class="bg-body rounded-3 shadow-sm p-4 h-100">
              <h3 class="h6 fw-semibold mb-3">Documents</h3>
              <div id="requirementDocAlert" class="alert d-none" role="alert"></div>

              <form class="js-doc-form mb-4" data-type="license" novalidate>
                <label class="form-label" for="licenseInput">
                  Driver's licence
                  <?php if ($reqStatus['license']): ?><span class="badge bg-success ms-1">on file</span><?php else: ?><span class="badge bg-danger ms-1">missing</span><?php endif; ?>
                </label>
                <div class="d-flex flex-column flex-sm-row gap-2">
                  <input type="file" class="form-control" id="licenseInput" name="file" accept="image/jpeg,image/png">
                  <button type="submit" class="btn btn-outline-primary rounded-pill px-4 text-nowrap">Upload</button>
                </div>
                <?php if ($reqStatus['license']): ?>
                  <a class="form-text d-inline-block mt-1" href="secure_file.php?type=license&user=<?= (int) $user['id'] ?>" target="_blank" rel="noopener">View current licence</a>
                <?php else: ?>
                  <div class="form-text">JPG or PNG, up to 3MB. Only you and PMS staff can see it.</div>
                <?php endif; ?>
              </form>

              <form class="js-doc-form" data-type="verification" novalidate>
                <label class="form-label" for="verificationInput">
                  Verification photo
                  <?php if ($reqStatus['photo']): ?><span class="badge bg-success ms-1">on file</span><?php else: ?><span class="badge bg-danger ms-1">missing</span><?php endif; ?>
                </label>
                <div class="d-flex flex-column flex-sm-row gap-2">
                  <input type="file" class="form-control" id="verificationInput" name="file" accept="image/jpeg,image/png">
                  <button type="submit" class="btn btn-outline-primary rounded-pill px-4 text-nowrap">Upload</button>
                </div>
                <?php if ($reqStatus['photo']): ?>
                  <a class="form-text d-inline-block mt-1" href="secure_file.php?type=verification&user=<?= (int) $user['id'] ?>" target="_blank" rel="noopener">View current photo</a>
                <?php else: ?>
                  <div class="form-text">A clear photo of you holding your licence. JPG or PNG, up to 3MB.</div>
                <?php endif; ?>
              </form>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="bg-body rounded-3 shadow-sm p-4 h-100">
              <h3 class="h6 fw-semibold mb-3">Home address
                <?php if ($reqStatus['address']): ?><span class="badge bg-success ms-1">saved</span><?php else: ?><span class="badge bg-danger ms-1">missing</span><?php endif; ?>
              </h3>
              <div id="addressAlert" class="alert d-none" role="alert"></div>
              <form id="addressForm" novalidate>
                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label" for="addrLine1">Address line 1</label>
                    <input type="text" class="form-control" id="addrLine1" name="address_line1" value="<?= htmlspecialchars((string) ($reqUser['address_line1'] ?? '')) ?>" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="addrLine2">Address line 2 <span class="text-body-secondary">(optional)</span></label>
                    <input type="text" class="form-control" id="addrLine2" name="address_line2" value="<?= htmlspecialchars((string) ($reqUser['address_line2'] ?? '')) ?>">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="addrCity">City / Municipality</label>
                    <input type="text" class="form-control" id="addrCity" name="city" value="<?= htmlspecialchars((string) ($reqUser['city'] ?? '')) ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="addrProvince">Province</label>
                    <input type="text" class="form-control" id="addrProvince" name="province" value="<?= htmlspecialchars((string) ($reqUser['province'] ?? '')) ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="addrPostal">Postal code <span class="text-body-secondary">(optional)</span></label>
                    <input type="text" class="form-control" id="addrPostal" name="postal_code" value="<?= htmlspecialchars((string) ($reqUser['postal_code'] ?? '')) ?>">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="addrPhone">Phone <span class="text-body-secondary">(optional)</span></label>
                    <input type="tel" class="form-control" id="addrPhone" name="phone" value="<?= htmlspecialchars((string) ($reqUser['phone'] ?? '')) ?>">
                  </div>
                </div>
                <div class="d-grid d-md-flex mt-3">
                  <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">Save address</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>

      <!-- PROFILE SECTION -->
      <section class="mb-5" id="profile" data-reveal>
        <h2 class="h4 fw-semibold mb-3"><i class="fas fa-user" aria-hidden="true"></i> My Profile</h2>
        <div class="row g-3 g-lg-4">
          <div class="col-lg-6">
            <div class="bg-body rounded-3 shadow-sm p-4 h-100">
              <h3 class="h6 fw-semibold mb-3">Profile Information</h3>
              <div id="profileInfoAlert" class="alert d-none" role="alert" tabindex="-1"></div>
              <div id="profilePictureAlert" class="alert d-none" role="alert" tabindex="-1"></div>

              <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3 mb-4">
                <div id="profilePictureDisplay" style="width:96px;height:96px;"></div>
                <form id="profilePictureForm" class="flex-grow-1 w-100" novalidate>
                  <label for="profilePictureInput" class="form-label">Profile Picture</label>
                  <div class="d-flex flex-column flex-sm-row gap-2">
                    <input type="file" class="form-control" id="profilePictureInput" name="profile_picture" accept="image/jpeg,image/png" aria-describedby="profilePictureInputHelp">
                    <button type="submit" id="profilePictureSaveBtn" class="btn btn-outline-primary rounded-pill px-4 py-3 text-nowrap" aria-label="Upload profile picture">Upload</button>
                  </div>
                  <div class="form-text" id="profilePictureInputHelp">JPG or PNG, up to 3MB</div>
                </form>
              </div>

              <dl class="row mb-4">
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Name</dt>
                <dd class="col-7 col-sm-8" id="profileNameDisplay">—</dd>
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Email</dt>
                <dd class="col-7 col-sm-8 text-break" id="profileEmailDisplay">—</dd>
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Member Since</dt>
                <dd class="col-7 col-sm-8" id="profileMemberSinceDisplay">—</dd>
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Role</dt>
                <dd class="col-7 col-sm-8" id="profileRoleDisplay">—</dd>
              </dl>
              <form id="profileInfoForm" novalidate>
                <div class="mb-3">
                  <label for="profileNameInput" class="form-label">Name</label>
                  <input type="text" class="form-control" id="profileNameInput" required autocomplete="name" aria-describedby="profileNameInputFeedback">
                  <div class="invalid-feedback" id="profileNameInputFeedback"></div>
                </div>
                <div class="mb-3">
                  <label for="profileEmailInput" class="form-label">Email</label>
                  <input type="email" class="form-control" id="profileEmailInput" required autocomplete="email" aria-describedby="profileEmailInputFeedback">
                  <div class="invalid-feedback" id="profileEmailInputFeedback"></div>
                </div>
                <div class="d-grid d-md-flex">
                  <button type="submit" id="profileInfoSaveBtn" class="btn btn-primary rounded-pill px-4 py-3" aria-label="Save profile information changes">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="bg-body rounded-3 shadow-sm p-4 h-100">
              <h3 class="h6 fw-semibold mb-3">Change Password</h3>
              <div id="changePasswordAlert" class="alert d-none" role="alert" tabindex="-1"></div>
              <form id="changePasswordForm" novalidate>
                <div class="mb-3">
                  <label for="currentPasswordInput" class="form-label">Current Password</label>
                  <input type="password" class="form-control" id="currentPasswordInput" required autocomplete="current-password" aria-describedby="currentPasswordInputFeedback">
                  <div class="invalid-feedback" id="currentPasswordInputFeedback"></div>
                </div>
                <div class="mb-3">
                  <label for="newPasswordInput" class="form-label">New Password</label>
                  <input type="password" class="form-control" id="newPasswordInput" required autocomplete="new-password" aria-describedby="newPasswordInputFeedback newPasswordInputHelp">
                  <div class="invalid-feedback" id="newPasswordInputFeedback"></div>
                  <div class="form-text" id="newPasswordInputHelp">Minimum 6 characters</div>
                </div>
                <div class="mb-3">
                  <label for="confirmNewPasswordInput" class="form-label">Confirm New Password</label>
                  <input type="password" class="form-control" id="confirmNewPasswordInput" required autocomplete="new-password" aria-describedby="confirmNewPasswordInputFeedback">
                  <div class="invalid-feedback" id="confirmNewPasswordInputFeedback"></div>
                </div>
                <div class="d-grid d-md-flex">
                  <button type="submit" id="changePasswordSaveBtn" class="btn btn-primary rounded-pill px-4 py-3" aria-label="Save new password">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <!-- Return Early Modal -->
  <div class="modal fade" id="returnEarlyModal" tabindex="-1" aria-labelledby="returnEarlyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="returnEarlyModalLabel">Return This Vehicle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="returnEarlyForm">
            <input type="hidden" id="booking_id">
            <p>This records the vehicle as returned right now. An admin will then inspect it (fuel, condition, any damage) and settle your security deposit.</p>
            <p class="text-body-secondary small">Note: no refund is given for unused rental days if this is earlier than your scheduled drop-off.</p>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-warning" onclick="confirmReturnEarly(this)">Confirm Return</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Cancel Booking Modal -->
  <div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="cancelBookingModalLabel">Cancel Booking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="cancelBookingForm">
            <input type="hidden" id="cancel_booking_id">
            <div id="cancelBookingDetails" class="mb-3"></div>
            <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
            <div class="alert alert-info">
              <small>
                <i class="fas fa-info-circle"></i> Cancellation Policy (v1.0):
                <ul class="mb-0">
                  <li>Full (100%) refund of the rental fee if you cancel at least 24 hours before pickup</li>
                  <li>50% refund of the rental fee if you cancel within 24 hours of pickup</li>
                  <li>Once the rental has started it can no longer be cancelled</li>
                  <li>Any security deposit already paid is returned in full</li>
                </ul>
              </small>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Booking</button>
          <button type="button" class="btn btn-danger" onclick="confirmCancellation(this)">Confirm Cancellation</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return Receipt Modal -->
  <div class="modal fade" id="returnReceiptModal" tabindex="-1" aria-labelledby="returnReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="returnReceiptModalLabel">Return Recorded</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="returnReceiptContent">
          <!-- Content will be populated dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Leave a Review Modal (Phase 12) -->
  <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="reviewForm" novalidate>
          <div class="modal-header">
            <h5 class="modal-title" id="reviewModalLabel">Leave a Review</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-3" id="reviewVehicleName"></p>
            <div class="mb-3">
              <label class="form-label d-block">Your rating</label>
              <div id="reviewStars" role="radiogroup" aria-label="Rating out of 5 stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button type="button" class="btn btn-link p-1 review-star" data-value="<?= $i ?>" role="radio" aria-checked="false" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                    <i class="far fa-star fa-lg"></i>
                  </button>
                <?php endfor; ?>
              </div>
              <input type="hidden" id="reviewRating" value="">
            </div>
            <div class="mb-2">
              <label class="form-label" for="reviewBody">Your review (optional)</label>
              <textarea class="form-control" id="reviewBody" rows="3" maxlength="2000"></textarea>
            </div>
            <input type="hidden" id="reviewBookingId">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmReviewSubmit(this)">Submit Review</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
  <script>

    // "My Profile" navbar link lands here as transactions.php#profile.
    if (window.location.hash === '#profile') {
      const profileEl = document.getElementById('profile');
      if (profileEl) {
        setTimeout(function () {
          const y = profileEl.getBoundingClientRect().top + window.pageYOffset - 90;
          window.scrollTo({ top: y, behavior: 'smooth' });
        }, 300);
      }
    }

    function showBookingAlert(type, message) {
      const container = document.getElementById('bookingActionAlert');
      if (!container) return;
      container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
          ${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      `;
    }

    function showReturnEarlyModal(bookingId) {
      document.getElementById('booking_id').value = bookingId;
      new bootstrap.Modal(document.getElementById('returnEarlyModal')).show();
    }

    function showCancelModal(bookingId, vehicleTitle, rentalDate) {
      document.getElementById('cancel_booking_id').value = bookingId;
      document.getElementById('cancelBookingDetails').innerHTML = `
        <strong>Vehicle:</strong> ${vehicleTitle}<br>
        <strong>Rental Date:</strong> ${rentalDate}
      `;
      new bootstrap.Modal(document.getElementById('cancelBookingModal')).show();
    }

    function showReviewModal(bookingId, vehicleTitle) {
      document.getElementById('reviewBookingId').value = bookingId;
      document.getElementById('reviewVehicleName').textContent = 'How was your rental of ' + vehicleTitle + '?';
      document.getElementById('reviewRating').value = '';
      document.getElementById('reviewBody').value = '';
      setReviewStars(0);
      new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }

    function setReviewStars(value) {
      document.querySelectorAll('#reviewStars .review-star').forEach(function (btn) {
        const starVal = parseInt(btn.dataset.value, 10);
        const filled = starVal <= value;
        btn.querySelector('i').className = filled ? 'fas fa-star fa-lg' : 'far fa-star fa-lg';
        btn.setAttribute('aria-checked', filled && starVal === value ? 'true' : 'false');
      });
    }

    document.querySelectorAll('#reviewStars .review-star').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const value = parseInt(btn.dataset.value, 10);
        document.getElementById('reviewRating').value = value;
        setReviewStars(value);
      });
    });

    async function confirmReviewSubmit(btn) {
      const rating = document.getElementById('reviewRating').value;
      if (!rating) {
        showBookingAlert('danger', 'Please select a rating.');
        return;
      }

      const $btn = $(btn);
      PMSMotion.setButtonLoading($btn, true);
      try {
        const response = await fetch('submit_review.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            booking_id: document.getElementById('reviewBookingId').value,
            rating: rating,
            body: document.getElementById('reviewBody').value
          })
        });
        const data = await response.json();

        if (data.success) {
          bootstrap.Modal.getInstance(document.getElementById('reviewModal'))?.hide();
          showBookingAlert('success', 'Thank you for your review!');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showBookingAlert('danger', data.error || 'Failed to submit review');
        }
      } catch (error) {
        console.error('Error:', error);
        showBookingAlert('danger', 'Failed to submit review');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    }

    async function confirmCancellation(btn) {
      const bookingId = document.getElementById('cancel_booking_id').value;
      const $btn = $(btn);

      PMSMotion.setButtonLoading($btn, true);
      try {
        const response = await fetch('cancel_booking.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            booking_id: bookingId
          })
        });

        const data = await response.json();

        if (data.success) {
          const modalEl = document.getElementById('cancelBookingModal');
          bootstrap.Modal.getInstance(modalEl)?.hide();
          showBookingAlert('success', data.message || 'Booking cancelled successfully!');
          setTimeout(() => window.location.reload(), 3000);
        } else {
          showBookingAlert('danger', data.error || 'Failed to cancel booking');
        }
      } catch (error) {
        console.error('Error:', error);
        showBookingAlert('danger', 'Failed to process cancellation');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    }

    async function confirmReturnEarly(btn) {
      const bookingId = document.getElementById('booking_id').value;
      const $btn = $(btn);

      PMSMotion.setButtonLoading($btn, true);
      try {
        const response = await fetch('return_early.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            booking_id: bookingId
          })
        });

        const data = await response.json();
        
        if (data.success) {
          // Close the return early modal
          bootstrap.Modal.getInstance(document.getElementById('returnEarlyModal')).hide();

          // Show success alert with receipt
          const receiptModal = new bootstrap.Modal(document.getElementById('returnReceiptModal'));
          document.getElementById('returnReceiptContent').innerHTML = `
            <div class="alert alert-success mb-4">
              <i class="fas fa-check-circle me-2"></i> Return recorded — awaiting inspection.
            </div>
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Return Details</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <p><strong>Vehicle:</strong><br>${data.booking_details.vehicle_name}</p>
                    <p><strong>Booking Reference:</strong><br>${data.booking_details.booking_ref}</p>
                  </div>
                  <div class="col-md-6">
                    <p><strong>Scheduled Return:</strong><br>${data.booking_details.return_date}</p>
                    ${data.booking_details.actual_return_date ?
                      `<p><strong>Actual Return Recorded:</strong><br>${data.booking_details.actual_return_date}</p>` : ''}
                  </div>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                  <small><i class="fas fa-info-circle"></i>
                  An admin will inspect the vehicle (fuel, condition, any damage) and settle your refundable
                  security deposit. You'll be notified once that's done.
                  ${data.booking_details.early_return ? ' No refund is given for unused rental days on an early return.' : ''}
                  </small>
                </div>
              </div>
            </div>
          `;
          receiptModal.show();

          // Reload page after 3 seconds
          setTimeout(() => window.location.reload(), 3000);
        } else {
          showBookingAlert('danger', data.error || 'Failed to return vehicle');
        }
      } catch (error) {
        console.error('Error:', error);
        showBookingAlert('danger', 'Failed to process return');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    }

    // --- Phase 9: pay the rental fee + security deposit ------------------
    $('.js-pay-now').on('click', async function () {
      var $btn = $(this);
      PMSMotion.setButtonLoading($btn, true);
      try {
        var res = await fetch('payment_process.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ booking_id: $btn.data('id') })
        });
        var data = await res.json();
        if (res.ok && data.success && data.checkout_url) {
          window.location.href = data.checkout_url;
          return;
        }
        if (res.ok && data.success) {
          showBookingAlert('success', 'Payment received. Your booking is confirmed.');
          setTimeout(function () { window.location.reload(); }, 1200);
        } else {
          PMSMotion.setButtonLoading($btn, false);
          showBookingAlert('danger', data.error || 'Payment failed. Please try again.');
        }
      } catch (e) {
        PMSMotion.setButtonLoading($btn, false);
        showBookingAlert('danger', 'Payment failed. Please try again.');
      }
    });

    // --- Phase 7: verification documents + address -----------------------
    function reqAlert($el, type, msg) {
      $el.removeClass('d-none alert-success alert-danger')
         .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
         .text(msg);
    }

    $('.js-doc-form').on('submit', async function (e) {
      e.preventDefault();
      const form = this;
      const type = form.dataset.type;
      const input = form.querySelector('input[type=file]');
      const $alert = $('#requirementDocAlert');
      if (!input.files || !input.files.length) {
        reqAlert($alert, 'danger', 'Choose an image first.');
        return;
      }
      const fd = new FormData();
      fd.append('file', input.files[0]);
      const $btn = $(form).find('button[type=submit]');
      PMSMotion.setButtonLoading($btn, true);
      try {
        const res = await fetch('upload_requirement.php?type=' + encodeURIComponent(type), { method: 'POST', body: fd });
        const data = await res.json();
        if (res.ok && data.success) {
          reqAlert($alert, 'success', 'Uploaded. Refreshing…');
          setTimeout(() => window.location.reload(), 800);
        } else {
          reqAlert($alert, 'danger', data.error || 'Upload failed.');
        }
      } catch (err) {
        reqAlert($alert, 'danger', 'Upload failed. Please try again.');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    });

    $('#addressForm').on('submit', async function (e) {
      e.preventDefault();
      const $alert = $('#addressAlert');
      const payload = {};
      $(this).serializeArray().forEach(f => payload[f.name] = f.value);
      if (!payload.address_line1 || !payload.city || !payload.province) {
        reqAlert($alert, 'danger', 'Address line 1, city and province are required.');
        return;
      }
      const $btn = $(this).find('button[type=submit]');
      PMSMotion.setButtonLoading($btn, true);
      try {
        const res = await fetch('update_address.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.success) {
          reqAlert($alert, 'success', 'Address saved. Refreshing…');
          setTimeout(() => window.location.reload(), 800);
        } else {
          reqAlert($alert, 'danger', data.error || 'Could not save the address.');
        }
      } catch (err) {
        reqAlert($alert, 'danger', 'Could not save the address. Please try again.');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    });
  </script>
</body>
</html>
