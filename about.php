<?php
session_start();
// No DB usage on this page
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PMS Car Rental — About Us</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Animate.css for entrance/scroll animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>

  <main class="container py-5 position-relative navbar-offset">

    <div class="position-absolute end-3 top-3 pe-4 pt-3 d-none d-md-block" style="opacity:0.12; z-index: -1;">
      <img src="assets/new-logo-bg-remove.png" style="max-width:520px" alt="hero car">
    </div>

    <div class="text-center mb-5">
      <h1 class="font-poppins display-5 fw-bold">About Us</h1>
      <div class="text-body-secondary">Get to know our story and meet our team</div>
    </div>

    <div class="row mb-5" data-reveal>
      <div class="col-12">
        <h2 class="fw-bold font-poppins">Our Story</h2>
        <p class="text-body-secondary text-justify">PMS Corp was established in 2025 with the vision of providing reliable and
          accessible transportation
          solutions to meet the growing demand for convenient travel. The PMS Corp began its journey as a car rental
          service, catering to individuals, families, and businesses in need of flexible mobility options. Through a
          commitment to quality service, well-maintained vehicles, and customer satisfaction, PMS quickly built a
          reputation as a trusted partner in the transportation industry.</p>

        <p class="text-body-secondary text-justify">The company is fully committed to providing affordable, user-friendly, and
          flexible transportation solutions.
          Unlike other competitors that rely on manual and walk-in car reservations, PMS Corp focuses on personalized
          and automated customer service, ensuring that clients feel secure and comfortable with the vehicles they rent
          in our company.</p>
      </div>
    </div>

    <!-- Mission & Vision -->
    <div class="row mb-5">
      <div class="col-12">
        <div class="text-center mb-4">
          <div class="section-eyebrow">OUR PURPOSE</div>
          <h2 class="fw-bold font-poppins">Mission &amp; Vision</h2>
        </div>
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="p-4 bg-body rounded-3 shadow-sm h-100">
              <h3 class="fs-5 fw-semibold">Making every rental convenient, efficient, and easy.</h3>
              <p class="text-body-secondary text-justify mb-0">Our mission is to provide customers with a convenient,
                efficient, and user-friendly way to rent vehicles. Through our web-based platform, we aim to
                simplify the rental process by providing accessible vehicle information, availability, estimated
                rental fees, and online reservations while helping staff manage rental transactions and customer
                records efficiently.</p>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="p-4 bg-body rounded-3 shadow-sm h-100">
              <h3 class="fs-5 fw-semibold">A simpler, more accessible digital car rental experience.</h3>
              <p class="text-body-secondary text-justify mb-0">Our vision is to transform the traditional car rental
                experience into a more accessible and efficient digital service, reducing the time and effort
                required for customers and staff while improving the overall management of car rental operations
                and customer satisfaction.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Why Choose PMS -->
    <div class="row mb-5">
      <div class="col-12">
        <div class="text-center mb-4">
          <div class="section-eyebrow">WHY CHOOSE PMS</div>
          <h2 class="fw-bold font-poppins">Why Choose PMS</h2>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3" data-reveal-stagger>
          <div class="col">
            <div class="p-4 bg-body rounded-3 shadow-sm text-center h-100" data-reveal>
              <div class="mb-2"><i class="fa-solid fa-car fs-3" aria-hidden="true"></i></div>
              <h3 class="h6 mt-3">Variety Brands</h3>
              <p class="text-body-secondary small">Wide selection to choose</p>
            </div>
          </div>
          <div class="col">
            <div class="p-4 bg-body rounded-3 shadow-sm text-center h-100" data-reveal>
              <div class="mb-2"><i class="fa-solid fa-headset fs-3" aria-hidden="true"></i></div>
              <h3 class="h6 mt-3">Awesome Support</h3>
              <p class="text-body-secondary small">24/7 customer service</p>
            </div>
          </div>
          <div class="col">
            <div class="p-4 bg-body rounded-3 shadow-sm text-center h-100" data-reveal>
              <div class="mb-2"><i class="fa-solid fa-shield-halved fs-3" aria-hidden="true"></i></div>
              <h3 class="h6 mt-3">Maximum Freedom</h3>
              <p class="text-body-secondary small">Flexible pick-up and return</p>
            </div>
          </div>
          <div class="col">
            <div class="p-4 bg-body rounded-3 shadow-sm text-center h-100" data-reveal>
              <div class="mb-2"><i class="fa-solid fa-gears fs-3" aria-hidden="true"></i></div>
              <h3 class="h6 mt-3">Flexibility On The Go</h3>
              <p class="text-body-secondary small">Customize your trip</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Meet the Team -->
    <div class="row align-items-start my-5">
      <!-- Meet Our Team (Left) -->
      <div class="col-lg-7">
        <h3 class="fw-bold font-poppins mb-4">Meet Our Team</h3>
        <div class="row row-cols-1 row-cols-md-2 g-4" data-reveal-stagger>
          <!-- Team Member 1 -->
          <!-- Data-reveal is on this .col wrapper, not .team-member-card. -->
          <div class="col text-center" data-reveal>
            <div class="team-member-card p-4 bg-body rounded-3 shadow-sm h-100">
              <div class="mx-auto rounded-circle overflow-hidden mb-3" style="width: 110px; height: 110px;">
                <img src="assets/team-placeholder.svg" class="w-100 h-100 object-fit-cover" alt="Team Member 1">
              </div>
              <div class="fw-semibold">Team Member 1</div>
              <div class="text-body-secondary small">Developer 1</div>
            </div>
          </div>
          <!-- Team Member 2 -->
          <div class="col text-center" data-reveal>
            <div class="team-member-card p-4 bg-body rounded-3 shadow-sm h-100">
              <div class="mx-auto rounded-circle overflow-hidden mb-3" style="width: 110px; height: 110px;">
                <img src="assets/team-placeholder.svg" class="w-100 h-100 object-fit-cover" alt="Team Member 2">
              </div>
              <div class="fw-semibold">Team Member 2</div>
              <div class="text-body-secondary small">Developer 2</div>
            </div>
          </div>
          <!-- Team Member 3 -->
          <div class="col text-center" data-reveal>
            <div class="team-member-card p-4 bg-body rounded-3 shadow-sm h-100">
              <div class="mx-auto rounded-circle overflow-hidden mb-3" style="width: 110px; height: 110px;">
                <img src="assets/team-placeholder.svg" class="w-100 h-100 object-fit-cover" alt="Team Member 3">
              </div>
              <div class="fw-semibold">Team Member 3</div>
              <div class="text-body-secondary small">Developer 3</div>
            </div>
          </div>
          <!-- Team Member 4 -->
          <div class="col text-center" data-reveal>
            <div class="team-member-card p-4 bg-body rounded-3 shadow-sm h-100">
              <div class="mx-auto rounded-circle overflow-hidden mb-3" style="width: 110px; height: 110px;">
                <img src="assets/team-placeholder.svg" class="w-100 h-100 object-fit-cover" alt="Team Member 4">
              </div>
              <div class="fw-semibold">Team Member 4</div>
              <div class="text-body-secondary small">Developer 4</div>
            </div>
          </div>
          <!-- Team Member 5 -->
          <div class="col text-center" data-reveal>
            <div class="team-member-card p-4 bg-body rounded-3 shadow-sm h-100">
              <div class="mx-auto rounded-circle overflow-hidden mb-3" style="width: 110px; height: 110px;">
                <img src="assets/team-placeholder.svg" class="w-100 h-100 object-fit-cover" alt="Team Member 5">
              </div>
              <div class="fw-semibold">Team Member 5</div>
              <div class="text-body-secondary small">Developer 5</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <section class="cta-banner py-5 text-white text-center" data-reveal>
      <div class="container">
        <h3 class="fw-bold">Ready to start your journey?</h3>
        <p>Experience the difference with our premium car rental service</p>
        <a href="vehicles.php" class="btn btn-light rounded-pill px-4 fw-semibold mt-2">View Cars</a>
      </div>
    </section>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>

</body>

</html>