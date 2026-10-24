<?php $current = basename($_SERVER['PHP_SELF']); ?>
<div class="offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
  <div class="offcanvas-header border-bottom">
    <div class="d-flex align-items-center">
      <img src="assets/logo-removebg-preview.png" alt="PMS Logo" style="width:36px;height:28px;border-radius:6px;">
      <span class="ms-2 fw-bold font-poppins" id="adminSidebarLabel">PMS Admin</span>
    </div>
    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas"
            data-bs-target="#adminSidebar" aria-label="Close sidebar"></button>
  </div>
  <div class="offcanvas-body p-3">
    <nav aria-label="Admin navigation">
      <ul class="nav flex-column gap-2">
        <li class="nav-item">
          <a class="nav-link <?= $current === 'admin-dashboard.php' ? 'active' : '' ?>" href="admin-dashboard.php" <?= $current === 'admin-dashboard.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-dashboard me-2" aria-hidden="true"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current === 'admin_vehicles.php' ? 'active' : '' ?>" href="admin_vehicles.php" <?= $current === 'admin_vehicles.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-car me-2" aria-hidden="true"></i>Manage Vehicles
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current === 'admin_users.php' ? 'active' : '' ?>" href="admin_users.php" <?= $current === 'admin_users.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-users me-2" aria-hidden="true"></i>User Accounts
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current === 'admin_vouchers.php' ? 'active' : '' ?>" href="admin_vouchers.php" <?= $current === 'admin_vouchers.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-ticket me-2" aria-hidden="true"></i>Vouchers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current === 'view-all-data.php' ? 'active' : '' ?>" href="view-all-data.php" <?= $current === 'view-all-data.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-list me-2" aria-hidden="true"></i>Transactions
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link d-flex align-items-center <?= in_array($current, ['admin_messages.php'], true) ? 'active' : '' ?>" href="admin_messages.php" <?= $current === 'admin_messages.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-comments me-2" aria-hidden="true"></i>Messages
            <span class="badge bg-danger rounded-pill ms-auto d-none" id="sidebarMessagesBadge">0</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current === 'admin_reviews.php' ? 'active' : '' ?>" href="admin_reviews.php" <?= $current === 'admin_reviews.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-star me-2" aria-hidden="true"></i>Reviews
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current === 'admin_settings.php' ? 'active' : '' ?>" href="admin_settings.php" <?= $current === 'admin_settings.php' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-gear me-2" aria-hidden="true"></i>Settings
          </a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link text-danger" href="admin_logout.php" id="adminLogoutBtn">
            <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>Logout
          </a>
        </li>
      </ul>
    </nav>
  </div>
</div>
<script>
  // Unread-message badge on the Messages nav item.
  (function () {
    function refresh() {
      fetch('messages_unread_count.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var b = document.getElementById('sidebarMessagesBadge');
          if (!b) return;
          if (d && d.authenticated && d.count > 0) {
            b.textContent = d.count > 99 ? '99+' : String(d.count);
            b.classList.remove('d-none');
          } else {
            b.classList.add('d-none');
          }
        })
        .catch(function () {});
    }
    refresh();
    setInterval(refresh, 90000);
  })();
</script>
