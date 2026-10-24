<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
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
  <title>PMS — Manage Vehicles</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">
<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
<?php
$topbarTitle = 'Manage Vehicles';
$topbarActions = '<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addVehicleModal"><i class="fa fa-plus" aria-hidden="true"></i> Add Vehicle</button>';
include 'includes/admin_topbar.php';
?>
<div class="container py-4">
  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_GET['error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Vehicle added.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Vehicle updated.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">Vehicle deleted.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle" id="vehiclesTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Category</th>
              <th>Price/Day</th>
              <th>Units</th>
              <th>Status</th>
              <th>Image</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $result = $conn->query("SELECT * FROM vehicles ORDER BY id DESC");
            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $badge = $row['is_active'] == 1
                  ? "<span class='badge bg-success'>Available</span>"
                  : "<span class='badge bg-danger'>Unavailable</span>";

                $title = htmlspecialchars($row['title'], ENT_QUOTES);
                echo '<tr>';
                echo '<td data-order="' . (int) $row['id'] . '">' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['title']) . '</td>';
                echo '<td>' . htmlspecialchars($row['category']) . '</td>';
                echo '<td>₱' . htmlspecialchars($row['price_per_day']) . '</td>';
                echo '<td>' . htmlspecialchars($row['units_total']) . '</td>';
                echo '<td>' . $badge . '</td>';
                echo "<td><img src='assets/" . htmlspecialchars($row['thumbnail']) . "' alt='" . $title . "' style='width:70px;height:45px;object-fit:cover;'></td>";
                echo '<td>';
                echo '<button class="btn btn-sm btn-warning editVehicleBtn" '
                     . 'aria-label="Edit ' . $title . '" '
                     . 'data-id="' . htmlspecialchars($row['id']) . '" '
                     . 'data-title="' . htmlspecialchars($row['title'], ENT_QUOTES) . '" '
                     . 'data-category="' . htmlspecialchars($row['category'], ENT_QUOTES) . '" '
                     . 'data-price="' . htmlspecialchars($row['price_per_day'], ENT_QUOTES) . '" '
                     . 'data-units="' . htmlspecialchars($row['units_total'], ENT_QUOTES) . '" '
                     . 'data-fuel="' . htmlspecialchars($row['fuel'], ENT_QUOTES) . '" '
                     . 'data-transmission="' . htmlspecialchars($row['transmission'], ENT_QUOTES) . '" '
                     . 'data-seats="' . htmlspecialchars($row['seats'], ENT_QUOTES) . '" '
                     . 'data-active="' . htmlspecialchars($row['is_active'], ENT_QUOTES) . '">'
                     . '<i class="fa fa-edit"></i> Edit</button>';
                echo '<a href="admin_delete_vehicle.php?id=' . urlencode($row['id']) . '" class="btn btn-sm btn-danger delete-vehicle" '
                     . 'aria-label="Delete ' . $title . '" '
                     . 'data-title="' . $title . '">Delete</a>';
                echo '</td>';
                echo '</tr>';
              }
            } else {
              echo "<tr><td colspan='8' class='text-center text-body-secondary'>No vehicles found</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</main> <!-- End flex-grow-1 -->

<!-- Add Vehicle Modal -->
<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="addVehicleForm" action="admin_add_vehicle.php" method="POST" enctype="multipart/form-data" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
            <div class="d-flex justify-content-between align-items-start">
              <span class="js-modal-alert-text"></span>
              <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="addTitle">Car Title</label>
              <input type="text" name="title" id="addTitle" class="form-control" placeholder="e.g. Toyota Vios 1.3 XLE CVT" required>
              <div class="invalid-feedback" id="addTitleFeedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select name="category" class="form-select" required>
                <option value="">Select Category</option>
                <option value="Sedan">Sedan</option>
                <option value="SUV">SUV</option>
                <option value="Van">Van</option>
                <option value="Minivan">Minivan</option>
                <option value="Scooter">Scooter</option>
                <option value="Pickup">Pickup</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="addPrice">Price per Day (₱)</label>
              <input type="number" name="price_per_day" id="addPrice" class="form-control" required>
              <div class="invalid-feedback" id="addPriceFeedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="addUnits">Available Units</label>
              <input type="number" name="units_total" id="addUnits" class="form-control" required>
              <div class="invalid-feedback" id="addUnitsFeedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="addSeats">Seats</label>
              <input type="number" name="seats" id="addSeats" class="form-control" required>
              <div class="invalid-feedback" id="addSeatsFeedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fuel Type</label>
              <select name="fuel" class="form-select" required>
                <option value="">Select Fuel Type</option>
                <option value="Gasoline">Gasoline</option>
                <option value="Diesel">Diesel</option>
                <option value="Electric">Electric</option>
                <option value="Hybrid">Hybrid</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Transmission Type</label>
              <select name="transmission" class="form-select" required>
                <option value="">Select Transmission Type</option>
                <option value="Automatic">Automatic</option>
                <option value="Manual">Manual</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Thumbnail Image</label>
              <input type="file" name="thumbnail" class="form-control" accept="image/*" required>
            </div>
            <div class="col-12 form-check mt-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
              <label class="form-check-label" for="is_active">Active</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Vehicle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="editVehicleForm" action="admin_edit_vehicle.php" method="POST" enctype="multipart/form-data" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
            <div class="d-flex justify-content-between align-items-start">
              <span class="js-modal-alert-text"></span>
              <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
            </div>
          </div>
          <input type="hidden" name="id" id="editVehicleId">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="editTitle">Car Name</label>
              <input type="text" name="title" id="editTitle" class="form-control" required>
              <div class="invalid-feedback" id="editTitleFeedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="editCategory">Category</label>
              <select name="category" id="editCategory" class="form-select" required>
                <option value="Sedan">Sedan</option>
                <option value="SUV">SUV</option>
                <option value="Van">Van</option>
                <option value="Minivan">Minivan</option>
                <option value="Scooter">Scooter</option>
                <option value="Pickup">Pickup</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="editPrice">Price per Day</label>
              <input type="number" name="price_per_day" id="editPrice" class="form-control" required>
              <div class="invalid-feedback" id="editPriceFeedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="editUnits">Units Available</label>
              <input type="number" name="units_total" id="editUnits" class="form-control" required>
              <div class="invalid-feedback" id="editUnitsFeedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Upload Image (optional)</label>
              <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="editSeats">Seats</label>
              <input type="number" name="seats" id="editSeats" class="form-control" required>
              <div class="invalid-feedback" id="editSeatsFeedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fuel Type</label>
              <select name="fuel" id="editFuel" class="form-select" required>
                <option value="Gasoline">Gasoline</option>
                <option value="Diesel">Diesel</option>
                <option value="Electric">Electric</option>
                <option value="Hybrid">Hybrid</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Transmission</label>
              <select name="transmission" id="editTransmission" class="form-select" required>
                <option value="Automatic">Automatic</option>
                <option value="Manual">Manual</option>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="editActive">
                <label class="form-check-label" for="editActive">Active (Available for rent)</label>
              </div>
            </div>
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
