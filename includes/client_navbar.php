<?php $messagesNavClass = !empty($_SESSION['user']) ? '' : ' d-none'; ?>
<nav class="navbar navbar-expand-md fixed-top bg-body shadow-sm border-bottom d-print-none">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <img src="assets/new-logo-bg-remove.png" alt="logo"
        style="width:36px;height:28px;object-fit:cover;border-radius:6px;">
      <span class="h5 mb-0 font-poppins">PMS Car Rental</span>
    </div>
    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav ms-auto mb-2 mb-md-0">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="vehicles.php">Vehicles</a></li>
        <li class="nav-item"><a class="nav-link" href="faq.php">FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
        <li class="nav-item<?php echo $messagesNavClass; ?>" id="messagesNavItem"><a class="nav-link" href="my_messages.php">Messages</a></li>
      </ul>
      <!-- Theme toggle — static markup, not injected by renderNavbarAuth, so it never disappears for logged-out visitors. -->
      <button type="button" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center ms-3 js-theme-toggle" id="themeToggle" style="width:44px;height:44px;" aria-label="Switch to dark mode" aria-pressed="false">
        <i class="fas fa-moon" aria-hidden="true"></i>
      </button>
      <?php $bellAudience = 'user'; include __DIR__ . '/notification_bell.php'; ?>
      <div id="navbarAuthArea" class="ms-3 d-flex align-items-center gap-2"></div>
    </div>
  </div>
</nav>
<script src="js/csrf.js?v=<?php echo @filemtime(__DIR__ . '/../js/csrf.js'); ?>"></script>
<script src="js/notifications.js?v=<?php echo @filemtime(__DIR__ . '/../js/notifications.js'); ?>"></script>
