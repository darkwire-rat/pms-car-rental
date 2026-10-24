<?php
session_start();
include 'db_connect.php';


// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}


// Handle CRUD operations via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
   
    switch($action) {
        case 'create':
            $code = $_POST['code'];
            $discount_pct = (int)$_POST['discount_pct'];
            $discount_amount = (float)$_POST['discount_amount'];
            $usage_limit = $_POST['usage_limit'];
           
            $stmt = $conn->prepare("
                INSERT INTO vouchers (code, discount_pct, discount_amount, usage_limit, usage_count, is_active)
                VALUES (?, ?, ?, ?, 0, 1)
            ");
            $stmt->bind_param("sidi", $code, $discount_pct, $discount_amount, $usage_limit);


           
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Voucher created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating voucher']);
            }
            break;
           
        case 'update':
            $id = (int)$_POST['id'];
            $code = $_POST['code'];
            $discount_pct = (int)$_POST['discount_pct'];
            $discount_amount = (float)$_POST['discount_amount'];
            $usage_limit = (int)$_POST['usage_limit'];  
           
            $stmt = $conn->prepare("UPDATE vouchers SET code=?, discount_pct=?, discount_amount=?, usage_limit=? WHERE id=?");
            $stmt->bind_param("sidii", $code, $discount_pct, $discount_amount, $usage_limit, $id);
               
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Voucher updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating voucher']);
            }
            break;
           
        case 'delete':
            $id = (int)$_POST['id'];
           
            $stmt = $conn->prepare("DELETE FROM vouchers WHERE id=?");
            $stmt->bind_param("i", $id);
           
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Voucher deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting voucher']);
            }
            break;
    }
   
    exit;
}


// Fetch all vouchers for display
$vouchers = $conn->query("SELECT * FROM vouchers ORDER BY created_at DESC");
?>


<!DOCTYPE html>
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS Car Rental — Voucher Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">
<?php include 'includes/admin_sidebar.php'; ?>
<main class="flex-grow-1">
  <!-- Topbar -->
  <?php
  $topbarTitle = 'Voucher Management';
  $topbarActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVoucherModal"><i class="fas fa-plus" aria-hidden="true"></i> Add New Voucher</button>';
  include 'includes/admin_topbar.php';
  ?>

<div class="container mt-4">
    <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="pageAlert">
        <div class="d-flex justify-content-between align-items-start">
            <span class="js-modal-alert-text"></span>
            <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Discount (%)</th>
                            <th>Discount (₱)</th>
                            <th>Usage</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($voucher = $vouchers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $voucher['id']; ?></td>
                            <td><?php echo htmlspecialchars($voucher['code']); ?></td>
                            <td><?php echo $voucher['discount_pct']; ?>%</td>
                            <td>₱<?php echo number_format($voucher['discount_amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($voucher['usage_count'] < $voucher['usage_limit']) ? 'success' : 'danger'; ?>">
                                    <?php echo $voucher['usage_count'] . ' / ' . $voucher['usage_limit']; ?>
                                    <?php if ($voucher['usage_count'] >= $voucher['usage_limit']): ?>
                                        <span class="ms-1">(Maxed Out)</span>
                                    <?php endif; ?>
                                </span>
                            </td>


                            <td><?php echo date('Y-m-d H:i', strtotime($voucher['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-voucher"
                                        aria-label="Edit voucher <?php echo htmlspecialchars($voucher['code']); ?>"
                                        data-id="<?php echo $voucher['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($voucher['code']); ?>"
                                        data-discount-pct="<?php echo $voucher['discount_pct']; ?>"
                                        data-discount-amount="<?php echo $voucher['discount_amount']; ?>"
                                        data-usage-limit="<?php echo $voucher['usage_limit']; ?>">
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-voucher"
                                        aria-label="Delete voucher <?php echo htmlspecialchars($voucher['code']); ?>"
                                        data-id="<?php echo $voucher['id']; ?>">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- Add Voucher Modal -->
<div class="modal fade" id="addVoucherModal" tabindex="-1" aria-labelledby="addVoucherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addVoucherForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="addVoucherModalLabel">Add New Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="js-modal-alert-text"></span>
                        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="code" class="form-label">Voucher Code</label>
                    <input type="text" class="form-control" id="code" name="code" required>
                    <div class="invalid-feedback" id="codeFeedback"></div>
                </div>
                <div class="mb-3">
                    <label for="discount_pct" class="form-label">Discount Percentage</label>
                    <input type="number" class="form-control" id="discount_pct" name="discount_pct"
                           min="0" max="100" required>
                    <div class="invalid-feedback" id="discount_pctFeedback"></div>
                </div>
                <div class="mb-3">
                    <label for="discount_amount" class="form-label">Discount Amount (₱)</label>
                    <input type="number" class="form-control" id="discount_amount" name="discount_amount"
                           min="0" step="0.01" required>
                    <div class="invalid-feedback" id="discount_amountFeedback"></div>
                </div>
                <div class="mb-3">
                    <label for="usage_limit" class="form-label">Usage Limit</label>
                    <input type="number" class="form-control" id="usage_limit" name="usage_limit" min="1" value="1" required>
                    <div class="invalid-feedback" id="usage_limitFeedback"></div>
                    <small class="text-body-secondary">Set how many times this voucher can be used (1 = single use, higher = multiple).</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveVoucher">Save Voucher</button>
            </div>
            </form>
        </div>
    </div>
</div>


<!-- Edit Voucher Modal -->
<div class="modal fade" id="editVoucherModal" tabindex="-1" aria-labelledby="editVoucherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editVoucherForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="editVoucherModalLabel">Edit Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="js-modal-alert-text"></span>
                        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
                    </div>
                </div>
                <input type="hidden" id="edit_id" name="id">
                <div class="mb-3">
                    <label for="edit_code" class="form-label">Voucher Code</label>
                    <input type="text" class="form-control" id="edit_code" name="code" required>
                    <div class="invalid-feedback" id="edit_codeFeedback"></div>
                </div>
                <div class="mb-3">
                    <label for="edit_discount_pct" class="form-label">Discount Percentage</label>
                    <input type="number" class="form-control" id="edit_discount_pct" name="discount_pct"
                           min="0" max="100" required>
                    <div class="invalid-feedback" id="edit_discount_pctFeedback"></div>
                </div>
                <div class="mb-3">
                    <label for="edit_discount_amount" class="form-label">Discount Amount (₱)</label>
                    <input type="number" class="form-control" id="edit_discount_amount" name="discount_amount"
                           min="0" step="0.01" required>
                    <div class="invalid-feedback" id="edit_discount_amountFeedback"></div>
                </div>
                <div class="mb-3">
                    <label for="edit_usage_limit" class="form-label">Usage Limit</label>
                    <input type="number" class="form-control" id="edit_usage_limit" name="usage_limit" min="1" value="1" required>
                    <div class="invalid-feedback" id="edit_usage_limitFeedback"></div>
                    <small class="text-body-secondary">Set how many times this voucher can be used (1 = single use, higher = multiple).</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="updateVoucher">Update Voucher</button>
            </div>
            </form>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
<script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>

</main> <!-- End flex-grow-1 -->
</body>
</html>

