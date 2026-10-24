<?php
// The ten-step rental process, built once and rendered in three places so the three copies cannot drift out of sync with each other.
$rentalProcessSteps = [
  ['icon' => 'fa-car',             'title' => 'Browse & choose a vehicle',
    'body' => 'Filter by category, price, seats and more, then pick the vehicle that fits your trip.'],
  ['icon' => 'fa-calendar-check',  'title' => 'Pick dates, fill in your details, accept the policy',
    'body' => 'Choose your pickup and return dates and branches, provide your licence and contact details, and agree to the rental policy.'],
  ['icon' => 'fa-paper-plane',     'title' => 'Submit your request',
    'body' => 'Nothing is charged yet — your request goes to our team for review.'],
  ['icon' => 'fa-user-check',      'title' => 'We review your request',
    'body' => 'We may approve it outright, or ask you for more information first.'],
  ['icon' => 'fa-credit-card',     'title' => 'Pay the rental fee + refundable deposit',
    'body' => 'Once approved, pay within 48 hours to confirm your booking. The security deposit is fully refundable.'],
  ['icon' => 'fa-key',             'title' => 'Pick up the vehicle',
    'body' => 'We record the fuel level and hand over the keys.'],
  ['icon' => 'fa-road',            'title' => 'Enjoy your rental',
    'body' => 'The vehicle is yours for the dates you booked.'],
  ['icon' => 'fa-flag-checkered',  'title' => 'Return the vehicle',
    'body' => 'Return it on schedule, or request an early return if your plans change.'],
  ['icon' => 'fa-clipboard-check', 'title' => 'Inspection & deposit settlement',
    'body' => 'We inspect the vehicle and settle your deposit — refunded in full if there is nothing to deduct.'],
  ['icon' => 'fa-star',            'title' => 'Leave a review',
    'body' => 'Tell other customers about your experience.'],
];
?>
<ol class="list-unstyled rental-process-list mb-0">
  <?php foreach ($rentalProcessSteps as $i => $step): ?>
    <li class="d-flex gap-3 <?php echo $i < count($rentalProcessSteps) - 1 ? 'mb-4' : ''; ?>">
      <div class="step-badge rounded-circle flex-shrink-0" aria-hidden="true"><?php echo $i + 1; ?></div>
      <div>
        <h3 class="h6 fw-bold mb-1">
          <i class="fas <?php echo htmlspecialchars($step['icon']); ?> me-2 text-primary" aria-hidden="true"></i><?php echo htmlspecialchars($step['title']); ?>
        </h3>
        <p class="text-body-secondary mb-0"><?php echo htmlspecialchars($step['body']); ?></p>
      </div>
    </li>
  <?php endforeach; ?>
</ol>
