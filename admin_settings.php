<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT name, email FROM admins WHERE id = ?");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
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
  <title>PMS — Account Settings</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">
<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
<?php $topbarTitle = 'Account Settings'; include 'includes/admin_topbar.php'; ?>
<div class="container py-4">
  <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="settingsAlert">
    <div class="d-flex justify-content-between align-items-start">
      <span class="js-modal-alert-text"></span>
      <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h5 mb-3">Profile Information</h2>
          <form id="profileForm" novalidate>
            <input type="hidden" name="action" value="update_profile">
            <div class="mb-3">
              <label class="form-label" for="settingsName">Name</label>
              <input type="text" class="form-control" name="name" id="settingsName"
                     value="<?= htmlspecialchars($admin['name'] ?? '', ENT_QUOTES) ?>" required>
              <div class="invalid-feedback" id="settingsNameFeedback"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="settingsEmail">Email</label>
              <input type="email" class="form-control" name="email" id="settingsEmail"
                     value="<?= htmlspecialchars($admin['email'] ?? '', ENT_QUOTES) ?>" required>
              <div class="invalid-feedback" id="settingsEmailFeedback"></div>
            </div>
            <button type="submit" class="btn btn-primary">Save Profile</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h5 mb-3">Change Password</h2>
          <form id="passwordForm" novalidate>
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
              <label class="form-label" for="currentPassword">Current Password</label>
              <input type="password" class="form-control" name="current_password" id="currentPassword"
                     autocomplete="current-password" required>
              <div class="invalid-feedback" id="currentPasswordFeedback"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="newPassword">New Password</label>
              <input type="password" class="form-control" name="new_password" id="newPassword"
                     autocomplete="new-password" required>
              <div class="invalid-feedback" id="newPasswordFeedback"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="confirmNewPassword">Confirm New Password</label>
              <input type="password" class="form-control" name="confirm_password" id="confirmNewPassword"
                     autocomplete="new-password" required>
              <div class="invalid-feedback" id="confirmNewPasswordFeedback"></div>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</main> <!-- End flex-grow-1 -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
<script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
</body>
</html>
