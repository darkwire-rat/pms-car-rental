<?php
session_start();
include 'db_connect.php'; // create this file once, it holds your DB credentials
require 'db.php'; // $pdo — Phase 12 review summary; view-all-data.php already establishes the pattern of loading both connections together
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/reviews.php';
require_once __DIR__ . '/lib/reporting.php';

// Optional: check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header('Location: admin-login.php');
  exit;
}

// Phase 12: review summary for the dashboard card that replaced Recent Messages.
$reviewSummary = review_admin_summary($pdo);

// Business Overview — real revenue, every booking status, and a real top-10.
$reportMonth = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) ? $_GET['month'] : date('Y-m');
$reportCategory = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
$reportStatuses = isset($_GET['statuses']) && is_array($_GET['statuses'])
  ? array_values(array_intersect($_GET['statuses'], booking_status_values()))
  : [];
if ($reportStatuses === []) {
  $reportStatuses = null; // no selection = no restriction, same convention admin_dashboard_data.php uses
}
$metricSet = $_GET['metric_set'] ?? 'fleet';
if (!is_string($metricSet) || !in_array($metricSet, ['fleet', 'bookings', 'finance'], true)) {
  $metricSet = 'fleet';
}
$dashboardMetrics = reporting_dashboard_metric_cards($pdo, $metricSet);

$revenueByMonth = reporting_revenue_by_month($pdo, $reportMonth, 12, $reportStatuses);
$bookingsByStatus = reporting_bookings_by_status($pdo, $reportMonth);
$topVehicles = reporting_top_vehicles($pdo, $reportMonth, $reportCategory, 10);
$vehicleCategories = $pdo->query("SELECT DISTINCT category FROM vehicles ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$totalStatusBookings = array_sum($bookingsByStatus);
$totalRevenueWindow = array_sum($revenueByMonth);
$statusChartColors = reporting_status_chart_colors();

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
  <title>PMS Car Rental — Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body class="bg-body-tertiary">
  <div class="d-flex min-vh-100" id="adminLayout">
    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main -->
    <main class="flex-grow-1">

      <!-- Topbar -->
      <?php $topbarTitle = 'Admin Dashboard'; include 'includes/admin_topbar.php'; ?>

      <!-- Metrics -->
      <div class="container py-4">
        <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="dashboardAlert">
          <div class="d-flex justify-content-between align-items-start">
            <span class="js-modal-alert-text"></span>
            <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
          </div>
        </div>
        <p class="text-body-secondary small mb-2">
          <i class="fas fa-clock me-1" aria-hidden="true"></i>
          Dashboard statistics can be switched between fleet, booking, and financial views.
        </p>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
          <div>
            <label for="dashboardMetricSet" class="form-label small mb-1">Statistics</label>
            <select class="form-select form-select-sm" id="dashboardMetricSet" name="metric_set" style="max-width:220px;">
              <option value="fleet" <?php echo $metricSet === 'fleet' ? 'selected' : ''; ?>>Fleet overview</option>
              <option value="bookings" <?php echo $metricSet === 'bookings' ? 'selected' : ''; ?>>Booking overview</option>
              <option value="finance" <?php echo $metricSet === 'finance' ? 'selected' : ''; ?>>Financial overview</option>
            </select>
          </div>
          <span class="text-body-secondary small" id="dashboardMetricStatus">Updated now</span>
        </div>
        <div class="row g-3 mb-4" id="dashboardMetricCards" data-reveal-stagger>
          <?php foreach ($dashboardMetrics as $metric): ?>
            <div class="col-12 col-sm-6 col-xl-3" data-reveal>
              <div class="card metric-card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <div class="fs-2 text-<?php echo htmlspecialchars($metric['color']); ?>"><i class="fa <?php echo htmlspecialchars($metric['icon']); ?>" aria-hidden="true"></i></div>
                  <div class="fw-bold fs-4 js-dashboard-metric-value" data-value="<?php echo htmlspecialchars((string) $metric['value'], ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($metric['prefix'] ?? ''); ?><?php echo is_float($metric['value']) ? number_format($metric['value'], 2) : (int) $metric['value']; ?><?php echo htmlspecialchars($metric['suffix'] ?? ''); ?>
                  </div>
                  <div class="text-body-secondary js-dashboard-metric-label"><?php echo htmlspecialchars($metric['label']); ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Manage Vehicles and User Accounts quick-link cards removed per request -->

        <!-- ====================== Recent Transactions Table ====================== -->
        <div class="card shadow-sm border-0">
          <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Recent Transactions</h2></div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Car</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Pickup Time</th>
                  <th>Dropoff Time</th>
                  <th>Days</th>
                  <th>Amount</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $result = $conn->query("SELECT t.id, u.name AS user, v.title AS car, t.rental_date, t.status, t.payment_status,
                t.pickup_time, t.dropoff_time, t.days, t.total_amount
                        FROM bookings t
                        JOIN users u ON t.user_id = u.id 
                        JOIN vehicles v ON t.vehicle_id = v.id 
                        ORDER BY t.id DESC 
                        LIMIT 5");
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    // Label/badge mapping centralized in lib/booking_status.php rather than reimplemented per file.
                    $badgeClass = booking_status_badge_class(strtolower($row['status']));

          // Approval moved off the dashboard row into the reviewed detail page.
          $actions = '<a class="btn btn-sm btn-outline-primary" href="admin_transaction_detail.php?id='
            . (int) $row['id'] . '"><i class="fas fa-eye me-1" aria-hidden="true"></i>View</a>';

                    echo '<tr>'
                      . '<td>' . htmlspecialchars($row['id']) . '</td>'
                      . '<td>' . htmlspecialchars($row['user']) . '</td>'
                      . '<td>' . htmlspecialchars($row['car']) . '</td>'
                      . '<td>' . htmlspecialchars($row['rental_date']) . '</td>'
                      . '<td><span class="badge ' . $badgeClass . '">' . htmlspecialchars(booking_status_label($row['status'])) . '</span> '
                        . '<span class="badge ' . booking_payment_status_badge_class($row['payment_status']) . '">' . htmlspecialchars(ucfirst($row['payment_status'])) . '</span></td>'
                      . '<td>' . htmlspecialchars($row['pickup_time']) . '</td>'
                      . '<td>' . htmlspecialchars($row['dropoff_time']) . '</td>'
                      . '<td>' . htmlspecialchars($row['days']) . '</td>'
                      . '<td>' . htmlspecialchars($row['total_amount']) . '</td>'
                      . '<td>' . $actions . '</td>'
                      . '</tr>';
                  }
                } else {
                  echo "<tr><td colspan='10' class='text-center text-body-secondary'>No transactions found</td></tr>";
                }
                ?>
              </tbody>
            </table>
            <div class="card-footer bg-body">
              <a href="view-all-data.php?type=transactions" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-list me-1"></i> View All Transactions
              </a>
          </div>
          </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
          <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Reviews</h2></div>
          <div class="card-body">
            <?php if ($reviewSummary['count'] > 0): ?>
              <div class="row g-4">
                <div class="col-md-4 text-center text-md-start">
                  <div class="fs-2 fw-bold"><?= number_format($reviewSummary['average'], 1) ?></div>
                  <div>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="<?= $i <= round($reviewSummary['average']) ? 'fas' : 'far' ?> fa-star text-warning"></i>
                    <?php endfor; ?>
                  </div>
                  <div class="text-body-secondary small"><?= (int) $reviewSummary['count'] ?> review<?= $reviewSummary['count'] === 1 ? '' : 's' ?></div>
                </div>
                <div class="col-md-4">
                  <?php for ($star = 5; $star >= 1; $star--):
                    $pct = $reviewSummary['count'] > 0 ? round($reviewSummary['distribution'][$star] / $reviewSummary['count'] * 100) : 0;
                  ?>
                    <div class="d-flex align-items-center gap-2 small mb-1">
                      <span style="width:34px;"><?= $star ?> <i class="fas fa-star text-warning"></i></span>
                      <div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div></div>
                      <span class="text-body-secondary" style="width:24px;"><?= (int) $reviewSummary['distribution'][$star] ?></span>
                    </div>
                  <?php endfor; ?>
                </div>
                <div class="col-md-4">
                  <div class="fw-semibold small mb-2">Recent Reviews</div>
                  <?php foreach ($reviewSummary['recent'] as $rec): ?>
                    <div class="mb-2 pb-2 border-bottom small">
                      <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="<?= $i <= (int) $rec['rating'] ? 'fas' : 'far' ?> fa-star text-warning" style="font-size:.75rem;"></i>
                        <?php endfor; ?>
                        <strong><?= htmlspecialchars($rec['vehicle_title']) ?></strong>
                      </div>
                      <div class="text-body-secondary"><?= htmlspecialchars($rec['attribution']) ?> — <?= htmlspecialchars(date('M j', strtotime($rec['created_at']))) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <p class="text-body-secondary mb-0">No reviews yet.</p>
            <?php endif; ?>
          </div>
          <div class="card-footer bg-body">
            <a href="admin_reviews.php" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-star me-1"></i> View All Reviews
            </a>
          </div>
        </div>

        <!-- ====================== Business Overview filter ====================== -->
        <!-- Analysis sec 5.1: "Selected period" -- shared across all three charts below, unlike the "Right now" cards above. -->
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-body py-3">
            <form id="dashboardFilterForm" class="row g-2 align-items-end" novalidate>
              <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="filterMonth">Period</label>
                <input type="month" class="form-control form-control-sm" id="filterMonth" name="month" value="<?php echo htmlspecialchars($reportMonth); ?>">
              </div>
              <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="filterCategory">Category <span class="text-body-secondary d-none d-md-inline">(Top Vehicles)</span></label>
                <select class="form-select form-select-sm" id="filterCategory" name="category">
                  <option value="">All categories</option>
                  <?php foreach ($vehicleCategories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $reportCategory === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-8 col-md-auto">
                <label class="form-label small mb-1" for="filterStatuses">Revenue includes <span class="text-body-secondary">(blank = all)</span></label>
                <select class="form-select form-select-sm" id="filterStatuses" name="statuses[]" multiple size="3" style="min-width:170px;">
                  <?php foreach (booking_status_values() as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo ($reportStatuses !== null && in_array($s, $reportStatuses, true)) ? 'selected' : ''; ?>><?php echo htmlspecialchars(booking_status_label($s)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-4 col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary" id="btnApplyDashboardFilter">
                  <i class="fas fa-filter me-1" aria-hidden="true"></i>Apply
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- ====================== Business Overview ====================== -->
        <div class="row g-3 mt-1 business-overview" data-reveal-stagger>
          <div class="col-12 col-lg-4" data-reveal>
            <div class="card metric-card shadow-sm border-0 h-100">
              <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Revenue by Month</h2></div>
              <div class="card-body">
                <p class="text-body-secondary small mb-2" id="revenueEmptyState" <?php echo $totalRevenueWindow != 0 ? 'hidden' : ''; ?>>No revenue recorded in this window.</p>
                <canvas id="revenueChart" height="220" role="img" aria-label="Revenue by month, trailing 12 months, vertical bar chart. A data table with the same figures follows for screen readers."></canvas>
                <table class="visually-hidden">
                  <caption id="revenueCaption">Revenue by month (trailing 12 months, ending <?= htmlspecialchars(date('M Y', strtotime($reportMonth . '-01'))) ?>)</caption>
                  <thead><tr><th scope="col">Month</th><th scope="col">Revenue</th></tr></thead>
                  <tbody id="revenueTableBody">
                    <?php foreach ($revenueByMonth as $ym => $amount): ?>
                      <tr><td><?= htmlspecialchars(date('M Y', strtotime($ym . '-01'))) ?></td><td>₱<?= number_format($amount, 2) ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4" data-reveal>
            <div class="card metric-card shadow-sm border-0 h-100">
              <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Bookings by Status</h2></div>
              <div class="card-body">
                <p class="text-body-secondary small mb-2" id="statusEmptyState" <?php echo $totalStatusBookings !== 0 ? 'hidden' : ''; ?>>No bookings recorded in <span id="statusEmptyMonth"><?= htmlspecialchars(date('F Y', strtotime($reportMonth . '-01'))) ?></span>.</p>
                <canvas id="statusChart" height="220" role="img" aria-label="Bookings by status for <?= htmlspecialchars(date('F Y', strtotime($reportMonth . '-01'))) ?>, doughnut chart. A data table with the same figures follows for screen readers."></canvas>
                <table class="visually-hidden">
                  <caption id="statusCaption">Bookings by status (<?= htmlspecialchars(date('F Y', strtotime($reportMonth . '-01'))) ?>)</caption>
                  <thead><tr><th scope="col">Status</th><th scope="col">Count</th></tr></thead>
                  <tbody id="statusTableBody">
                    <?php foreach ($bookingsByStatus as $status => $count): ?>
                      <tr><td><?= htmlspecialchars(booking_status_label($status)) ?></td><td><?= (int) $count ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4" data-reveal>
            <div class="card metric-card shadow-sm border-0 h-100">
              <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Top Vehicles by Bookings</h2></div>
              <div class="card-body">
                <p class="text-body-secondary small mb-2" id="topVehiclesEmptyState" <?php echo !empty($topVehicles) ? 'hidden' : ''; ?>>No bookings recorded in <span id="topVehiclesEmptyMonth"><?= htmlspecialchars(date('F Y', strtotime($reportMonth . '-01'))) ?></span>.</p>
                <canvas id="topVehiclesChart" height="260" role="img" aria-label="Top vehicles by bookings for <?= htmlspecialchars(date('F Y', strtotime($reportMonth . '-01'))) ?>, horizontal bar chart. A data table with the same figures follows for screen readers."></canvas>
                <table class="visually-hidden">
                  <caption id="topVehiclesCaption">Top vehicles by bookings (<?= htmlspecialchars(date('F Y', strtotime($reportMonth . '-01'))) ?>)</caption>
                  <thead><tr><th scope="col">Rank</th><th scope="col">Vehicle</th><th scope="col">Bookings</th></tr></thead>
                  <tbody id="topVehiclesTableBody">
                    <?php foreach ($topVehicles as $i => $row): ?>
                      <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($row['title']) ?></td><td><?= (int) $row['total'] ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <script>
          window.PMS_DASHBOARD_CHARTS = {
            revenue: <?php echo json_encode(array_map(fn($ym) => date('M Y', strtotime($ym . '-01')), array_keys($revenueByMonth))); ?>,
            revenueData: <?php echo json_encode(array_values($revenueByMonth)); ?>,
            statusLabels: <?php echo json_encode(array_map('booking_status_label', array_keys($bookingsByStatus))); ?>,
            statusData: <?php echo json_encode(array_values($bookingsByStatus)); ?>,
            statusColors: <?php echo json_encode(array_map(fn($s) => $statusChartColors[$s] ?? '#adb5bd', array_keys($bookingsByStatus))); ?>,
            topVehicleLabels: <?php echo json_encode(array_column($topVehicles, 'title')); ?>,
            topVehicleData: <?php echo json_encode(array_map('intval', array_column($topVehicles, 'total'))); ?>
          };
        </script>

        <!-- Manage Vehicles and Users are now on their dedicated pages -->
      </div>
    </main>
  </div>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
  <script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
</body>

</html>