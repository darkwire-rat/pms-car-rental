<?php
require 'db.php'; // connect to database
require_once __DIR__ . '/lib/booking_status.php';
require_once __DIR__ . '/lib/requirements.php';
require_once __DIR__ . '/lib/booking_rules.php';
require_once __DIR__ . '/lib/reviews.php';
?>


<?php session_start();

// Verification-requirement completeness for the current customer, used to gate the booking modal client-side.
$bookingReqStatus = null;
if (!empty($_SESSION['user']['id'])) {
  $bookingReqStatus = requirements_user_status($pdo, (int) $_SESSION['user']['id']);
}

// Resume a vehicle selection that survived the login gate via set_pending_vehicle.php.
$resumeVehicle = null;
if (!empty($_SESSION['user']['id']) && !empty($_SESSION['pending_vehicle_id'])) {
  $stmt = $pdo->prepare("SELECT id, title, thumbnail FROM vehicles WHERE id = ? AND is_active = 1");
  $stmt->execute([(int) $_SESSION['pending_vehicle_id']]);
  $resumeVehicle = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  unset($_SESSION['pending_vehicle_id']);
}

// Phase 8: pickup / drop-off branches for the booking form.
$bookingLocations = booking_active_locations($pdo);
 ?>


<?php
// Sort whitelist — never interpolate $_GET['sort'] directly into SQL
$allowedSorts = [
  'title_asc'  => 'title ASC',
  'title_desc' => 'title DESC',
  'price_asc'  => 'price_per_day ASC',
  'price_desc' => 'price_per_day DESC',
  'newest'     => 'id DESC',
];
$sortParam = $_GET['sort'] ?? 'title_asc';
$orderBy = $allowedSorts[$sortParam] ?? 'title ASC';
if (!isset($allowedSorts[$sortParam])) {
  $sortParam = 'title_asc';
}

// Filter parameters Homepage compatibility: the homepage search widget hands off a plain ?category=suv string.
$rawCategory = $_GET['category'] ?? [];
if (is_string($rawCategory)) {
  $rawCategory = $rawCategory !== '' ? [strtolower(trim($rawCategory))] : [];
} else {
  $rawCategory = array_map(fn($c) => strtolower(trim((string)$c)), (array)$rawCategory);
}
$filterCategories    = $rawCategory;
$filterFuels         = isset($_GET['fuel']) ? (array)$_GET['fuel'] : [];
$filterTransmissions = isset($_GET['transmission']) ? (array)$_GET['transmission'] : [];
$filterPriceMin      = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$filterPriceMax      = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
$filterAvailableOnly = isset($_GET['available_only']);

// Seat buckets — corrected ranges: 2 / 4-6 / 7-8 / 9+.
$validSeatBuckets = ['2', '4-6', '7-8', '9+'];
$filterSeatBuckets = isset($_GET['seats']) ? array_values(array_intersect((array)$_GET['seats'], $validSeatBuckets)) : [];

// Sidebar static data Category counts and seat-bucket counts are computed against the full is_active=1 set.
$allActiveVehicles = $pdo->query("SELECT category, seats FROM vehicles WHERE is_active = 1")->fetchAll();

$categoryCounts = [];
foreach ($allActiveVehicles as $v) {
  $cat = $v['category'];
  $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}
ksort($categoryCounts);

// Price range and distinct fuel/transmission — small read-only lookups on the same $pdo connection.
$priceRange = $pdo->query("SELECT MIN(price_per_day) AS min_price, MAX(price_per_day) AS max_price FROM vehicles WHERE is_active = 1")->fetch();
$fuelOptions = $pdo->query("SELECT DISTINCT fuel FROM vehicles WHERE is_active = 1 ORDER BY fuel")->fetchAll(PDO::FETCH_COLUMN);
$transmissionOptions = $pdo->query("SELECT DISTINCT transmission FROM vehicles WHERE is_active = 1 ORDER BY transmission")->fetchAll(PDO::FETCH_COLUMN);

// Seats: corrected buckets (Decision 2), counts static/unfiltered (Decision 1).
$seatBuckets = [
  '2'   => ['label' => '2 Seats',   'count' => 0],
  '4-6' => ['label' => '4-6 Seats', 'count' => 0],
  '7-8' => ['label' => '7-8 Seats', 'count' => 0],
  '9+'  => ['label' => '9+ Seats',  'count' => 0],
];
foreach ($allActiveVehicles as $v) {
  $seats = (int)$v['seats'];
  if ($seats === 2) {
    $seatBuckets['2']['count']++;
  } elseif ($seats >= 4 && $seats <= 6) {
    $seatBuckets['4-6']['count']++;
  } elseif ($seats >= 7 && $seats <= 8) {
    $seatBuckets['7-8']['count']++;
  } elseif ($seats >= 9) {
    $seatBuckets['9+']['count']++;
  }
}

// Build the filtered vehicle query Every filter value below is bound as a parameter — never string-interpolated.
$where = ['v.is_active = 1'];
$params = [];

if (!empty($filterCategories)) {
  $placeholders = implode(',', array_fill(0, count($filterCategories), '?'));
  $where[] = "LOWER(v.category) IN ($placeholders)";
  $params = array_merge($params, $filterCategories);
}

if (!empty($filterFuels)) {
  $placeholders = implode(',', array_fill(0, count($filterFuels), '?'));
  $where[] = "v.fuel IN ($placeholders)";
  $params = array_merge($params, $filterFuels);
}

if (!empty($filterTransmissions)) {
  $placeholders = implode(',', array_fill(0, count($filterTransmissions), '?'));
  $where[] = "v.transmission IN ($placeholders)";
  $params = array_merge($params, $filterTransmissions);
}

if (!empty($filterSeatBuckets)) {
  $seatClauses = [];
  foreach ($filterSeatBuckets as $bucket) {
    switch ($bucket) {
      case '2':
        $seatClauses[] = 'v.seats = ?';
        $params[] = 2;
        break;
      case '4-6':
        $seatClauses[] = 'v.seats BETWEEN ? AND ?';
        $params[] = 4;
        $params[] = 6;
        break;
      case '7-8':
        $seatClauses[] = 'v.seats BETWEEN ? AND ?';
        $params[] = 7;
        $params[] = 8;
        break;
      case '9+':
        $seatClauses[] = 'v.seats >= ?';
        $params[] = 9;
        break;
    }
  }
  if (!empty($seatClauses)) {
    $where[] = '(' . implode(' OR ', $seatClauses) . ')';
  }
}

if ($filterPriceMin > 0) {
  $where[] = 'v.price_per_day >= ?';
  $params[] = $filterPriceMin;
}

if ($filterPriceMax > 0) {
  $where[] = 'v.price_per_day <= ?';
  $params[] = $filterPriceMax;
}

// Availability filter: moved from a PHP post-filter into the SQL WHERE clause.
if ($filterAvailableOnly) {
  $where[] = '(v.units_total - COALESCE(bc.booked_count, 0)) > 0';
}

$whereSql = implode(' AND ', $where);

// Booked-count join: a single LEFT JOIN against a pre-aggregated subquery.
$holdStatuses = booking_status_holds_inventory();
$holdStatusSql = implode(',', array_map(fn($s) => $pdo->quote($s), $holdStatuses));
$joinSql = "
  LEFT JOIN (
    SELECT vehicle_id, COUNT(*) AS booked_count
    FROM bookings
    WHERE status IN ($holdStatusSql)
      AND pickup_at < NOW()
      AND dropoff_at > NOW()
    GROUP BY vehicle_id
  ) bc ON v.id = bc.vehicle_id
";

// --- Pagination (Step 6) ----------------------------------------------------
$perPage = 9;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Count query — identical filters (incl. availability), no ORDER BY/LIMIT.
$countSql = "SELECT COUNT(*) FROM vehicles v $joinSql WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalVehicles = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalVehicles / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// Data query — single LEFT JOIN, no per-vehicle queries.
$dataSql = "
  SELECT v.*, COALESCE(bc.booked_count, 0) AS booked_count
  FROM vehicles v
  $joinSql
  WHERE $whereSql
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";
$dataParams = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare($dataSql);
$stmt->execute($dataParams);
$vehicles = $stmt->fetchAll();

$vehicleCount = count($vehicles);

// Review aggregates for every active vehicle in one query, same no-N+1 convention as the booked-count join above.
$reviewAggregates = review_aggregates_batch($pdo);

// Pagination URL helper — carries the current filter + sort state forward.
function paginationUrl($page, $params) {
  $params['page'] = $page;
  return 'vehicles.php?' . http_build_query($params);
}

$paginationParams = ['sort' => $sortParam];
if (!empty($filterCategories))    $paginationParams['category'] = $filterCategories;
if (!empty($filterFuels))         $paginationParams['fuel'] = $filterFuels;
if (!empty($filterTransmissions)) $paginationParams['transmission'] = $filterTransmissions;
if (!empty($filterSeatBuckets))   $paginationParams['seats'] = $filterSeatBuckets;
if ($filterPriceMin > 0)          $paginationParams['price_min'] = $filterPriceMin;
if ($filterPriceMax > 0)          $paginationParams['price_max'] = $filterPriceMax;
if ($filterAvailableOnly)         $paginationParams['available_only'] = 1;
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
  <title>PMS Car Rental — Vehicles</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Animate.css for entrance/scroll animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>


<body>
  <?php include 'includes/client_navbar.php'; ?>


  <!-- BOOKING MODAL -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-poppins" id="bookingModalLabel">Book This Vehicle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>


      <div class="modal-body">
        <!-- Same includes/rental_process.php partial as index.php and how-it-works.php. -->
        <p class="mb-3">
          <a class="text-decoration-none small" data-bs-toggle="collapse" href="#bookingProcessPanel" role="button" aria-expanded="false" aria-controls="bookingProcessPanel">
            <i class="fas fa-circle-question me-1" aria-hidden="true"></i>How does this work?
          </a>
        </p>
        <div class="collapse mb-3" id="bookingProcessPanel">
          <div class="border rounded-3 p-3 bg-body-tertiary">
            <?php include 'includes/rental_process.php'; ?>
          </div>
        </div>

        <!-- Alert -->
        <div id="bookingAlert" class="alert d-none" role="alert"></div>


        <form id="bookingForm">
          <input type="hidden" id="vehicle_id">


          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="rental_date">Rental Date</label>
              <input type="date" class="form-control" id="rental_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="return_date">Return Date</label>
              <input type="date" class="form-control" id="return_date" required>
            </div>


            <div class="col-md-6">
              <label class="form-label" for="pickup_time">Pick-up Time</label>
              <input type="time" class="form-control" id="pickup_time" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="dropoff_time">Drop-off Time</label>
              <input type="time" class="form-control" id="dropoff_time" required>
            </div>


            <div class="col-md-6">
              <label class="form-label" for="contact_number">Contact Number</label>
              <input type="tel" class="form-control" id="contact_number" required>
            </div>


            <div class="col-md-3">
              <label class="form-label" for="age">Age</label>
              <input type="number" class="form-control" id="age" required min="18">
            </div>

            <div class="col-md-6">
              <label class="form-label" for="pickup_location">Pick-up Branch</label>
              <select class="form-select" id="pickup_location" required>
                <option value="">Select a branch</option>
                <?php foreach ($bookingLocations as $loc): ?>
                  <option value="<?= (int) $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?> — <?= htmlspecialchars($loc['city']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="dropoff_location">Drop-off Branch</label>
              <select class="form-select" id="dropoff_location" required>
                <option value="">Select a branch</option>
                <?php foreach ($bookingLocations as $loc): ?>
                  <option value="<?= (int) $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?> — <?= htmlspecialchars($loc['city']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Demonstration branches only. A relocation fee applies for different branches (₱0 / ₱300 / ₱500).</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="voucherSelect">Apply Voucher</label>
              <select class="form-select" id="voucherSelect">
                <option value="">Select a voucher (optional)</option>
              </select>
              <div class="form-text">Select a voucher to get instant discount</div>
            </div>


          </div>


          <hr class="my-4">


          <!-- Verification requirements are collected once on the profile and reused across bookings. -->
          <?php $reqComplete = $bookingReqStatus && $bookingReqStatus['complete']; ?>
          <div id="requirementsGate" class="mb-3" data-complete="<?= $reqComplete ? '1' : '0' ?>">
            <?php if ($reqComplete): ?>
              <div class="alert alert-success py-2 mb-0">
                <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
                Driver's licence, verification photo and address are on file.
              </div>
            <?php else: ?>
              <div class="alert alert-warning py-2 mb-0">
                <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
                Before booking, add your driver's licence, a verification photo and your home address on your
                <a href="transactions.php#requirements">profile</a>.
              </div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Rental Policy</label>
            <?php $policyShowCheckbox = true; include 'includes/rental_policy.php'; ?>
          </div>


          <!-- Live booking preview block. -->
          <div id="bookingPreview" class="mb-3 js-booking-reveal">
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Booking Summary</h6>
                <div id="previewContent"></div>
                <!-- amount input lives in the always-visible #amountPaidSection below -->
              </div>
            </div>
          </div>


          <!-- No money is collected here. -->
          <input type="hidden" id="amount_paid" value="0">
          <p class="small text-body-secondary mb-3">
            Nothing is charged now. Once your booking is approved you'll be asked to pay the
            rental fee and a refundable security deposit within 48 hours.
          </p>


          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success rounded-pill" id="btnConfirm" disabled>Submit Booking Request</button>
          </div>
        </form>


        <!-- Receipt -->
        <div id="receiptArea" class="d-none mt-4">
          <h6 class="mb-3 font-poppins">Booking Receipt</h6>
          <div class="card">
            <div class="card-body" id="receiptContent"></div>
          </div>
          <div class="text-center mt-3">
            <span class="fw-bold text-success">Booking Confirmed!</span><br>
            <small class="text-body-secondary">Please present this receipt, license, and 1 valid ID upon pickup.</small>
          </div>
        </div>


      </div>
    </div>
  </div>
</div>


  <!-- VEHICLE DETAILS MODAL -->
  <div class="modal fade" id="vehicleDetailsModal" tabindex="-1" aria-labelledby="vehicleDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-poppins" id="vehicleDetailsModalLabel">Vehicle Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-4">
            <div class="col-12 col-md-5">
              <img id="detailsModalImage" src="" alt="" class="details-modal-img rounded-3 w-100 js-fade-on-load">
            </div>
            <div class="col-12 col-md-7">
              <h4 id="detailsModalTitle" class="mb-1"></h4>
              <span id="detailsModalCategory" class="badge bg-primary rounded-pill mb-2"></span>
              <div class="vehicle-pricebar mb-2">
                <div id="detailsModalPrice" class="price fs-4"></div>
              </div>
              <div class="mb-3">
                <span id="detailsModalAvailability" class="badge"></span>
              </div>
              <div class="vehicle-specs">
                <div><span id="detailsModalSeats"></span></div>
                <div><span id="detailsModalFuel"></span></div>
                <div><span id="detailsModalTransmission"></span></div>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <div class="rental-info-block">
            <div class="section-eyebrow">Rental Information</div>
            <ul class="text-body-secondary small mb-0 ps-3">
              <li>Minimum age: 18 years old</li>
              <li>Valid driver's license required</li>
              <li>Pick-up/drop-off times apply as scheduled</li>
            </ul>
          </div>

          <hr class="my-4">

          <div id="detailsModalReviewsBlock">
            <div class="section-eyebrow">Reviews</div>
            <div id="detailsModalReviewSummary" class="mb-3"></div>
            <div id="detailsModalReviewList"></div>
            <div class="text-center mt-2">
              <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnLoadMoreReviews">Load more reviews</button>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary rounded-pill" id="btnReserveFromDetails">Reserve Now</button>
        </div>
      </div>
    </div>
  </div>




  <main class="container py-5 navbar-offset">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Vehicles</li>
      </ol>
    </nav>
    <div class="text-center mb-4">
      <div class="section-eyebrow">VEHICLES</div>
      <h1 class="h2 fw-bold">Browse Our Vehicles</h1>
      <p class="text-body-secondary">Find the perfect vehicle for your next trip</p>
    </div>
    <button class="btn btn-outline-primary d-lg-none mb-3" type="button"
      data-bs-toggle="offcanvas" data-bs-target="#filterSidebar" aria-controls="filterSidebar">
      <i class="fas fa-sliders-h me-2"></i>Filters
      <span class="badge bg-primary rounded-pill ms-2 d-none" id="activeFilterCount">0</span>
    </button>

    <div class="row">
      <!-- Sidebar filter panel (Step 4) -->
      <div class="col-lg-3">
        <div class="offcanvas-lg offcanvas-start filter-sidebar" tabindex="-1" id="filterSidebar" aria-labelledby="filterSidebarLabel">
          <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="filterSidebarLabel">Filters</h5>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas"
              data-bs-target="#filterSidebar" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body p-3">
            <form method="GET" action="vehicles.php" id="filterForm">
              <div class="filter-group">
                <div class="filter-group-title">Category</div>
                <?php foreach ($categoryCounts as $cat => $count): $catKey = strtolower($cat); ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="category[]" value="<?= htmlspecialchars($catKey) ?>" id="filter_cat_<?= htmlspecialchars($catKey) ?>" <?= in_array($catKey, $filterCategories, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_cat_<?= htmlspecialchars($catKey) ?>"><?= htmlspecialchars(ucfirst($cat)) ?> (<?= (int)$count ?>)</label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Price Range</div>
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label small" for="filter_price_min">Min ₱</label>
                    <input type="number" class="form-control form-control-sm" id="filter_price_min" name="price_min" min="0" value="<?= isset($_GET['price_min']) ? (int)$_GET['price_min'] : (int)($priceRange['min_price'] ?? 0) ?>">
                  </div>
                  <div class="col-6">
                    <label class="form-label small" for="filter_price_max">Max ₱</label>
                    <input type="number" class="form-control form-control-sm" id="filter_price_max" name="price_max" min="0" value="<?= isset($_GET['price_max']) ? (int)$_GET['price_max'] : (int)($priceRange['max_price'] ?? 0) ?>">
                  </div>
                </div>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Seats</div>
                <?php foreach ($seatBuckets as $key => $bucket): $seatId = 'filter_seats_' . str_replace(['+', '-'], ['plus', 'to'], $key); ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="seats[]" value="<?= htmlspecialchars($key) ?>" id="<?= $seatId ?>" <?= in_array($key, $filterSeatBuckets, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="<?= $seatId ?>"><?= htmlspecialchars($bucket['label']) ?> (<?= (int)$bucket['count'] ?>)</label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Fuel Type</div>
                <?php foreach ($fuelOptions as $fuel): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="fuel[]" value="<?= htmlspecialchars($fuel) ?>" id="filter_fuel_<?= htmlspecialchars(strtolower($fuel)) ?>" <?= in_array($fuel, $filterFuels, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_fuel_<?= htmlspecialchars(strtolower($fuel)) ?>"><?= htmlspecialchars($fuel) ?></label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Transmission</div>
                <?php foreach ($transmissionOptions as $trans): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="transmission[]" value="<?= htmlspecialchars($trans) ?>" id="filter_trans_<?= htmlspecialchars(strtolower($trans)) ?>" <?= in_array($trans, $filterTransmissions, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_trans_<?= htmlspecialchars(strtolower($trans)) ?>"><?= htmlspecialchars($trans) ?></label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Availability</div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="available_only" value="1" id="filter_available_only" <?= $filterAvailableOnly ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_available_only">Show available only</label>
                </div>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill">Apply Filters</button>
                <a href="vehicles.php" class="btn btn-link text-decoration-none">Clear All</a>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Results + grid (Step 3, unchanged — only its wrapping container moved) -->
      <div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3" id="resultsBar">
      <div>
        <span class="fw-bold" id="resultsCount"><?= (int)$totalVehicles ?></span> vehicles listed
        <?php if ($totalVehicles > 0): ?>
        <small class="text-body-secondary d-block" id="resultsRange">
          Showing <?= (int)($offset + 1) ?>-<?= (int)min($offset + $perPage, $totalVehicles) ?> of <?= (int)$totalVehicles ?> vehicles
        </small>
        <?php endif; ?>
      </div>
      <select class="form-select form-select-sm w-auto" id="sortSelect" name="sort" aria-label="Sort vehicles">
        <option value="title_asc" <?= $sortParam === 'title_asc' ? 'selected' : '' ?>>Name A-Z</option>
        <option value="title_desc" <?= $sortParam === 'title_desc' ? 'selected' : '' ?>>Name Z-A</option>
        <option value="price_asc" <?= $sortParam === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sortParam === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
        <option value="newest" <?= $sortParam === 'newest' ? 'selected' : '' ?>>Newest First</option>
      </select>
    </div>



    <div class="row g-4" id="carsGrid" data-reveal-stagger="40">
      <?php
      foreach ($vehicles as $car) {
        // Booked count now comes straight from the LEFT JOIN — no more per-vehicle query here.
        $bookedCount = (int)$car['booked_count'];


        // Compute available units
        $available = $car['units_total'] - $bookedCount;
        $isAvailable = $available > 0;

        // This vehicle's review aggregate, or the zero-review default — never NaN, never a fake "0 stars".
        $reviewAgg = $reviewAggregates[(int) $car['id']] ?? ['count' => 0, 'average' => null];


        // Determine availability text
        $statusText = $isAvailable ? 'Available' : 'Unavailable';
        $btnClass = $isAvailable ? 'btn-primary' : 'btn-secondary';


        // Data-reveal is on the .car-card column wrapper, not .vehicle-card itself.
        echo "
  <div class='col-md-4 car-card' data-cat='" . strtolower(htmlspecialchars($car['category'])) . "' data-reveal>
    <div class='vehicle-card bg-body rounded-3 shadow-sm overflow-hidden'>
      <div class='vehicle-img-wrap'>
        <img src='assets/" . htmlspecialchars($car['thumbnail']) . "' alt='" . htmlspecialchars($car['title']) . "' class='vehicle-img' loading='lazy'>
      </div>
      <div class='p-3'>
        <h2 class='h5 mb-1'>" . htmlspecialchars($car['title']) . "</h2>
        <small class='text-body-secondary d-block mb-2'>" . ucfirst(htmlspecialchars($car['category'])) . "</small>
        <div class='mb-2 small'>" . (
          $reviewAgg['count'] > 0
            ? "<i class='fas fa-star text-warning'></i> <strong>" . number_format($reviewAgg['average'], 1) . "</strong> <span class='text-body-secondary'>(" . (int) $reviewAgg['count'] . " review" . ($reviewAgg['count'] === 1 ? '' : 's') . ")</span>"
            : "<span class='text-body-secondary'>No reviews yet</span>"
        ) . "</div>
        <div class='vehicle-specs mb-2'>
          <div><i class='fas fa-users'></i> " . (int)$car['seats'] . " Seats</div>
          <div><i class='fas fa-gas-pump'></i> " . htmlspecialchars($car['fuel']) . "</div>
          <div><i class='fas fa-briefcase'></i> " . htmlspecialchars($car['transmission']) . "</div>
        </div>
        <div class='vehicle-pricebar mb-2'>
          <div class='price'>₱" . number_format($car['price_per_day']) . "</div>
          <small>/ day</small>
        </div>
        <div class='mb-2'>
          <span class='badge " . ($isAvailable ? "bg-success" : "bg-danger") . "'>$statusText</span>
        </div>
        <button type='button' class='btn $btnClass w-100 rounded-pill mt-2 btn-view-details'
          data-id='" . $car['id'] . "'
          data-car='" . htmlspecialchars($car['title']) . "'
          data-rate='" . $car['price_per_day'] . "'
          data-cat='" . htmlspecialchars($car['category']) . "'
          data-seats='" . (int)$car['seats'] . "'
          data-fuel='" . htmlspecialchars($car['fuel']) . "'
          data-transmission='" . htmlspecialchars($car['transmission']) . "'
          data-thumbnail='" . htmlspecialchars($car['thumbnail']) . "'
          data-available='" . (int)$available . "'
          data-available-text='" . htmlspecialchars($statusText) . "'
          data-review-count='" . (int) $reviewAgg['count'] . "'
          data-review-average='" . htmlspecialchars((string) ($reviewAgg['average'] ?? '')) . "'>
          View Car Details
        </button>
      </div>
    </div>
  </div>
  ";
      }
      ?>
    </div>
    <?php if ($vehicleCount === 0): ?>
    <!-- No results message -->
    <p id="noResultsMessage" class="text-center text-body-secondary mt-4">
      No vehicles match your filters.
    </p>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-4">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars(paginationUrl(max(1, $currentPage - 1), $paginationParams)) ?>" aria-label="Previous">&laquo;</a>
        </li>
        <?php
        $windowSize = 5;
        $windowStart = max(1, $currentPage - (int)floor($windowSize / 2));
        $windowEnd = min($totalPages, $windowStart + $windowSize - 1);
        $windowStart = max(1, $windowEnd - $windowSize + 1);

        if ($windowStart > 1):
        ?>
        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars(paginationUrl(1, $paginationParams)) ?>">1</a></li>
        <?php if ($windowStart > 2): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>" <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
          <a class="page-link" href="<?= htmlspecialchars(paginationUrl($p, $paginationParams)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($windowEnd < $totalPages): ?>
        <?php if ($windowEnd < $totalPages - 1): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars(paginationUrl($totalPages, $paginationParams)) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>

        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars(paginationUrl(min($totalPages, $currentPage + 1), $paginationParams)) ?>" aria-label="Next">&raquo;</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
      </div>
    </div>
  </main>


  <?php include 'includes/client_footer.php'; ?>


  <?php include 'includes/auth_modals.php'; ?>


  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js"></script>
  <script>
    window.userLoggedIn = <?= isset($_SESSION['user']) && !empty($_SESSION['user']) ? 'true' : 'false' ?>;
  </script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
  <script src="js/voucher-manager.js?v=<?php echo filemtime(__DIR__ . '/js/voucher-manager.js'); ?>"></script>
  <script src="js/booking-validation.js?v=<?php echo filemtime(__DIR__ . '/js/booking-validation.js'); ?>"></script>
  <script>
    document.getElementById('sortSelect').addEventListener('change', function() {
      const params = new URLSearchParams(window.location.search);
      params.set('sort', this.value);
      params.delete('page');
      window.location.href = window.location.pathname + '?' + params.toString();
    });
  </script>
  <script>
    $(function () {
  let userLoggedIn = false;


  // ✅ Step 1: Check login state from PHP session via me.php
  async function checkLoginStatus() {
    try {
      const res = await fetch("me.php");
      const data = await res.json();
      userLoggedIn = data.logged_in;
    } catch (err) {
      console.error("Error checking login:", err);
      userLoggedIn = false;
    }
  }


  // Run on load
  checkLoginStatus();

    // The in-modal licence upload field was removed.

  // ✅ View Car Details: populate and open the details modal (no auth check)
  $(document).on("click", ".btn-view-details", function () {
    const $btn = $(this);

    const carName = $btn.data("car");
    const rate = $btn.data("rate");
    const cat = $btn.data("cat");
    const seats = $btn.data("seats");
    const fuel = $btn.data("fuel");
    const transmission = $btn.data("transmission");
    const thumbnail = $btn.data("thumbnail");
    const available = $btn.data("available");
    const availableText = $btn.data("available-text");

    // Reset fade-in state before swapping src so re-opening with a different vehicle fades in fresh instead of showing the previous.
    const detailsImgEl = document.getElementById("detailsModalImage");
    detailsImgEl.classList.remove("loaded");
    detailsImgEl.onload = function () {
      detailsImgEl.classList.add("loaded");
    };
    $("#detailsModalImage").attr("src", "assets/" + thumbnail).attr("alt", carName);
    $("#detailsModalTitle").text(carName);
    $("#detailsModalCategory").text(cat);
    $("#detailsModalPrice").text("₱" + Number(rate).toLocaleString() + " / day");
    $("#detailsModalSeats").html('<i class="fas fa-users"></i> ' + seats + " Seats");
    $("#detailsModalFuel").html('<i class="fas fa-gas-pump"></i> ' + fuel);
    $("#detailsModalTransmission").html('<i class="fas fa-briefcase"></i> ' + transmission);

    $("#detailsModalAvailability")
      .text(availableText)
      .removeClass("bg-success bg-danger")
      .addClass(available > 0 ? "bg-success" : "bg-danger");

    $("#vehicleDetailsModal")
      .data("vehicleId", $btn.data("id"))
      .data("vehicleName", carName)
      .data("vehicleThumbnail", thumbnail);

    $("#btnReserveFromDetails").prop("disabled", !(available > 0));

    loadVehicleReviews($btn.data("id"), 1, false);

    $("#vehicleDetailsModal").modal("show");
  });

  // Escapes review text before it ever touches innerHTML.
  function escapeHtml(str) {
    return $("<div>").text(str || "").html();
  }

  function renderStars(rating) {
    let html = "";
    for (let i = 1; i <= 5; i++) {
      html += "<i class='" + (i <= rating ? "fas" : "far") + " fa-star text-warning'></i>";
    }
    return html;
  }

  function loadVehicleReviews(vehicleId, page, append) {
    $("#detailsModalReviewList").data("vehicleId", vehicleId);
    fetch("vehicle_reviews_list.php?vehicle_id=" + encodeURIComponent(vehicleId) + "&page=" + page)
      .then((r) => r.json())
      .then(function (data) {
        if (!data.success) return;

        if (!append) {
          const agg = data.aggregate;
          if (agg.count > 0) {
            const dist = agg.distribution;
            let bars = "";
            for (let star = 5; star >= 1; star--) {
              const pct = agg.count > 0 ? Math.round((dist[star] / agg.count) * 100) : 0;
              bars += `
                <div class="d-flex align-items-center gap-2 small mb-1">
                  <span style="width:38px;">${star} <i class="fas fa-star text-warning"></i></span>
                  <div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar bg-warning" style="width:${pct}%"></div></div>
                  <span class="text-body-secondary" style="width:28px;">${dist[star]}</span>
                </div>`;
            }
            $("#detailsModalReviewSummary").html(`
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="fs-4 fw-bold">${agg.average.toFixed(1)}</span>
                <span>${renderStars(Math.round(agg.average))}</span>
                <span class="text-body-secondary">(${agg.count} review${agg.count === 1 ? "" : "s"})</span>
              </div>
              ${bars}
            `);
          } else {
            $("#detailsModalReviewSummary").html('<p class="text-body-secondary mb-0">No reviews yet.</p>');
          }
          $("#detailsModalReviewList").empty();
        }

        data.reviews.forEach(function (r) {
          const date = new Date(r.created_at).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
          const adminResponseHtml = r.admin_response
            ? `<div class="mt-2 ps-3 border-start border-2">
                 <div class="small text-body-secondary fw-semibold">Response from PMS</div>
                 <div class="small">${escapeHtml(r.admin_response)}</div>
               </div>`
            : "";
          $("#detailsModalReviewList").append(`
            <div class="border-bottom pb-2 mb-2">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <span>${renderStars(r.rating)}</span>
                  <strong class="ms-2">${escapeHtml(r.attribution)}</strong>
                </div>
                <small class="text-body-secondary">${date}</small>
              </div>
              ${r.body ? `<p class="mb-0 mt-1">${escapeHtml(r.body)}</p>` : ""}
              ${adminResponseHtml}
            </div>
          `);
        });

        $("#detailsModalReviewList").data("page", data.page);
        $("#btnLoadMoreReviews").toggleClass("d-none", data.page >= data.total_pages);
      })
      .catch(function () {
        $("#detailsModalReviewSummary").html('<p class="text-body-secondary mb-0">Reviews could not be loaded.</p>');
      });
  }

  $("#btnLoadMoreReviews").on("click", function () {
    const vehicleId = $("#detailsModalReviewList").data("vehicleId");
    const nextPage = ($("#detailsModalReviewList").data("page") || 1) + 1;
    loadVehicleReviews(vehicleId, nextPage, true);
  });


  // The actual booking-modal-opening sequence.
  function openBookingModalFor(vehicleId, carName, carThumbnail) {
    $("#vehicle_id").val(vehicleId);
    $("#bookingModalLabel").text("Book: " + carName);
    $("#bookingModal").data("vehicleName", carName).data("vehicleThumbnail", carThumbnail);

    $("#bookingForm")[0].reset();
    $("#bookingForm").removeClass("d-none");
    $("#bookingAlert").addClass("d-none").text("");
    $("#previewContent").empty();
    $("#bookingPreview").removeClass("is-visible");
    $("#amount_paid").prop("required", false);
    $("#btnConfirm").prop("disabled", true);
    $("#receiptArea").addClass("d-none");
    $("#receiptContent").empty();

    $("#bookingModal").modal("show");
  }

  // ✅ Step 3/4: Reserve Now (from details modal) → auth-gated hand off
  $("#btnReserveFromDetails").on("click", async function () {
    const vehicleId = $("#vehicleDetailsModal").data("vehicleId");
    const carName = $("#vehicleDetailsModal").data("vehicleName");
    const carThumbnail = $("#vehicleDetailsModal").data("vehicleThumbnail");

    await checkLoginStatus();

    $("#vehicleDetailsModal").one("hidden.bs.modal", function () {
      if (!userLoggedIn) {
        // The vehicle selection used to be lost here.
        fetch("set_pending_vehicle.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "vehicle_id=" + encodeURIComponent(vehicleId)
        }).finally(function () {
          $("#loginModal").modal("show");
        });
        return;
      }

      openBookingModalFor(vehicleId, carName, carThumbnail);
    });

    $("#vehicleDetailsModal").modal("hide");
  });

  <?php if ($resumeVehicle): ?>
  // A pending selection survived the login gate -- resume it automatically, once, on this fresh page load.
  $(function () {
    openBookingModalFor(
      <?php echo (int) $resumeVehicle['id']; ?>,
      <?php echo json_encode($resumeVehicle['title']); ?>,
      <?php echo json_encode($resumeVehicle['thumbnail']); ?>
    );
  });
  <?php endif; ?>


  // Was removed in the it was a long-dead duplicate of js/app.js's preview handler.

  // ✅ Step 4: Confirm booking → reserve.php
  $("#bookingForm").on("submit", async function (e) {
    if ($('#btnConfirm').prop('disabled')) { e.preventDefault(); return; }
    e.preventDefault();


    const vehicle_id = $("#vehicle_id").val();
    const rental_date = $("#rental_date").val();
    const return_date = $("#return_date").val();
    const pickup_time = $("#pickup_time").val();
    const dropoff_time = $("#dropoff_time").val();
    const contact_number = $("#contact_number").val();
    const voucher_code = $("#voucherSelect").val().trim();
    const age = $("#age").val();
    // Phase 9: no payment at booking time — the "Amount to Pay" field is gone.


    if (age < 18) {
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("You must be at least 18 years old to rent a car.");
      return;
    }


    // Policy acceptance is required; the licence/photo/address gate is shown above.
    if (!$('#policy_accepted').is(':checked')) {
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("Please read and accept the rental policy.");
      return;
    }
    if ($('#requirementsGate').data('complete') !== 1) {
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("Add your licence, verification photo and address on your profile before booking.");
      return;
    }

    try {
      const res = await fetch("reserve.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          vehicle_id,
          rental_date,
          return_date,
          pickup_time,
          dropoff_time,
          contact_number,
          voucher_code,
          age,
          pickup_location_id: $('#pickup_location').val() || 0,
          dropoff_location_id: $('#dropoff_location').val() || 0,
          policy_accepted: true,
          policy_version: $('#policy_version').val()
        }),
      });

      const data = await res.json();


      if (!res.ok || data.error) {
        $("#bookingAlert")
          .removeClass("d-none alert-success")
          .addClass("alert-danger")
          .text(data.error || "Booking failed.");
        return;
      }


      // ✅ Show receipt and trigger printing
      $("#bookingForm").addClass("d-none");
      $("#receiptArea").removeClass("d-none");
      $("#receiptContent").html(`
        <p><strong>Booking Reference:</strong> ${data.booking_ref}</p>
        <p><strong>Vehicle:</strong> ${data.vehicle_name}</p>
        <p><strong>Rental Period:</strong> ${data.rental_date} to ${data.return_date}</p>
        <p><strong>Pick-up Time:</strong> ${data.pickup_time}</p>
        <p><strong>Drop-off Time:</strong> ${data.dropoff_time}</p>
        ${data.voucher_code ? `<p><strong>Voucher Applied:</strong> ${data.voucher_code}</p>` : ''}
        <p><strong>Days:</strong> ${data.days}</p>
        <p><strong>Rate per Day:</strong> ₱${data.rate.toLocaleString()}</p>
        <p><strong>Subtotal:</strong> ₱${data.subtotal.toLocaleString()}</p>
        ${data.discount > 0 ? `<p><strong>Discount:</strong> ₱${data.discount.toLocaleString()}</p>` : ''}
        <p><strong>Rental Total:</strong> ₱${data.total.toLocaleString()}</p>
        <p><strong>Security Deposit (refundable):</strong> ₱${Number(data.security_deposit || 0).toLocaleString()}</p>
        <p><strong>Due after approval:</strong> ₱${Number(data.amount_due_at_payment || 0).toLocaleString()}</p>
        <p class="text-success fw-bold">Booking request submitted!</p>
      `);

      // Redirect to the confirmation page.
      const bookingId = data.booking_id || null;
      const ref = data.booking_ref || null;
      if (bookingId) {
        window.location.href = 'receipt.php?id=' + encodeURIComponent(bookingId);
      } else if (ref) {
        window.location.href = 'receipt.php?ref=' + encodeURIComponent(ref);
      } else {
        setTimeout(() => window.location.href = "transactions.php", 1500);
      }


    } catch (err) {
      console.error(err);
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("Server error. Please try again.");
    }
  });
});
  </script>
</body>
</html>

