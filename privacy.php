<?php
session_start();
// No DB usage on this page.
define('PRIVACY_POLICY_VERSION', '1.0');
define('PRIVACY_POLICY_LAST_UPDATED', 'August 21, 2026');
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
  <title>PMS Car Rental — Privacy Policy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Animate.css for entrance/scroll animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>

  <main class="container py-5 navbar-offset">

    <div class="row">
      <div class="col-lg-8 mx-auto">

        <div class="mb-5">
          <div class="section-eyebrow">LEGAL</div>
          <h1 class="fw-bold font-poppins">Privacy Policy</h1>
          <p class="text-body-secondary">
            Version <?= htmlspecialchars(PRIVACY_POLICY_VERSION, ENT_QUOTES) ?> ·
            Last updated <?= htmlspecialchars(PRIVACY_POLICY_LAST_UPDATED, ENT_QUOTES) ?>
          </p>
        </div>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">1. Introduction</h2>
          <p>
            PMS Car Rental ("PMS," "we," "us," or "our") operates this car rental platform, including
            the website and any associated booking services (the "Service"). This Privacy Policy
            explains what personal information we collect from users of the Service, why we collect
            it, how long we keep it, who we share it with, and how we protect it. This Policy is
            written to align with the Data Privacy Act of 2012 (Republic Act No. 10173) of the
            Philippines.
          </p>
          <p>
            By creating an account, submitting a booking, or otherwise using the Service, you
            acknowledge that you have read and understood this Policy.
          </p>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">2. Information We Collect</h2>
          <p>When you register and use the Service, we collect:</p>
          <ul>
            <li><strong>Account information</strong> — your name, email address, and password (stored in encrypted/hashed form, never in plain text).</li>
            <li><strong>Identity and eligibility information</strong> — your age and a copy of your driver's licence.</li>
            <li><strong>Contact information</strong> — your mobile/contact number.</li>
            <li><strong>Booking and transaction information</strong> — the vehicles you reserve, rental dates, pickup/drop-off details, payment amounts, and related booking history.</li>
          </ul>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">3. Purpose of Collection</h2>
          <p>We collect and process the categories of information above for the following specific purposes:</p>
          <ul>
            <li>
              <strong>Age &amp; Driver's Licence.</strong> Needed to verify that you meet the legal
              driving age under Philippine law and to validate the legality of the driver for
              insurance compliance purposes before a rental is approved.
            </li>
            <li>
              <strong>Contact Number.</strong> Needed for booking confirmations, coordinating vehicle
              pickup and drop-off, and providing emergency roadside assistance during your rental
              period.
            </li>
            <li>
              <strong>Account Credentials.</strong> Needed to secure your account and prevent
              unauthorized access to your bookings and personal information.
            </li>
          </ul>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">4. Data Retention</h2>
          <p>
            We retain your personal information for as long as your account remains active and for
            as long as reasonably necessary to fulfill the purposes described in this Policy,
            including completing bookings, resolving disputes, and meeting insurance and legal
            obligations related to a rental.
          </p>
          <p>
            <strong>A fixed retention schedule for driver's licence and booking records has not yet
            been finalized.</strong> We are committed to not retaining this information longer than
            necessary, and this section will be updated with a specific retention period once one is
            formally adopted. If you wish to request deletion of your data sooner, see Section 7
            below.
          </p>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">5. Data Sharing &amp; Third Parties</h2>
          <p>
            We do not sell your personal information. We may disclose your contact number and
            driver's licence details to the following categories of recipients, only where necessary:
          </p>
          <ul>
            <li>Third-party vehicle fleet partners or owners, for the specific vehicle you have booked.</li>
            <li>Insurance providers, to process a claim arising from your rental.</li>
            <li>Law enforcement authorities, in the event of an accident, traffic violation, or other legal obligation to report.</li>
          </ul>
          <p>
            We do not currently have active data-sharing integrations with third parties beyond what
            is described above; this section states our disclosure practice as a matter of policy,
            regardless of whether a given sharing scenario has occurred.
          </p>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">6. Security Measures</h2>
          <p>
            Because a driver's licence is a government-issued identity document, we apply stricter
            safeguards to it and to your other personal information. Personal information is
            encrypted both in transit (e.g., over HTTPS) and at rest. Access to stored personal
            information is restricted to what is necessary to operate the Service.
          </p>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">7. Your Rights</h2>
          <p>Under the Data Privacy Act of 2012, you have the right to:</p>
          <ul>
            <li>Be informed that your personal information will be, is being, or was processed;</li>
            <li>Access and request a copy of your personal information that we hold;</li>
            <li>Request correction of inaccurate or outdated personal information;</li>
            <li>Request deletion or blocking of your personal information, subject to our legal and insurance record-keeping obligations described in Section 4; and</li>
            <li>Object to the processing of your personal information, and lodge a complaint with the National Privacy Commission.</li>
          </ul>
          <p>To exercise any of these rights, contact us using the details in Section 8.</p>
        </section>

        <section class="mb-5">
          <h2 class="h4 fw-bold font-poppins">8. Contact</h2>
          <p>
            For inquiries regarding this platform's data privacy practices, please contact our
            designated project data representative:
          </p>
          <div class="card border-0 bg-body-tertiary">
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">Dev Team Lead</dt>
                <dd class="col-sm-8">PMSQR</dd>
                <dt class="col-sm-4">Project Group Name</dt>
                <dd class="col-sm-8">PMS Corp.</dd>
                <dt class="col-sm-4">Institution</dt>
                <dd class="col-sm-8">Technological Institute of the Philippines, Quezon City</dd>
                <dt class="col-sm-4">Dedicated Email</dt>
                <dd class="col-sm-8"><a href="mailto:placeholder@gmail.com">placeholder@gmail.com</a></dd>
              </dl>
            </div>
          </div>
        </section>

      </div>
    </div>

  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <!-- Added 2026-08-22 in the same position faq.php uses — immediately after confirm.js. -->
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
</body>

</html>
