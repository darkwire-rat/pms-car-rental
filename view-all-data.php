<?php
// Admin transaction list.: - the list-row "Confirm" button is gone.
session_start();
require 'db_connect.php';                 // $conn (mysqli) — edit-times modal path
require 'db.php';                         // $pdo — new filtered query
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/payment.php';
require_once __DIR__ . '/lib/return_flow.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// P8: lazily expire any approved-but-unpaid booking past its 48h window.
$stale = $pdo->query("SELECT id, user_id, status, payment_status, approved_at
                      FROM bookings
                      WHERE status = 'approved' AND payment_status != 'paid'
                        AND approved_at IS NOT NULL AND approved_at < (NOW() - INTERVAL 48 HOUR)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($stale as $b) {
    payment_expire_if_stale($pdo, $b);
}

// Analysis sec 7.3: overdue is derived, never stored.
$overdueNow = $pdo->query("SELECT b.id, b.vehicle_id, b.dropoff_at, v.title AS vehicle_title
                           FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
                           WHERE b.status = 'active' AND b.dropoff_at < NOW()")->fetchAll(PDO::FETCH_ASSOC);
foreach ($overdueNow as $b) {
    return_flow_notify_overdue_once($pdo, array_merge($b, ['status' => 'active']));
}

$paymentStatusValues = ['unpaid', 'partially_paid', 'paid', 'refunded'];
$filterPaymentStatus = array_values(array_intersect((array) ($_GET['payment_status'] ?? []), $paymentStatusValues));
$filterOverdueOnly = isset($_GET['overdue_only']);

// --- Filter inputs -------------------------------------------------------
$statusValues = booking_status_values();
$filterStatus = array_values(array_intersect((array) ($_GET['status'] ?? []), $statusValues));

$vehicleOptions = $pdo->query("SELECT id, title FROM vehicles ORDER BY title")->fetchAll();
$filterVehicle = (int) ($_GET['vehicle_id'] ?? 0);

$filterCustomer  = trim((string) ($_GET['customer'] ?? ''));
$filterRentalFrom = trim((string) ($_GET['rental_from'] ?? ''));
$filterRentalTo   = trim((string) ($_GET['rental_to'] ?? ''));

$isDate = fn($s) => $s !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1;

// --- Build query -------------------------------------------------------
$where = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[] = 'b.status IN (' . implode(',', array_fill(0, count($filterStatus), '?')) . ')';
    $params = array_merge($params, $filterStatus);
}
if ($filterPaymentStatus) {
    $where[] = 'b.payment_status IN (' . implode(',', array_fill(0, count($filterPaymentStatus), '?')) . ')';
    $params = array_merge($params, $filterPaymentStatus);
}
if ($filterVehicle > 0) {
    $where[] = 'b.vehicle_id = ?';
    $params[] = $filterVehicle;
}
if ($filterCustomer !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $filterCustomer . '%';
    $params[] = '%' . $filterCustomer . '%';
}
if ($isDate($filterRentalFrom)) { $where[] = 'b.rental_date >= ?'; $params[] = $filterRentalFrom; }
if ($isDate($filterRentalTo))   { $where[] = 'b.rental_date <= ?'; $params[] = $filterRentalTo; }
if ($filterOverdueOnly)    { $where[] = "b.status = 'active' AND b.dropoff_at < NOW()"; }

$sql = "
    SELECT b.id, b.booking_ref, b.rental_date, b.return_date, b.pickup_time, b.dropoff_time,
           b.dropoff_at, b.status, b.payment_status, b.total_amount, b.vehicle_id,
           u.name AS user_name, v.title AS vehicle_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.rental_date DESC, b.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$activeFilterCount = count(array_filter([
  $filterStatus, $filterVehicle > 0, $filterCustomer !== '',
  $isDate($filterRentalFrom), $isDate($filterRentalTo), $filterPaymentStatus, $filterOverdueOnly,
]));
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
  <title>PMS — All Transactions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">

<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
  <?php $topbarTitle = 'All Transactions'; include 'includes/admin_topbar.php'; ?>

  <div class="container-fluid">
    <div class="card shadow-sm border-0 mt-4">
      <div class="card-header bg-body d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">All Transactions</h2>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel"
                aria-expanded="<?php echo $activeFilterCount ? 'true' : 'false'; ?>" aria-controls="filterPanel">
          <i class="fas fa-filter me-1" aria-hidden="true"></i>Filters<?php echo $activeFilterCount ? ' (' . $activeFilterCount . ')' : ''; ?>
        </button>
      </div>

      <div class="collapse <?php echo $activeFilterCount ? 'show' : ''; ?>" id="filterPanel">
        <div class="card-body border-bottom bg-body-tertiary">
          <form method="get" class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="fCustomer">Customer name or email</label>
              <input type="text" class="form-control" id="fCustomer" name="customer" value="<?php echo htmlspecialchars($filterCustomer, ENT_QUOTES); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fVehicle">Vehicle</label>
              <select class="form-select" id="fVehicle" name="vehicle_id">
                <option value="0">Any vehicle</option>
                <?php foreach ($vehicleOptions as $v): ?>
                  <option value="<?php echo (int) $v['id']; ?>" <?php echo $filterVehicle === (int) $v['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($v['title']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label d-block">Rental date</label>
              <input type="date" class="form-control mb-1" name="rental_from" value="<?php echo htmlspecialchars($filterRentalFrom, ENT_QUOTES); ?>" aria-label="Rental date from">
              <input type="date" class="form-control" name="rental_to" value="<?php echo htmlspecialchars($filterRentalTo, ENT_QUOTES); ?>" aria-label="Rental date to">
            </div>

            <div class="col-12">
              <label class="form-label d-block">Status</label>
              <?php foreach ($statusValues as $s): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="status[]" value="<?php echo $s; ?>"
                         id="fStatus<?php echo $s; ?>" <?php echo in_array($s, $filterStatus, true) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="fStatus<?php echo $s; ?>"><?php echo htmlspecialchars(booking_status_label($s)); ?></label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="col-12">
              <label class="form-label d-block">Payment status</label>
              <?php foreach ($paymentStatusValues as $ps): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="payment_status[]" value="<?php echo $ps; ?>"
                         id="fPay<?php echo $ps; ?>" <?php echo in_array($ps, $filterPaymentStatus, true) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="fPay<?php echo $ps; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $ps))); ?></label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="overdue_only" value="1" id="fOverdueOnly" <?php echo $filterOverdueOnly ? 'checked' : ''; ?>>
                <label class="form-check-label" for="fOverdueOnly">Overdue only <span class="text-body-secondary">(active, past drop-off — derived, not stored)</span></label>
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1" aria-hidden="true"></i>Apply filters</button>
              <a href="view-all-data.php" class="btn btn-outline-secondary">Clear all</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card-body">
        <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="pageAlert">
          <div class="d-flex justify-content-between align-items-start">
            <span class="js-modal-alert-text"></span>
            <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
          </div>
        </div>
        <p class="text-body-secondary small"><?php echo count($transactions); ?> booking<?php echo count($transactions) === 1 ? '' : 's'; ?><?php echo $activeFilterCount ? ' matching the current filters' : ''; ?>.</p>
        <div class="table-responsive">
          <table class="table table-hover" id="transactionsTable">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>User</th>
                <th>Vehicle</th>
                <th>Rental Date</th>
                <th>Return Date</th>
                <th>Pickup Time</th>
                <th>Dropoff Time</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$transactions): ?>
                <tr><td colspan="10" class="text-center text-body-secondary">No transactions found</td></tr>
              <?php endif; ?>
              <?php foreach ($transactions as $t): ?>
                <tr>
                  <td data-order="<?php echo (int) $t['id']; ?>">#<?php echo (int) $t['id']; ?></td>
                  <td><?php echo htmlspecialchars($t['user_name']); ?></td>
                  <td>
                    <?php echo htmlspecialchars($t['vehicle_name']); ?><br>
                    <small class="text-body-secondary"><?php echo (int) $t['vehicle_id']; ?></small>
                  </td>
                  <td><?php echo date('M d, Y', strtotime($t['rental_date'])); ?></td>
                  <td><?php echo date('M d, Y', strtotime($t['return_date'])); ?></td>
                  <td><?php echo date('h:i A', strtotime($t['pickup_time'])); ?></td>
                  <td><?php echo date('h:i A', strtotime($t['dropoff_time'])); ?></td>
                  <td>
                    <span class="badge <?php echo booking_status_badge_class(strtolower($t['status'])); ?>">
                      <?php echo htmlspecialchars(booking_status_label($t['status'])); ?>
                    </span>
                    <span class="badge <?php echo booking_payment_status_badge_class($t['payment_status']); ?>">
                      <?php echo htmlspecialchars(ucfirst($t['payment_status'])); ?>
                    </span>
                    <?php if ($t['status'] === 'active' && strtotime($t['dropoff_at']) < time()): ?>
                      <span class="badge bg-danger">Overdue</span>
                    <?php endif; ?>
                  </td>
                  <td data-order="<?php echo (float) $t['total_amount']; ?>">₱<?php echo number_format((float) $t['total_amount'], 2); ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary me-1" href="admin_transaction_detail.php?id=<?php echo (int) $t['id']; ?>"
                       aria-label="View booking #<?php echo (int) $t['id']; ?>">
                      <i class="fas fa-eye" aria-hidden="true"></i> View
                    </a>

                    <button class="btn btn-sm btn-outline-secondary edit-transaction me-1"
                            data-id="<?php echo (int) $t['id']; ?>"
                            data-pickup="<?php echo htmlspecialchars($t['pickup_time'], ENT_QUOTES); ?>"
                            data-dropoff="<?php echo htmlspecialchars($t['dropoff_time'], ENT_QUOTES); ?>"
                            aria-label="Edit times for booking #<?php echo (int) $t['id']; ?>">
                      <i class="fas fa-clock" aria-hidden="true"></i>
                    </button>

                    <?php if (booking_status_can_hard_delete($t['status'])): ?>
                      <button class="btn btn-sm btn-outline-danger delete-transaction"
                              data-id="<?php echo (int) $t['id']; ?>"
                              aria-label="Delete booking #<?php echo (int) $t['id']; ?>">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTransactionForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="editTransactionModalLabel">Edit Booking Times</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="transaction_id" id="editTransactionId">
            <div class="modal-body">
                <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="js-modal-alert-text"></span>
                        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editPickupTime">Pickup Time</label>
                    <input type="time" class="form-control" name="pickup_time" id="editPickupTime" required>
                    <div class="invalid-feedback" id="editPickupTimeFeedback"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editDropoffTime">Dropoff Time</label>
                    <input type="time" class="form-control" name="dropoff_time" id="editDropoffTime" required>
                    <div class="invalid-feedback" id="editDropoffTimeFeedback"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
<script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
</body>
</html>
