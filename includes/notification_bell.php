<?php
// Shared bell for both shells.
$bellAudience = ($bellAudience ?? 'user') === 'admin' ? 'admin' : 'user';
?>
<div class="dropdown notification-bell d-none" id="notificationBell" data-audience="<?= $bellAudience ?>">
  <button class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center position-relative"
          style="width:44px;height:44px;" type="button" id="notificationBellToggle"
          data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
    <i class="fas fa-bell" aria-hidden="true"></i>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
          id="notificationBadge">
      <span id="notificationBadgeCount">0</span>
      <span class="visually-hidden">unread notifications</span>
    </span>
  </button>
  <div class="dropdown-menu dropdown-menu-end shadow p-0" aria-labelledby="notificationBellToggle"
       style="width:340px;max-width:92vw;">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <span class="fw-semibold">Notifications</span>
      <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none small" id="notificationMarkAll">Mark all read</button>
    </div>
    <div id="notificationList" style="max-height:360px;overflow-y:auto;">
      <p class="text-body-secondary small text-center py-4 mb-0" id="notificationEmpty">Loading…</p>
    </div>
    <div class="border-top text-center py-2">
      <a href="notifications.php?audience=<?= $bellAudience ?>" class="small text-decoration-none">View all notifications</a>
    </div>
  </div>
</div>
