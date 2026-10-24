<?php
require 'db.php';
$stmt = $pdo->query("SELECT * FROM vehicles WHERE is_active = 1 ORDER BY id DESC LIMIT 3");
$featuredVehicles = $stmt->fetchAll();

$totalVehicles = $pdo->query("SELECT COUNT(*) AS total FROM vehicles")->fetch()['total'];
$totalUsers = $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'];
// Revision: status value updated to the new enum.
$completedRentals = $pdo->query("SELECT COUNT(*) AS total FROM bookings WHERE status='completed'")->fetch()['total'];
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
  <title>PMS Car Rental - Home</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Animate.css for entrance/scroll animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
   <!-- Your main custom styles -->
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>

  <main>
  <!-- =========================== [WOW HERO SECTION] =========================== -->
  <section class="hero-immersive position-relative overflow-hidden d-flex align-items-center">
    <!--<video autoplay muted loop playsinline class="position-absolute w-100 h-100 object-fit-cover lazy-video"
      style="z-index: 0; opacity: 0.35; filter: blur(1.5px);" preload="none">
      <source src="assets/car-video-model-compressed.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>-->
    <img src="assets/car-hero-img.jpg" alt=""
      class="position-absolute w-100 h-100 object-fit-cover hero-fallback-img" style="z-index: 0; opacity: 0.35;">

    <!-- Hero scrim. #2159C7 tracks the 2026-08-22 --secondary darkening. -->
    <div class="position-absolute top-0 start-0 w-100 h-100"
      style="background: linear-gradient(135deg,#0F2A4D99,#2159C799); opacity:0.75; z-index:1;"></div>
    <div class="container position-relative z-2 d-flex flex-column justify-content-center align-items-center">
      <h1 class="display-4 fw-bold font-poppins animate__animated animate__fadeInDown">Find Your Perfect Ride</h1>
      <p class="lead text-white-50 mb-4 animate__animated animate__fadeInUp">Book premium vehicles for any journey,
        with a simple request-and-approve process.</p>
    </div>
  </section>
  <script>
    (function () {
      var video = document.querySelector('.hero-immersive video');
      var fallback = document.querySelector('.hero-immersive .hero-fallback-img');
      if (!video || !fallback) return;
      var showFallback = function () {
        video.style.display = 'none';
        fallback.style.display = 'block';
      };
      var playPromise = video.play();
      if (playPromise !== undefined) {
        playPromise.catch(showFallback);
      }
      video.addEventListener('error', showFallback);
    })();
  </script>

  <!-- ================= [VEHICLE SEARCH WIDGET] ================= -->
  <div class="container search-widget-wrap" data-reveal style="--reveal-delay: var(--motion-delay-long)">
    <div class="bg-body search-widget-card p-3 p-lg-4 mx-auto">
      <form action="vehicles.php" method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-lg-3">
          <label for="widgetPickupDate" class="form-label fw-semibold">Pickup Date</label>
          <input type="date" class="form-control" id="widgetPickupDate" name="pickup_date" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-12 col-lg-3">
          <label for="widgetReturnDate" class="form-label fw-semibold">Return Date</label>
          <input type="date" class="form-control" id="widgetReturnDate" name="return_date" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-12 col-lg-3">
          <label for="widgetCategory" class="form-label fw-semibold">Vehicle Type</label>
          <select class="form-select" id="widgetCategory" name="category">
            <option value="">Any Type</option>
            <option value="sedan">Sedan</option>
            <option value="suv">SUV</option>
            <option value="van">Van</option>
            <option value="minivan">Minivan</option>
            <option value="scooter">Scooter</option>
            <option value="pickup">Pickup</option>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <button type="submit" class="btn btn-primary rounded-pill w-100">
            <i class="fas fa-search"></i> Search Vehicles
          </button>
        </div>
      </form>
    </div>
  </div>
  <script>
    (function () {
      var pickup = document.getElementById('widgetPickupDate');
      var ret = document.getElementById('widgetReturnDate');
      if (!pickup || !ret) return;
      pickup.addEventListener('change', function () {
        ret.min = pickup.value;
        if (ret.value && ret.value < pickup.value) {
          ret.value = pickup.value;
        }
      });
    })();
  </script>

  <!-- ================= [ANIMATED FEATURES HIGHLIGHT] ================= -->
  <!-- The old 3-card section here said "book anytime with instant confirmation", which was never true. -->
  <section class="features-glass my-5" data-reveal>
    <div class="container">
      <div class="text-center mb-5">
        <div class="section-eyebrow">HOW IT WORKS</div>
        <h2 class="fw-bold">Renting with PMS, Start to Finish</h2>
      </div>
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <?php include 'includes/rental_process.php'; ?>
        </div>
      </div>
      <div class="text-center mt-4">
        <a href="how-it-works.php" class="btn btn-outline-primary rounded-pill px-4">
          Read the full guide <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
        </a>
      </div>
    </div>
  </section>

  <!-- ================= [FEATURED VEHICLES] ================= -->
  <section class="featured-vehicles my-5">
    <div class="container">
      <div class="text-center mb-5">
        <div class="section-eyebrow">FEATURED VEHICLES</div>
        <h2 class="fw-bold">Featured Cars</h2>
      </div>

      <?php if (empty($featuredVehicles)): ?>
        <p class="text-center text-body-secondary">No vehicles available right now. Please check back soon.</p>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($featuredVehicles as $car): ?>
            <div class="col-md-6 col-lg-4">
              <div class="vehicle-card bg-body rounded-3 shadow-sm overflow-hidden">
                <div class="vehicle-img-wrap">
                  <img src="assets/<?= htmlspecialchars($car['thumbnail']) ?>" alt="<?= htmlspecialchars($car['title']) ?>" style="height:220px; object-fit:cover;" loading="lazy">
                </div>
                <div class="p-3">
                  <h3 class="h5 mb-1"><?= htmlspecialchars($car['title']) ?></h3>
                  <small class="text-body-secondary d-block mb-2"><?= ucfirst(htmlspecialchars($car['category'])) ?></small>
                  <div class="vehicle-specs mb-2">
                    <div><i class="fas fa-users"></i> <?= (int)$car['seats'] ?> Seats</div>
                    <div><i class="fas fa-gas-pump"></i> <?= htmlspecialchars($car['fuel']) ?></div>
                    <div><i class="fas fa-briefcase"></i> <?= htmlspecialchars($car['transmission']) ?></div>
                  </div>
                  <div class="vehicle-pricebar mb-2">
                    <div class="price">₱<?= number_format($car['price_per_day']) ?></div>
                    <small>/ day</small>
                  </div>
                  <a href="vehicles.php" class="btn btn-primary rounded-pill w-100 mt-2">Reserve Now</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
          <a href="vehicles.php" class="text-decoration-none fw-semibold">View All Vehicles &rarr;</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="stats-bar py-5">
    <div class="container">
      <div class="row g-4 text-center" data-reveal-stagger="80">
        <div class="col-6 col-lg-3" data-reveal>
          <i class="fas fa-car stat-icon mb-2"></i>
          <div class="stat-number display-5 fw-bold">
            <span aria-hidden="true"><span class="js-count" data-target="<?= (int)$totalVehicles ?>"><?= (int)$totalVehicles ?></span></span>
            <span class="visually-hidden"><?= (int)$totalVehicles ?></span>
          </div>
          <div class="text-body-secondary">Total Vehicles</div>
        </div>
        <div class="col-6 col-lg-3" data-reveal>
          <i class="fas fa-users stat-icon mb-2"></i>
          <div class="stat-number display-5 fw-bold">
            <span aria-hidden="true"><span class="js-count" data-target="<?= (int)$totalUsers ?>"><?= (int)$totalUsers ?></span></span>
            <span class="visually-hidden"><?= (int)$totalUsers ?></span>
          </div>
          <div class="text-body-secondary">Happy Customers</div>
        </div>
        <div class="col-6 col-lg-3" data-reveal>
          <i class="fas fa-check-circle stat-icon mb-2"></i>
          <div class="stat-number display-5 fw-bold">
            <span aria-hidden="true"><span class="js-count" data-target="<?= (int)$completedRentals ?>"><?= (int)$completedRentals ?></span></span>
            <span class="visually-hidden"><?= (int)$completedRentals ?></span>
          </div>
          <div class="text-body-secondary">Completed Rentals</div>
        </div>
        <div class="col-6 col-lg-3" data-reveal>
          <i class="fas fa-calendar-alt stat-icon mb-2"></i>
          <div class="stat-number display-5 fw-bold">
            <span aria-hidden="true"><span class="js-count" data-target="1">1</span>+</span>
            <span class="visually-hidden">1+</span>
          </div>
          <div class="text-body-secondary">Years of Service</div>
        </div>
      </div>
    </div>
  </section>

  <section class="testimonials py-5">
    <div class="container">
      <div class="text-center mb-5">
        <div class="section-eyebrow">TESTIMONIALS</div>
        <h2 class="fw-bold">What Our Customers Say</h2>
      </div>
      <div class="row g-4" data-reveal-stagger="100">
        <div class="col-md-4">
          <div class="testimonial-card bg-body shadow-sm rounded-3 p-4 text-center h-100" data-reveal>
            <i class="fas fa-quote-left testimonial-quote-icon mb-2"></i>
            <i class="fas fa-user-circle testimonial-avatar mb-3"></i>
            <p class="mb-3">"予約はスムーズで、受け取った車も素晴らしい状態でした。おかげで週末の旅行がとても快適になりました。."</p>
            <strong>Yuji Itadori</strong>
            <div class="text-body-secondary small">Weekend Traveler</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="testimonial-card bg-body shadow-sm rounded-3 p-4 text-center h-100" data-reveal>
            <i class="fas fa-quote-left testimonial-quote-icon mb-2"></i>
            <i class="fas fa-user-circle testimonial-avatar mb-3"></i>
            <p class="mb-3">"Nagkinahanglan ko og kasaligang sakyanan para sa usa ka business trip nga kalit lang ang plano. Sayon ug diretso ang proseso, walay mga surpresa."</p>
            <strong>Balmond Butterbonia</strong>
            <div class="text-body-secondary small">Business Traveler</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="testimonial-card bg-body shadow-sm rounded-3 p-4 text-center h-100" data-reveal>
            <i class="fas fa-quote-left testimonial-quote-icon mb-2"></i>
            <i class="fas fa-user-circle testimonial-avatar mb-3"></i>
            <p class="mb-3">"Rented a scooter for a few days while exploring the city. Simple to reserve and pick up."</p>
            <strong>Happy Customer</strong>
            <div class="text-body-secondary small">Tourist</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-banner py-5 text-white text-center" data-reveal>
    <div class="container">
      <h2 class="fw-bold">Ready to Hit the Road?</h2>
      <p class="mb-4">Browse our collection and find the perfect vehicle for your next trip.</p>
      <a href="vehicles.php" class="btn btn-light rounded-pill btn-lg px-4 fw-semibold">Browse Vehicles</a>
    </div>
  </section>

  <section class="faq-preview py-5">
    <div class="container">
      <div class="row">
        <div class="col-lg-8 mx-auto text-center mb-4">
          <div class="section-eyebrow">FAQ</div>
          <h2 class="fw-bold">Frequently Asked Questions</h2>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-8 mx-auto">
          <div class="accordion" id="homeFaqAccordion">
            <div class="accordion-item">
              <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#homeFaq1">What documents do I need to rent a car?</button></h2>
              <div id="homeFaq1" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                <div class="accordion-body">You'll need a valid driver's license, one (1) government-issued ID, and a
                  credit or debit card under your name for the security deposit. Drivers must present their official
                  transaction receipt before driving.</div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#homeFaq2">How to rent a car?</button></h2>
              <div id="homeFaq2" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                <div class="accordion-body">First, select an available car from our vehicles page. Second, click the
                  'Reserve Now' button to open the booking form. Third, fill out the booking form and confirm your
                  payment. Lastly, save the receipt and present it together with the other documents required.</div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#homeFaq3">What is the minimum age to rent a car?</button></h2>
              <div id="homeFaq3" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                <div class="accordion-body">The minimum age requirement is 18 years old.</div>
              </div>
            </div>
          </div>
          <div class="text-center mt-4">
            <a href="faq.php" class="text-decoration-none fw-semibold">View All FAQs &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <!-- Scripts: Bootstrap JS etc -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
</body>

</html>