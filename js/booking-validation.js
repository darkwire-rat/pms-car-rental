// Client-side booking-window UX helpers for the vehicles.php booking modal.

(function () {
  'use strict';

  var MIN_RENTAL_HOURS = 24;
  var MIN_RENTAL_MS = MIN_RENTAL_HOURS * 60 * 60 * 1000;

  // Local-timezone "YYYY-MM-DD".
  function toLocalDateStr(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  // Local-timezone "HH:MM" (matches an <input type="time"> value).
  function toLocalTimeStr(date) {
    var h = String(date.getHours()).padStart(2, '0');
    var m = String(date.getMinutes()).padStart(2, '0');
    return h + ':' + m;
  }

  // Combine a date input value with a time input value into a local-time Date.
  function combineDateTime(dateStr, timeStr) {
    if (!dateStr) return null;
    var dp = dateStr.split('-');
    if (dp.length !== 3) return null;
    var tp = (timeStr || '00:00').split(':');
    var dt = new Date(
      Number(dp[0]),
      Number(dp[1]) - 1,
      Number(dp[2]),
      Number(tp[0]) || 0,
      Number(tp[1]) || 0,
      0, 0
    );
    return isNaN(dt.getTime()) ? null : dt;
  }

  // Ask js/app.js to refresh the server-computed price + duration readout.
  function refreshPreview() {
    if (
      $('#vehicle_id').val() &&
      $('#rental_date').val() &&
      $('#return_date').val() &&
      typeof window.refreshBookingPreview === 'function'
    ) {
      window.refreshBookingPreview();
    }
  }

  // Back-compat: the old public name, still called from js/app.js's voucher handler and elsewhere.
  window.calculateTotalPrice = refreshPreview;

  function setupDateValidation() {
    var $rentalDate = $('#rental_date');
    var $returnDate = $('#return_date');
    var $pickupTime = $('#pickup_time');
    var $dropoffTime = $('#dropoff_time');

    if (!$rentalDate.length) return;

    // Pickup cannot be in the past.
    $rentalDate.attr('min', toLocalDateStr(new Date()));

    // Derive the return-date minimum from the full pickup datetime = rental_date + pickup_time.
    function syncReturnMinimum() {
      var pickup = combineDateTime($rentalDate.val(), $pickupTime.val());
      if (!pickup) return;

      var earliestReturn = new Date(pickup.getTime() + MIN_RENTAL_MS);
      var earliestReturnDate = toLocalDateStr(earliestReturn);

      $returnDate.attr('min', earliestReturnDate);

      // Drag an out-of-range return date up to the earliest valid day.
      if ($returnDate.val() && $returnDate.val() < earliestReturnDate) {
        $returnDate.val(earliestReturnDate);
      }

      // If the return falls on exactly the earliest valid day.
      if ($returnDate.val() === earliestReturnDate) {
        var earliestReturnTime = toLocalTimeStr(earliestReturn);
        $dropoffTime.attr('min', earliestReturnTime);
        if ($dropoffTime.val() && $dropoffTime.val() < earliestReturnTime) {
          $dropoffTime.val(earliestReturnTime);
        }
      } else {
        $dropoffTime.removeAttr('min');
      }

      refreshPreview();
    }

    // The minimum must react to EITHER the pickup date OR the pickup time.
    $rentalDate.on('change', syncReturnMinimum);
    $pickupTime.on('change', syncReturnMinimum);

    // Return-side changes only need a fresh server preview.
    $returnDate.on('change', refreshPreview);
    $dropoffTime.on('change', refreshPreview);
  }

  $(function () {
    setupDateValidation();

    // Voucher and branch changes re-price through the same server preview.
    $('#voucherSelect, #pickup_location, #dropoff_location').on('change', function () {
      if ($('#rental_date').val() && $('#return_date').val()) {
        refreshPreview();
      }
    });
  });
})();
