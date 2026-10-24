$(function () {
  // Navbar active highlighting
  var path = window.location.pathname.split('/').pop();
  if (!path) path = 'index.html';
  $('.nav-link').removeClass('active').removeAttr('aria-current');
  $('.nav-link').each(function () {
    var href = $(this).attr('href');
    if (href === path) {
      $(this).addClass('active').attr('aria-current', 'page');
    }
  });
});


  // Datepicker and timepicker
  if (typeof $.fn.datepicker === 'function') {
    $("#rentalDate, #returnDate").datepicker({
      dateFormat: "yy-mm-dd",
      minDate: 0
    });
  }


  if (typeof $.fn.timepicker === 'function') {
    $("#pickupTime, #dropoffTime").timepicker({
      timeFormat: 'HH:mm',
      interval: 30,
      minTime: '00:00',
      maxTime: '23:30',
      dynamic: false,
      dropdown: true,
      scrollbar: true
    });
  }
  


  $(function() {
  let selectedVehicleId = null;
  let previewData = null;


  // Open modal with vehicle ID
  $(document).on('click', '.btn-book', function() {
    selectedVehicleId = $(this).data('vehicle-id');
    $('#vehicle_id').val(selectedVehicleId);
    $('#bookingForm')[0].reset();
    $('#bookingPreview').empty();
    $('#receiptArea').addClass('d-none');
    $('#btnConfirm').prop('disabled', true);
    $('#bookingAlert').addClass('d-none');
    $('#bookingModal').modal('show');
  });


  // Refresh booking pricing preview.
  window.refreshBookingPreview = async function refreshBookingPreview() {
    const $btnConfirm = $('#btnConfirm');
    const vehicle_id = $('#vehicle_id').val();
    const rental_date = $('#rental_date').val();
    const return_date = $('#return_date').val();
    const pickup_time = $('#pickup_time').val();
    const dropoff_time = $('#dropoff_time').val();
    const contact_number = $('#contact_number').val();
    const voucher_code = $('#voucherSelect').val();
    const age = parseInt($('#age').val(), 10);

    if (!vehicle_id || !rental_date || !return_date) {
      showAlert('Please fill in required fields.', 'danger');
      $btnConfirm.prop('disabled', true);
      return false;
    }

    const payload = {
      is_preview: true,
      vehicle_id,
      rental_date,
      return_date,
      pickup_time,
      dropoff_time,
      contact_number,
      age,
      voucher_code,
      pickup_location_id: $('#pickup_location').val() || 0,
      dropoff_location_id: $('#dropoff_location').val() || 0
    };

    PMSMotion.setButtonLoading($btnConfirm, true);
    let success = false;
    try {
      const res = await fetch('reserve_preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.error) {
        showAlert(data.error || 'Preview failed', 'danger');
        return false;
      }

      const { days = 1, rate = 0, subtotal = 0, discount = 0, total = 0, duration_label = '', location_fee = 0,
              security_deposit = 0, amount_due_at_payment = 0, pickup_at_label = '', dropoff_at_label = '' } = data;
      const billableDaysText = `${days} billable day${days === 1 ? '' : 's'}`;
      const locationFeeRow = (Number(location_fee) > 0 || $('#pickup_location').val())
        ? `<p class="mb-1">Location fee: ₱${Number(location_fee).toLocaleString()}</p>`
        : '';
      // Duration + billable days come straight from reserve_preview.php.
      const durationText = duration_label
        ? `${duration_label} · ${billableDaysText}`
        : billableDaysText;

      // Vehicle name/thumbnail are reused from #bookingModal's data.
      const vehicleNameRaw = $('#bookingModal').data('vehicleName') || '';
      const vehicleName = $('<div>').text(vehicleNameRaw).html();
      const vehicleThumbnail = $('#bookingModal').data('vehicleThumbnail');
      const vehicleRowHtml = vehicleThumbnail
        ? `<div class="d-flex align-items-center gap-2 mb-3">
             <img src="assets/${vehicleThumbnail}" alt="${vehicleName}" class="booking-preview-thumb rounded-3">
             <span class="fw-semibold">${vehicleName}</span>
           </div>`
        : '';

      // Pickup/return datetimes shown alongside duration.
      $('#bookingPreview').html(`
        ${vehicleRowHtml}
        <div class="border rounded p-3 bg-body-tertiary">
          <h6 class="mb-2">Booking Summary</h6>
          ${pickup_at_label ? `<p class="mb-1">Pickup: <strong>${pickup_at_label}</strong></p>` : ''}
          ${dropoff_at_label ? `<p class="mb-1">Return: <strong>${dropoff_at_label}</strong></p>` : ''}
          <p class="mb-1">Duration: <strong>${durationText}</strong></p>
          <p class="mb-1">Rate per day: ₱${Number(rate).toLocaleString()}</p>
          <p class="mb-1">Rental subtotal: ₱${Number(subtotal).toLocaleString()}</p>
          ${locationFeeRow}
          ${Number(discount) > 0 ? `<p class="mb-1 text-danger">Discount: −₱${Number(discount).toLocaleString()}</p>` : ''}
          <hr>
          <p class="mb-1"><strong>Rental total: ₱${Number(total).toLocaleString()}</strong></p>
          <div class="border rounded p-2 my-2 bg-body">
            <div class="fw-semibold small">Security Deposit <span class="text-body-secondary">(refundable)</span></div>
            <div>₱${Number(security_deposit).toLocaleString()}</div>
            <div class="small text-body-secondary">Held during your rental, returned after a clean return &amp; inspection.</div>
          </div>
          <h5 class="mt-2">Due after approval: ₱${Number(amount_due_at_payment).toLocaleString()}</h5>
          <p class="small text-body-secondary mb-0">Nothing is charged now.</p>
        </div>
      `).addClass('is-visible');

      showAlert('', 'success'); // hide previous alerts
      success = true;
      return true;
    } catch (err) {
      console.error('Preview error:', err);
      showAlert('Error generating preview.', 'danger');
      return false;
    } finally {
      // SetButtonLoading unconditionally re-enables the button.
      PMSMotion.setButtonLoading($btnConfirm, false);
      $btnConfirm.prop('disabled', !success);
    }
  };


// The dead #previewBtn handler that lived here was removed.




  // === Confirm Booking ===
$('#btnConfirm').on('click', async function () {
  const $btn = $(this);
  const vehicle_id = $('#vehicle_id').val();
  const rental_date = $('#rental_date').val();
  const return_date = $('#return_date').val();
  const pickup_time = $('#pickup_time').val();
  const dropoff_time = $('#dropoff_time').val();
  const contact_number = $('#contact_number').val();
  const age = parseInt($('#age').val());
  const voucher_code = $('#voucherSelect').val();


  // Basic front-end check. browser alert replaced with the modal's own inline alert region.
  if (!vehicle_id || !rental_date || !return_date || !contact_number || !age) {
    showAlert('Please complete all required fields.', 'danger');
    return;
  }
  if (age < 18) {
    showAlert('You must be at least 18 years old to rent a car.', 'danger');
    return;
  }
  // Policy acceptance + the profile requirements gate. reserve.php re-validates both server-side.
  if (!$('#policy_accepted').is(':checked')) {
    showAlert('Please read and accept the rental policy.', 'danger');
    return;
  }
  if ($('#requirementsGate').data('complete') !== 1) {
    showAlert('Add your licence, verification photo and address on your profile before booking.', 'danger');
    return;
  }


  // Confirm before submitting. no money changes hands here.
  const confirmed = await window.PMSConfirm({
    title: 'Submit this booking request?',
    body: 'Your booking will be submitted for approval. Nothing is charged now — you\'ll pay the rental fee and a refundable security deposit once it\'s approved.',
    confirmLabel: 'Submit Request',
    variant: 'success'
  });
  if (!confirmed) return;


  PMSMotion.setButtonLoading($btn, true);
  let isNavigatingAway = false;
  try {
    const res = await fetch('reserve.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        vehicle_id,
        rental_date,
        return_date,
        pickup_time,
        dropoff_time,
        voucher_code,
        contact_number,
        age,
        pickup_location_id: $('#pickup_location').val() || 0,
        dropoff_location_id: $('#dropoff_location').val() || 0,
        policy_accepted: true,
        policy_version: $('#policy_version').val()
      })
    });


    const data = await res.json();


    if (data.success) {
      // Redirect to receipt page using booking_id or booking_ref
      isNavigatingAway = true;
      const bookingId = data.booking_id || null;
      const ref = data.booking_ref || null;
      if (bookingId) {
        window.location.href = 'receipt.php?id=' + encodeURIComponent(bookingId);
      } else if (ref) {
        window.location.href = 'receipt.php?ref=' + encodeURIComponent(ref);
      } else {
        // fallback: hide modal and reload
        $('#bookingModal').modal('hide');
        location.reload();
      }
    } else {
      console.error('Booking failed:', data);
      showAlert(data.error || 'Booking failed.', 'danger');
    }
  } catch (err) {
    console.error('Network error:', err);
    showAlert('Something went wrong while submitting your booking.', 'danger');
  } finally {
    if (!isNavigatingAway) PMSMotion.setButtonLoading($btn, false);
  }
});




  // A second, redundant #bookingForm submit handler that lived here was removed.


  function showAlert(msg, type) {
    $('#bookingAlert').removeClass('d-none alert-danger alert-success')
                      .addClass(`alert-${type}`)
                      .text(msg);
  }
});




  // Simple accordion fallback for non-Bootstrap behavior (if needed)
  $('.accordion-button').on('click', function () {
    var target = $(this).data('bs-target') || $(this).attr('data-bs-target');
    if (!target) return;
    $(target).collapse('toggle');
  });


  // Show modals
  $(document).on('click', '.btn-login', function () {
    $('#loginModal').modal('show');
  });
  $(document).on('click', '.btn-signup', function () {
    $('#signupModal').modal('show');
  });


  // "Login as Admin" button removed.


  // The dead "Booking form " #bookingForm submit handler that used to live here was removed.


  // Show validation feedback
  function showValidation(elementId, message, type = 'danger') {
    $(`#${elementId}Error`).removeClass('d-none').text(message);
  }


  function hideValidation(elementId) {
    $(`#${elementId}Error`).addClass('d-none').text('');
  }


  // Show floating alert
  function showFloatingAlert(message, type = 'danger') {
    const alert = $(`
      <div class="alert alert-${type} alert-floating shadow-sm">
        ${message}
        <button type="button" class="btn-close ms-3" data-bs-dismiss="alert"></button>
      </div>
    `);
   
    $('body').append(alert);
    setTimeout(() => alert.remove(), 5000);
  }


  // Only allow modal if logged in
  $('#openBookingMultiModal').on('click', async function () {
    const response = await fetch('check_login.php');
    const data = await response.json();
   
    if (!data.loggedIn) {
      showFloatingAlert('Please log in to make a booking', 'warning');
      $('#loginModal').modal('show');
      return;
    }
   
    $('#bookingMultiModal').modal('show');
    loadVouchers(); // Load available vouchers when modal opens
  });  // Step navigation
  function showStep(step) {
    $('.booking-step').addClass('d-none').hide();
    $('#bookingStep' + step).removeClass('d-none').fadeIn(200);
    // Focus first input for accessibility
    $('#bookingStep' + step).find('input,select,button').filter(':visible').first().focus();
  }


  function resetBookingModal() {
  const form = document.getElementById('bookingForm');
  if (form) form.reset(); // Prevents undefined error


  $('#bookingAlert').addClass('d-none').text('');
  $('#bookingPreview').empty();
  $('#btnConfirm').addClass('d-none');
  $('#receiptArea').addClass('d-none');
}


  // Step 1 → Step 2
  $('#bookingStep1Next').on('click', function () {
    // Validate fields
    let valid = true;
    $('#bookingStep1 input').each(function () {
      if (!$(this).val()) valid = false;
    });
    const age = parseInt($('#driverAge').val(), 10);
    if (isNaN(age) || age < 18) {
      $('#bookingStep1Alert').removeClass('d-none').text('You must be 18 or older to rent a vehicle. Please edit your information.');
      $('#driverAge').focus();
      return;
    } else {
      $('#bookingStep1Alert').addClass('d-none').text('');
    }
    if (!valid) {
      $('#bookingStep1Alert').removeClass('d-none').text('Please fill out all fields.');
      return;
    }
    // Prepare summary from booking form (index.html)
    let bookingData = getBookingFormData();
    let days = bookingData.days;
    let rate = bookingData.rate;
    let total = days * rate;
    $('#bookingSummary').html(`
      <div><strong>Car Type:</strong> ${bookingData.carType}</div>
      <div><strong>Rate per Day:</strong> ₱${rate}</div>
      <div><strong>Rental Period:</strong> ${bookingData.rentalDate} to ${bookingData.returnDate} (${days} days)</div>
      <div><strong>Total Cost:</strong> <span class="text-success fw-bold">₱${total}</span></div>
    `);
    showStep(2);
  });


  // Load available vouchers
  async function loadVouchers() {
    try {
      const response = await fetch('get_vouchers.php');
      const vouchers = await response.json();
     
      const $select = $('#voucherSelect').empty();
      $select.append('<option value="">Select a voucher (optional)</option>');
     
      vouchers.forEach(voucher => {
        // A voucher is either percent-based or flat-amount — showing "0% off" for a flat voucher was the previous bug here.
        const label = Number(voucher.discount_pct) > 0
          ? `${voucher.discount_pct}% off`
          : `₱${Number(voucher.discount_amount).toLocaleString()} off`;
        $select.append(`<option value="${voucher.code}">
          ${voucher.code} - ${label}
        </option>`);
      });
    } catch (err) {
      console.error('Error loading vouchers:', err);
    }
  }


  // Step 2: Payment method fields
  $('#paymentMethod').on('change', function () {
    const method = $(this).val();
    $('#paymentFields').empty();
   
    switch(method) {
      case 'credit_card':
        $('#paymentFields').html(`
          <div class="mb-3">
            <label class="form-label">Card Number</label>
            <input type="text" class="form-control" name="card_number" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Expiry Date</label>
              <input type="text" class="form-control" name="card_expiry" placeholder="MM/YY" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">CVV</label>
              <input type="text" class="form-control" name="card_cvv" required>
            </div>
          </div>
        `);
        break;
       
      case 'gcash':
        $('#paymentFields').html(`
          <div class="mb-3">
            <label class="form-label">GCash Number</label>
            <input type="text" class="form-control" name="gcash_number" required>
          </div>
        `);
        break;
    }
  });


  // Handle voucher application
  $('#voucherCode').on('change', async function() {
    const code = $(this).val();
    if (!code) return;
   
    const total = parseFloat($('#totalAmount').data('original')) || 0;
   
    try {
      const response = await fetch('apply_voucher.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include', // this sends PHP session cookies
        body: JSON.stringify({
          voucher_code: code,
          total_amount: total
        })
      });
     
      const result = await response.json();
     
      if (result.success) {
        $('#discountAmount').text(`-₱${result.discount.toFixed(2)}`);
        $('#finalTotal').text(`₱${result.final_total.toFixed(2)}`);
        $('#totalAmount').val(result.final_total);
        showAlert('Voucher applied successfully!', 'success');
      } else {
        showAlert(result.message, 'danger');
        $(this).val(''); // Clear invalid voucher
      }
    } catch (err) {
      console.error(err);
      showAlert('Error applying voucher', 'danger');
    }
  });  // Step 2 → Step 1 (Back)
  $('#bookingStep2Back').on('click', function () {
    showStep(1);
  });




  // Step 2 → Step 3 (Confirm Payment)
  /* $.on('click', function { // Validate payment const method = $.val. */


  // The dead #bookingStep2Confirm handler that lived here was removed.






  // Step 3 → Close
  $('#bookingStep3Close').on('click', function () {
    $('#bookingMultiModal').modal('hide');
    resetBookingModal();
  });


  // Utility: Get booking form data from index.html
  function getBookingFormData() {
    // Try to get from booking form (index.html)
    let carType = $('#carType').val() || 'Sedan';
    let rentalDate = $('#rentalDate').val() || '2025-10-14';
    let returnDate = $('#returnDate').val() || '2025-10-15';
    let pickupTime = $('#pickupTime').val() || '09:00';
    let dropoffTime = $('#dropoffTime').val() || '17:00';
    let rate = 1200; // Default rate
    // You can map carType to rate if needed
    if (carType === 'SUV') rate = 2500;
    if (carType === 'Minivan') rate = 1800;
    if (carType === 'Van') rate = 3500;
    if (carType === 'Scooter') rate = 600;
    // Calculate days
    let days = 1;
    try {
      let d1 = new Date(rentalDate);
      let d2 = new Date(returnDate);
      days = Math.max(1, Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)));
    } catch (e) { days = 1; }
    return { carType, rentalDate, returnDate, pickupTime, dropoffTime, rate, days };
  }


  // Accessibility: Focus trap inside modal
  $('#bookingMultiModal').on('shown.bs.modal', function () {
    $(this).attr('tabindex', '-1').focus();
  });


  // Keyboard navigation for steps
  $('.booking-step').on('keydown', function (e) {
    if (e.key === 'Enter') {
      if ($(this).is('#bookingStep1')) $('#bookingStep1Next').click();
      if ($(this).is('#bookingStep2')) $('#bookingStep2Confirm').click();
    }
  });


  $('#bookingFormModern input, #bookingFormModern select').on('focus', function () {
    var step = $(this).closest('.form-group').index() + 1;
    $('.booking-step-indicator .step-circle').removeClass('active');
    $('.booking-step-indicator .step-circle').eq(step - 1).addClass('active');
  });


  // Animate on scroll for feature cards
  $(window).on('scroll', function () {
    $('.feature-card').each(function () {
      if ($(this).offset().top < $(window).scrollTop() + $(window).height() - 100) {
        $(this).addClass('animate__fadeInUp');
      }
    });
  });


  // Example: quick search input with fade on key
  $('.hero-immersive input[type="text"]').on('input', function () {
    $(this).addClass('animate__pulse');
    setTimeout(() => $(this).removeClass('animate__pulse'), 250);
  });


;


// ===================== AUTHENTICATION (SERVER-BASED) =====================


// Ask PHP who is currently logged in
async function getMe() {
  try {
    const res = await fetch('me.php');
    const data = await res.json();
    return data;
  } catch (err) {
    console.error('Error checking user session:', err);
    return { logged_in: false };
  }
}


// First letter of the first word + first letter of the last word; single-word names fall back to just that letter.
function getInitials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}


// Renders either an <img> or an initials-circle fallback.
function getAvatarHtml(name, profilePicturePath, sizePx, fontSizePx) {
  const size = sizePx || 36;
  const fontSize = fontSizePx || Math.round(size * 0.4);
  if (profilePicturePath) {
    return `<img src="${profilePicturePath}" alt="" class="rounded-circle" style="width:${size}px;height:${size}px;object-fit:cover;">`;
  }
  return `<span class="avatar-circle text-white" style="width:${size}px;height:${size}px;font-size:${fontSize}px;">${getInitials(name)}</span>`;
}


// Register a new user
async function registerUser(name, email, password, privacyConsent) {
  const res = await fetch('register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password, privacy_consent: privacyConsent })
  });
  return await res.json();
}


// Log in existing user
async function loginUser(email, password) {
  const res = await fetch('login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  return await res.json();
}


// Log out current user — BUGS.md.
async function logoutUser() {
  try {
    const res = await fetch('logout.php');
    await res.text(); // drain the body: guarantees the request completed
  } catch (err) {
    console.error('Logout request failed', err);
  }
  window.location.reload(); // refresh the page after logout
}


// Request a password-reset code.
async function requestPasswordReset(email) {
  const res = await fetch('forgot_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
  return await res.json();
}


// Consume a reset code and set a new password
async function resetPassword(email, code, newPassword) {
  const res = await fetch('reset_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, code, new_password: newPassword })
  });
  return await res.json();
}


// FRONT-END HANDLERS.


// AUTH FIELD VALIDATION Fills the ".is-invalid never set" gap documented in Motion Design previously every auth error surfaced only.
const AuthValidation = (function () {
  // Looks up the field's .invalid-feedback element by the project's fixed naming convention.
  function feedbackFor($input) {
    return $('#' + $input.attr('id') + 'Feedback');
  }

  function setInvalid($input, message) {
    $input.addClass('is-invalid').removeClass('is-valid').attr('aria-invalid', 'true');
    feedbackFor($input).text(message);
  }

  function setValid($input) {
    $input.removeClass('is-invalid').addClass('is-valid').attr('aria-invalid', 'false');
  }

  function clearField($input) {
    $input.removeClass('is-invalid is-valid').removeAttr('aria-invalid');
    feedbackFor($input).text('');
  }

  function clearForm($form) {
    // Form-check-input added for #signupPrivacyConsent.
    $form.find('.form-control, .form-check-input').each(function () {
      clearField($(this));
    });
  }

  // Returns the first failing rule's message, or null if all rules pass. rules.
  function firstError(value, rules) {
    for (const rule of rules) {
      if (!rule.test(value)) return rule.message;
    }
    return null;
  }

  // Wires blur then input to a single field.
  function attachRealTime($input, rules, options) {
    options = options || {};
    let touched = false;

    function check() {
      const error = firstError($input.val(), rules);
      if (error) {
        setInvalid($input, error);
      } else if (options.showValid) {
        setValid($input);
      } else {
        clearField($input);
      }
      return !error;
    }

    $input.on('blur', function () {
      touched = true;
      check();
    });
    $input.on('input', function () {
      if (touched) check();
    });

    return check;
  }

  // Validates every field in fieldConfigs = [{ $input.
  function validateAll(fieldConfigs) {
    let $firstInvalid = null;
    let allValid = true;
    fieldConfigs.forEach(function (cfg) {
      const error = firstError(cfg.$input.val(), cfg.rules);
      if (error) {
        setInvalid(cfg.$input, error);
        allValid = false;
        if (!$firstInvalid) $firstInvalid = cfg.$input;
      } else if (cfg.showValid) {
        setValid(cfg.$input);
      } else {
        clearField(cfg.$input);
      }
    });
    if ($firstInvalid) $firstInvalid.trigger('focus');
    return allValid;
  }

  // Reusable rule builders shared by every auth form.
  const rules = {
    required: function (message) {
      return { test: (v) => v.trim().length > 0, message: message };
    },
    email: function (message) {
      return { test: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()), message: message };
    },
    minLength: function (min, message) {
      return { test: (v) => v.length >= min, message: message };
    },
    matches: function (getOtherValue, message) {
      return { test: (v) => v === getOtherValue(), message: message };
    },
    pattern: function (regex, message) {
      return { test: (v) => regex.test(v.trim()), message: message };
    },
    // Every other rule tests the string firstError/validateAll pass in.
    checked: function ($input, message) {
      return { test: () => $input.is(':checked'), message: message };
    },
  };

  return { attachRealTime, validateAll, clearField, clearForm, rules };
})();


// Shows an auth-form error: sets the message, shakes the alert, and moves focus to it.
function showAuthError($alert, message) {
  $alert.text(message).removeClass('d-none');
  $alert.addClass('animate__animated animate__shakeX');
  setTimeout(function () {
    $alert.removeClass('animate__animated animate__shakeX');
  }, 500);
  $alert.trigger('focus');
}


$(function () {
  // Login form fields — real-time validation.
  AuthValidation.attachRealTime($('#loginEmail'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);
  AuthValidation.attachRealTime($('#loginPassword'), [
    AuthValidation.rules.required('Please enter your password.'),
  ]);


  // "Forgot your password?" link — dismisses the login modal and opens #forgotPasswordModal if it exists.
  $(document).on('click', '.btn-forgot-password', function () {
    const $forgotModal = $('#forgotPasswordModal');
    if ($forgotModal.length) {
      $forgotModal.modal('show');
    }
  });


  // Signup form fields — real-time validation, matching register.php:24's actual server rule.
  AuthValidation.attachRealTime($('#signupName'), [
    AuthValidation.rules.required('Please enter your name.'),
  ]);
  AuthValidation.attachRealTime($('#signupEmail'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);
  AuthValidation.attachRealTime($('#signupPassword'), [
    AuthValidation.rules.required('Please enter a password.'),
    AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
  ]);
  const checkConfirmPassword = AuthValidation.attachRealTime($('#signupConfirmPassword'), [
    AuthValidation.rules.required('Please confirm your password.'),
    AuthValidation.rules.matches(() => $('#signupPassword').val(), 'Passwords do not match.'),
  ], { showValid: true });

  // Cross-validation: if the user edits #signupPassword after already typing a confirmation.
  $('#signupPassword').on('input', function () {
    if ($('#signupConfirmPassword').val()) {
      checkConfirmPassword();
    }
  });


  // Password visibility toggle — shared by #signupPassword and #signupConfirmPassword.
  $(document).on('click', '.btn-toggle-password', function () {
    const $btn = $(this);
    const $input = $($btn.data('target'));
    const $icon = $btn.find('i');
    const showing = $input.attr('type') === 'text';
    $input.attr('type', showing ? 'password' : 'text');
    $icon.toggleClass('fa-eye fa-eye-slash');
    $btn.attr('aria-label', showing ? 'Show password' : 'Hide password');
  });


  // Register form
  $('#signupForm').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#signupError');
    $alert.addClass('d-none').text('');


    // Full-form validation gate, ahead of the fetch.
    const isValid = AuthValidation.validateAll([
      {
        $input: $('#signupName'),
        rules: [AuthValidation.rules.required('Please enter your name.')],
      },
      {
        $input: $('#signupEmail'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
      {
        $input: $('#signupPassword'),
        rules: [
          AuthValidation.rules.required('Please enter a password.'),
          AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
        ],
      },
      {
        $input: $('#signupConfirmPassword'),
        rules: [
          AuthValidation.rules.required('Please confirm your password.'),
          AuthValidation.rules.matches(() => $('#signupPassword').val(), 'Passwords do not match.'),
        ],
        showValid: true,
      },
      {
        // Required privacy-consent checkbox, unticked by default.
        $input: $('#signupPrivacyConsent'),
        rules: [AuthValidation.rules.checked($('#signupPrivacyConsent'), 'Please agree to the Privacy Policy to continue.')],
      },
    ]);
    if (!isValid) return;

    const name = $('#signupName').val().trim();
    const email = $('#signupEmail').val().trim();
    const password = $('#signupPassword').val().trim();
    const privacyConsent = $('#signupPrivacyConsent').is(':checked');


    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await registerUser(name, email, password, privacyConsent);
      if (res.success) {
        $('#signupModal').modal('hide');
        $('#loginModal').modal('show');
      } else {
        showAuthError($alert, res.error || 'Registration failed.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Login form
  $('#loginForm').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#loginError');
    $alert.addClass('d-none').text('');


    // Full-form validation gate, ahead of the fetch.
    const isValid = AuthValidation.validateAll([
      {
        $input: $('#loginEmail'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
      {
        $input: $('#loginPassword'),
        rules: [AuthValidation.rules.required('Please enter your password.')],
      },
    ]);
    if (!isValid) return;

    const email = $('#loginEmail').val().trim();
    const password = $('#loginPassword').val().trim();


    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await loginUser(email, password);
      if (res.success) {
        $('#loginModal').modal('hide');
        window.location.reload();
      } else {
        showAuthError($alert, res.error || 'Login failed.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Forgot Password modal — 3-step flow: email -> code -> new password.
  let forgotEmail = '';
  let forgotCode = '';

  // Shows one step, hides the other two, and focuses its first input. initModalFocus only fires on shown.bs.modal.
  function showForgotStep(stepNumber) {
    $('#forgotStep1, #forgotStep2, #forgotStep3').addClass('d-none');
    const $step = $('#forgotStep' + stepNumber);
    $step.removeClass('d-none');
    $step.find('input').first().trigger('focus');
  }

  AuthValidation.attachRealTime($('#forgotEmail'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);
  AuthValidation.attachRealTime($('#forgotCode'), [
    AuthValidation.rules.required('Please enter the 6-digit code.'),
    AuthValidation.rules.pattern(/^\d{6}$/, 'Please enter the 6-digit code exactly as sent.'),
  ]);
  AuthValidation.attachRealTime($('#forgotNewPassword'), [
    AuthValidation.rules.required('Please enter a password.'),
    AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
  ]);
  const checkForgotConfirmPassword = AuthValidation.attachRealTime($('#forgotConfirmPassword'), [
    AuthValidation.rules.required('Please confirm your password.'),
    AuthValidation.rules.matches(() => $('#forgotNewPassword').val(), 'Passwords do not match.'),
  ], { showValid: true });

  // Same cross-validation pattern as signup.
  $('#forgotNewPassword').on('input', function () {
    if ($('#forgotConfirmPassword').val()) {
      checkForgotConfirmPassword();
    }
  });


  // Step 1 -> Step 2: request the code
  $('#forgotStep1Form').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#forgotError');
    $alert.addClass('d-none').text('');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#forgotEmail'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
    ]);
    if (!isValid) return;

    const email = $('#forgotEmail').val().trim();

    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await requestPasswordReset(email);
      if (res.success) {
        forgotEmail = email;
        $('#forgotStep2Instruction').text('We\'ve sent a 6-digit code to ' + forgotEmail + '.');
        showForgotStep(2);
      } else {
        showAuthError($alert, res.error || 'Something went wrong. Please try again.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // > client-side only — no endpoint verifies the code in isolation.
  $('#forgotStep2Form').on('submit', function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#forgotCode'),
        rules: [
          AuthValidation.rules.required('Please enter the 6-digit code.'),
          AuthValidation.rules.pattern(/^\d{6}$/, 'Please enter the 6-digit code exactly as sent.'),
        ],
      },
    ]);
    if (!isValid) return;

    forgotCode = $('#forgotCode').val().trim();
    showForgotStep(3);
  });


  // Resend code — reuses the same endpoint/email from Step 1
  $('#btnResendCode').on('click', async function () {
    const $btn = $(this);
    const $status = $('#forgotResendStatus');
    PMSMotion.setButtonLoading($btn, true);
    try {
      await requestPasswordReset(forgotEmail);
      $status.text('Code resent! Check your email.');
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Back to preserves the already-entered email so the user can correct a typo without restarting the whole flow from the login modal.
  $('#btnForgotBack').on('click', function () {
    showForgotStep(1);
  });


  // > success: reuses the email and code carried in the closure variables above, plus the new password entered here.
  $('#forgotStep3Form').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#forgotError');
    $alert.addClass('d-none').text('');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#forgotNewPassword'),
        rules: [
          AuthValidation.rules.required('Please enter a password.'),
          AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
        ],
      },
      {
        $input: $('#forgotConfirmPassword'),
        rules: [
          AuthValidation.rules.required('Please confirm your password.'),
          AuthValidation.rules.matches(() => $('#forgotNewPassword').val(), 'Passwords do not match.'),
        ],
        showValid: true,
      },
    ]);
    if (!isValid) return;

    const newPassword = $('#forgotNewPassword').val().trim();

    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await resetPassword(forgotEmail, forgotCode, newPassword);
      if (res.success) {
        $('#forgotPasswordModal').modal('hide');
        $('#loginModal').modal('show');
      } else {
        showAuthError($alert, res.error || 'Password reset failed.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Reset to whenever the modal closes, by any path — so it always reopens fresh, never mid-flow with stale data.
  $('#forgotPasswordModal').on('hidden.bs.modal', function () {
    forgotEmail = '';
    forgotCode = '';
    $('#forgotStep1Form, #forgotStep2Form, #forgotStep3Form').each(function () {
      AuthValidation.clearForm($(this));
      this.reset();
    });
    $('#forgotError').addClass('d-none').text('');
    $('#forgotResendStatus').text('Didn\'t receive a code?');
    $('#forgotNewPassword, #forgotConfirmPassword').attr('type', 'password');
    $('.btn-toggle-password[data-target="#forgotNewPassword"] i, .btn-toggle-password[data-target="#forgotConfirmPassword"] i')
      .removeClass('fa-eye-slash').addClass('fa-eye');
    showForgotStep(1);
  });


  // Logout button
  $(document).on('click', '#logoutBtn', async function (e) {
    e.preventDefault();
    // Confirm before ending the session — session-ending actions get a confirmation per this project's convention.
    const confirmed = await window.PMSConfirm({
      title: 'Log out?',
      body: "You'll need to sign in again to view your bookings.",
      confirmLabel: 'Log Out',
      variant: 'warning'
    });
    if (!confirmed) return;
    await logoutUser();
  });


  // Navbar dynamic display
  async function renderNavbarAuth() {
    const $area = $('#navbarAuthArea');
    const $messagesNavItem = $('#messagesNavItem');
    $area.empty();


    const me = await getMe();
    if (me.logged_in) {
      $messagesNavItem.removeClass('d-none');
      $area.append(`
        <div class="dropdown">
          <button class="btn btn-outline-primary rounded-pill dropdown-toggle d-inline-flex align-items-center gap-2" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="navbarAvatar">${getAvatarHtml(me.user.name, me.user.profile_picture_path, 28, 12)}</span>
            <span id="navbarWelcomeText">Welcome, ${me.user.name.split(' ')[0]}!</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="transactions.php#profile">My Profile</a></li>
            <li><a class="dropdown-item text-danger" href="#" id="logoutBtn">Logout</a></li>
          </ul>
        </div>
      `);
    } else {
      $messagesNavItem.addClass('d-none');
      $area.append(`
        <button class="btn btn-outline-primary rounded-pill btn-login me-2">Log In</button>
        <button class="btn btn-primary rounded-pill btn-signup">Sign Up</button>
      `);
    }
  }


  renderNavbarAuth();


  // Profile section — AuthValidation's second consumer.

  // MySQL datetime strings aren't reliably parsed by `new Date` in all browsers — swap in a "T" first.
  function formatMemberSince(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
  }

  // Inline adaptation of showAuthError for non-modal use.
  function showInlineFeedback($alert, type, message) {
    $alert.removeClass('alert-success alert-danger d-none').addClass('alert-' + type).text(message);
    $alert.addClass('animate__animated animate__shakeX');
    setTimeout(function () {
      $alert.removeClass('animate__animated animate__shakeX');
    }, 500);
    $alert.trigger('focus');
  }

  async function populateProfileSection() {
    if (!$('#profile').length) return;
    const me = await getMe();
    if (!me.logged_in) return;

    $('#profileNameDisplay').text(me.user.name);
    $('#profileEmailDisplay').text(me.user.email);
    $('#profileRoleDisplay').text(me.user.role);
    $('#profileMemberSinceDisplay').text(formatMemberSince(me.user.created_at));
    $('#profileNameInput').val(me.user.name);
    $('#profileEmailInput').val(me.user.email);
    $('#profilePictureDisplay').html(getAvatarHtml(me.user.name, me.user.profile_picture_path, 96, 36));
  }

  populateProfileSection();

  // Live preview before upload — swaps the display circle for the locally chosen file via FileReader.
  $('#profilePictureInput').on('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      $('#profilePictureDisplay').html(
        `<img src="${e.target.result}" alt="" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;">`
      );
    };
    reader.readAsDataURL(file);
  });

  $('#profilePictureForm').on('submit', async function (e) {
    e.preventDefault();
    const $alert = $('#profilePictureAlert');
    $alert.addClass('d-none');

    const fileInput = document.getElementById('profilePictureInput');
    const file = fileInput.files && fileInput.files[0];
    if (!file) {
      showInlineFeedback($alert, 'danger', 'Please choose an image to upload.');
      return;
    }

    const $btn = $('#profilePictureSaveBtn');
    PMSMotion.setButtonLoading($btn, true);
    try {
      const formData = new FormData();
      formData.append('profile_picture', file);
      const res = await fetch('update_profile_picture.php', {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();

      if (data.success) {
        showInlineFeedback($alert, 'success', 'Profile picture updated successfully.');
        const me = await getMe();
        $('#profilePictureDisplay').html(getAvatarHtml(me.user.name, me.user.profile_picture_path, 96, 36));
        $('#navbarAvatar').html(getAvatarHtml(me.user.name, me.user.profile_picture_path, 28, 12));
        fileInput.value = '';
      } else {
        showInlineFeedback($alert, 'danger', data.error || 'Failed to upload profile picture.');
        // Revert the live preview back to the actual saved picture/initials.
        populateProfileSection();
      }
    } catch (err) {
      console.error('Error:', err);
      showInlineFeedback($alert, 'danger', 'Failed to upload profile picture.');
      populateProfileSection();
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });

  AuthValidation.attachRealTime($('#profileNameInput'), [
    AuthValidation.rules.required('Please enter your name.'),
  ]);
  AuthValidation.attachRealTime($('#profileEmailInput'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);

  $('#profileInfoForm').on('submit', async function (e) {
    e.preventDefault();
    const $alert = $('#profileInfoAlert');
    $alert.addClass('d-none');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#profileNameInput'),
        rules: [AuthValidation.rules.required('Please enter your name.')],
      },
      {
        $input: $('#profileEmailInput'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
    ]);
    if (!isValid) return;

    // Confirm only when the email actually changed.
    const newEmail = $('#profileEmailInput').val().trim();
    const currentEmail = $('#profileEmailDisplay').text().trim();
    if (newEmail !== currentEmail) {
      const confirmed = await window.PMSConfirm({
        title: 'Change email?',
        body: `Change your email to ${newEmail}? This is the address you'll log in with and the address password resets are sent to.`,
        confirmLabel: 'Change Email',
        variant: 'primary'
      });
      if (!confirmed) return;
    }

    const $btn = $('#profileInfoSaveBtn');
    PMSMotion.setButtonLoading($btn, true);
    try {
      const name = $('#profileNameInput').val().trim();
      const email = $('#profileEmailInput').val().trim();
      const res = await fetch('update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email }),
      });
      const data = await res.json();

      if (data.success) {
        showInlineFeedback($alert, 'success', 'Profile updated successfully.');
        $('#profileNameDisplay').text(name);
        $('#profileEmailDisplay').text(email);
        $('#navbarWelcomeText').text('Welcome, ' + name.split(' ')[0] + '!');
      } else {
        showInlineFeedback($alert, 'danger', data.error || 'Failed to update profile.');
      }
    } catch (err) {
      console.error('Error:', err);
      showInlineFeedback($alert, 'danger', 'Failed to update profile.');
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });

  AuthValidation.attachRealTime($('#currentPasswordInput'), [
    AuthValidation.rules.required('Please enter your current password.'),
  ]);
  AuthValidation.attachRealTime($('#newPasswordInput'), [
    AuthValidation.rules.required('Please enter a new password.'),
    AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
  ]);
  const checkConfirmNewPassword = AuthValidation.attachRealTime($('#confirmNewPasswordInput'), [
    AuthValidation.rules.required('Please confirm your new password.'),
    AuthValidation.rules.matches(() => $('#newPasswordInput').val(), 'Passwords do not match.'),
  ]);
  // Re-check the confirm field as the new-password field changes.
  $('#newPasswordInput').on('input', function () {
    if ($('#confirmNewPasswordInput').val()) checkConfirmNewPassword();
  });

  $('#changePasswordForm').on('submit', async function (e) {
    e.preventDefault();
    const $alert = $('#changePasswordAlert');
    $alert.addClass('d-none');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#currentPasswordInput'),
        rules: [AuthValidation.rules.required('Please enter your current password.')],
      },
      {
        $input: $('#newPasswordInput'),
        rules: [
          AuthValidation.rules.required('Please enter a new password.'),
          AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
        ],
      },
      {
        $input: $('#confirmNewPasswordInput'),
        rules: [
          AuthValidation.rules.required('Please confirm your new password.'),
          AuthValidation.rules.matches(() => $('#newPasswordInput').val(), 'Passwords do not match.'),
        ],
      },
    ]);
    if (!isValid) return;

    // Confirm a credential change before submitting it.
    const confirmed = await window.PMSConfirm({
      title: 'Change password?',
      body: "You'll use the new password the next time you log in.",
      confirmLabel: 'Change Password',
      variant: 'primary'
    });
    if (!confirmed) return;

    const $btn = $('#changePasswordSaveBtn');
    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await fetch('change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          current_password: $('#currentPasswordInput').val(),
          new_password: $('#newPasswordInput').val(),
        }),
      });
      const data = await res.json();

      // 's error shape is {error: "..."}, unlike update_profile.php's {success:false, error:"..."}.
      if (data.success) {
        showInlineFeedback($alert, 'success', data.message || 'Password updated successfully.');
        const $form = $('#changePasswordForm');
        $form[0].reset();
        AuthValidation.clearForm($form);
      } else {
        showInlineFeedback($alert, 'danger', data.error || 'Failed to update password.');
      }
    } catch (err) {
      console.error('Error:', err);
      showInlineFeedback($alert, 'danger', 'Failed to update password.');
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Modals (open login/signup)
  $(document).on('click', '.btn-login', function () {
    $('#loginModal').modal('show');
  });
  $(document).on('click', '.btn-signup', function () {
    $('#signupModal').modal('show');
  });
});



