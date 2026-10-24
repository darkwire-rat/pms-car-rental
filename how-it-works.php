<!doctype html>
<html lang="en">

<head>
  <script>
    // No-flash theme bootstrap — see faq.php for why this stays inline.
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PMS Car Rental — How It Works</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>
  <div style="height:80px"></div>

  <main class="container py-5">
    <div class="text-center mb-5">
      <div class="section-eyebrow">HOW IT WORKS</div>
      <h1 class="display-5 fw-bold font-poppins">Renting with PMS, Start to Finish</h1>
      <p class="text-body-secondary">Everything that happens between browsing a vehicle and leaving a review — no surprises.</p>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <?php include 'includes/rental_process.php'; ?>
      </div>
    </div>
    <div class="text-center mt-5">
      <a href="vehicles.php" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-car me-1" aria-hidden="true"></i> Browse Vehicles
      </a>
      <a href="faq.php" class="btn btn-outline-secondary rounded-pill px-4 ms-2">
        <i class="fas fa-circle-question me-1" aria-hidden="true"></i> Read the FAQ
      </a>
    </div>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
</body>

</html>
