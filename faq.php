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
  <title>PMS Car Rental — FAQ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Animate.css for entrance/scroll animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>
  <div style="height:80px"></div>

  <main class="container py-5">
    <div class="text-center mb-4">
      <h1 class="display-5 fw-bold font-poppins">Top PMS Car Rental Questions</h1>
      <p class="text-body-secondary">Find answers to the most commonly asked questions...</p>
      <p class="text-body-secondary">Looking for the full picture instead? Read our <a href="how-it-works.php">step-by-step rental guide</a>.</p>
    </div>
    <div class="accordion" id="faqAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq1">What is PMS?</button></h2>
        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">PMS is the name of our company. We offer car rentals and services.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq2">What do you offer?</button></h2>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">We offer car rentals and services. Our cars are well-maintained, widely available,
            and good pricing with no hidden fees</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq3">What documents do I need to rent a car?</button></h2>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">You’ll need a valid driver’s license, one (1) government-issued ID, and a credit
            or debit card under your name for the security deposit. Drivers must present their official transaction receipt before driving.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq4">What is the minimum age to rent a car?</button></h2>
        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">The minimum age requirement is 18 years old.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq5">Are pets allowed inside the car?</button></h2>
        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Pets are allowed in selected vehicles. Please return the car clean and free of pet
            hair or odors.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq6">What should I do if the car breaks down or gets into an accident?</button></h2>
        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Contact our 24/7 roadside assistance hotline immediately. Do not attempt repairs
            on your own. In case of an accident, file a police report and notify us
            right away.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq7">Can I return the car to a different location?</button></h2>
        <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Yes. Choose your pickup and drop-off branch when you book. Returning to a
            different branch than you picked up from adds a relocation fee — free for the same branch, ₱300 for a
            nearby branch, or ₱500 for a farther one — shown in your booking summary before you confirm.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq8">How to rent a car?</button></h2>
        <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">First, browse our vehicles and pick the one that fits your trip. Second, fill
            out the booking form with your dates, contact details and required documents, and submit your request —
            nothing is charged yet. Third, once our team approves your request, pay the rental fee and refundable
            security deposit within 48 hours to confirm your booking. Finally, pick up your vehicle at your selected
            branch on the scheduled date. See our <a href="how-it-works.php">full step-by-step guide</a> for more
            detail.</div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
</body>

</html>