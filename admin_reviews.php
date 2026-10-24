<?php
// Moderation: view every review, hide/unhide, and post one admin_response.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/reviews.php';
require_once __DIR__ . '/lib/csrf.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$reviews = review_admin_list($pdo);
$csrfToken = csrf_token();
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
  <title>PMS — Reviews</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">

<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
  <?php $topbarTitle = 'Reviews'; include 'includes/admin_topbar.php'; ?>

  <div class="container-fluid py-4">
    <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="pageAlert">
      <div class="d-flex justify-content-between align-items-start">
        <span class="js-modal-alert-text"></span>
        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
      </div>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-header bg-body fw-bold">All Reviews</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle" id="reviewsTable">
            <thead>
              <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Customer</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Status</th>
                <th>Admin Response</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reviews as $r): ?>
              <tr>
                <td data-order="<?php echo htmlspecialchars($r['created_at']); ?>"><?php echo htmlspecialchars(date('M j, Y', strtotime($r['created_at']))); ?></td>
                <td><?php echo htmlspecialchars($r['vehicle_title']); ?></td>
                <td><?php echo htmlspecialchars($r['user_name']); ?><br><small class="text-body-secondary"><?php echo htmlspecialchars($r['user_email']); ?></small></td>
                <td data-order="<?php echo (int) $r['rating']; ?>">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="<?php echo $i <= (int) $r['rating'] ? 'fas' : 'far'; ?> fa-star text-warning"></i>
                  <?php endfor; ?>
                </td>
                <td style="max-width:280px;"><?php echo $r['body'] ? nl2br(htmlspecialchars($r['body'])) : '<span class="text-body-secondary">—</span>'; ?></td>
                <td>
                  <span class="badge <?php echo $r['status'] === 'visible' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($r['status']); ?></span>
                </td>
                <td style="max-width:240px;"><?php echo $r['admin_response'] ? nl2br(htmlspecialchars($r['admin_response'])) : '<span class="text-body-secondary">—</span>'; ?></td>
                <td class="text-nowrap">
                  <?php if ($r['status'] === 'visible'): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-review mb-1" data-id="<?php echo (int) $r['id']; ?>" data-action="hide">Hide</button>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-success js-toggle-review mb-1" data-id="<?php echo (int) $r['id']; ?>" data-action="unhide">Unhide</button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-outline-primary js-respond-review mb-1" data-id="<?php echo (int) $r['id']; ?>" data-response="<?php echo htmlspecialchars($r['admin_response'] ?? '', ENT_QUOTES); ?>">
                    <?php echo $r['admin_response'] ? 'Edit Response' : 'Respond'; ?>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if (!$reviews): ?>
            <p class="text-body-secondary text-center mb-0">No reviews yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Respond to Review Modal -->
<div class="modal fade" id="respondReviewModal" tabindex="-1" aria-labelledby="respondReviewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="respondReviewModalLabel">Respond to Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
          <div class="d-flex justify-content-between align-items-start">
            <span class="js-modal-alert-text"></span>
            <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
          </div>
        </div>
        <input type="hidden" id="respondReviewId">
        <label class="form-label" for="respondReviewText">Your response</label>
        <textarea class="form-control" id="respondReviewText" rows="4" maxlength="2000"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveReviewResponse">Save Response</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
<script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
</body>
</html>
