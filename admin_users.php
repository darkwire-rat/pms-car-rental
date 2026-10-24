<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// Shared with js/app.js's getInitials/getAvatarHtml.
function get_initials($name) {
    $parts = array_values(array_filter(preg_split('/\s+/', trim($name)), fn($p) => $p !== ''));
    if (count($parts) === 0) return '?';
    if (count($parts) === 1) return strtoupper(mb_substr($parts[0], 0, 1));
    return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
}

function render_avatar_html($name, $picturePath, $size = 32) {
    if ($picturePath) {
        $src = htmlspecialchars($picturePath, ENT_QUOTES);
        return "<img src=\"{$src}\" alt=\"\" class=\"rounded-circle\" style=\"width:{$size}px;height:{$size}px;object-fit:cover;\">";
    }
    $initials = htmlspecialchars(get_initials($name), ENT_QUOTES);
    $fontSize = (int) round($size * 0.4);
    return "<span class=\"avatar-circle text-white\" style=\"width:{$size}px;height:{$size}px;font-size:{$fontSize}px;\">{$initials}</span>";
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
  <title>PMS — User Accounts</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">
<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
<?php $topbarTitle = 'User Accounts'; include 'includes/admin_topbar.php'; ?>
<div class="container py-4">
  <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="pageAlert">
    <div class="d-flex justify-content-between align-items-start">
      <span class="js-modal-alert-text"></span>
      <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
    </div>
  </div>
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="usersTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Avatar</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $users = $conn->query("SELECT id, name, email, role, created_at, profile_picture_path FROM users WHERE role != 'admin' ORDER BY created_at DESC");

            if ($users->num_rows > 0) {
                while ($user = $users->fetch_assoc()) {
                    echo "
                    <tr>
                        <td data-order='" . (int) $user['id'] . "'>#{$user['id']}</td>
                        <td>" . render_avatar_html($user['name'], $user['profile_picture_path'] ?? null) . "</td>
                        <td>" . htmlspecialchars($user['name'], ENT_QUOTES) . "</td>
                        <td>" . htmlspecialchars($user['email'], ENT_QUOTES) . "</td>
                        <td><span class='badge bg-info text-body'>" . ucfirst($user['role']) . "</span></td>
                        <td data-order='" . (int) strtotime($user['created_at']) . "'>" . date('M d, Y g:i A', strtotime($user['created_at'])) . "</td>
                        <td>
                            <button class='btn btn-sm btn-outline-primary edit-user'
                                    aria-label='Edit " . htmlspecialchars($user['name'], ENT_QUOTES) . "'
                                    data-id='" . htmlspecialchars($user['id'], ENT_QUOTES) . "'
                                    data-name='" . htmlspecialchars($user['name'], ENT_QUOTES) . "'
                                    data-email='" . htmlspecialchars($user['email'], ENT_QUOTES) . "'>
                                <i class='fas fa-pencil' aria-hidden='true'></i>
                            </button>
                            <button class='btn btn-sm btn-outline-danger delete-user'
                                    aria-label='Delete " . htmlspecialchars($user['name'], ENT_QUOTES) . "'
                                    data-id='" . htmlspecialchars($user['id'], ENT_QUOTES) . "'>
                                <i class='fas fa-trash' aria-hidden='true'></i>
                            </button>
                        </td>
                    </tr>
                    ";
                }
            } else {
                echo "<tr><td colspan='7' class='text-center text-body-secondary py-3'>No users found</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</main> <!-- End flex-grow-1 -->

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editUserForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-body">
                <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="js-modal-alert-text"></span>
                        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editUserName">Name</label>
                    <input type="text" class="form-control" name="name" id="editUserName" required>
                    <div class="invalid-feedback" id="editUserNameFeedback"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editUserEmail">Email</label>
                    <input type="email" class="form-control" name="email" id="editUserEmail" required>
                    <div class="invalid-feedback" id="editUserEmailFeedback"></div>
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
