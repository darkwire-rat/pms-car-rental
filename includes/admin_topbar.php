<?php
// Calling convention mirrors admin_sidebar.php's $current: set $topbarTitle immediately before this include.
$topbarTitle = $topbarTitle ?? 'Admin';
$topbarBreadcrumb = $topbarBreadcrumb ?? $topbarTitle;
$topbarActions = $topbarActions ?? '';
?>
<div class="d-flex align-items-center justify-content-between bg-body border-bottom px-4 py-3 admin-topbar">
  <div class="d-flex align-items-center flex-grow-1 overflow-hidden admin-topbar-title-group">
    <button class="btn btn-sm btn-outline-secondary d-lg-none me-2 flex-shrink-0"
            data-bs-toggle="offcanvas"
            data-bs-target="#adminSidebar"
            aria-controls="adminSidebar"
            aria-label="Toggle sidebar">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
    <div class="w-100" style="min-width:0;">
      <h1 class="h4 mb-0 text-truncate"><?= htmlspecialchars($topbarTitle, ENT_QUOTES) ?></h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($topbarBreadcrumb, ENT_QUOTES) ?></li>
        </ol>
      </nav>
    </div>
  </div>
  <div class="d-flex align-items-center flex-shrink-0 ms-3">
    <!-- Theme toggle — static markup here rather than via $topbarActions. -->
    <button type="button" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center me-3 js-theme-toggle" id="adminThemeToggle" style="width:44px;height:44px;" aria-label="Switch to dark mode" aria-pressed="false">
      <i class="fas fa-moon" aria-hidden="true"></i>
    </button>
    <div class="me-3"><?php $bellAudience = 'admin'; include __DIR__ . '/notification_bell.php'; ?></div>
    <span class="fw-bold font-poppins d-none d-sm-inline me-3">Hello, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES) ?>!</span>
    <?php if ($topbarActions !== ''): ?>
      <div class="ms-auto"><?= $topbarActions ?></div>
    <?php endif; ?>
  </div>
</div>
<script src="js/csrf.js?v=<?= @filemtime(__DIR__ . '/../js/csrf.js') ?>"></script>
<script src="js/notifications.js?v=<?= @filemtime(__DIR__ . '/../js/notifications.js') ?>"></script>
