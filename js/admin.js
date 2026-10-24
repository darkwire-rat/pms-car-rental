(function () {
  'use strict';

  // AdminValidation — mirrors js/app.js's AuthValidation shape and behavior.
  var AdminValidation = (function () {
    function feedbackFor($input) {
      return $('#' + $input.attr('id') + 'Feedback');
    }

    function setInvalid($input, message) {
      $input.addClass('is-invalid').attr('aria-invalid', 'true')
        .attr('aria-describedby', $input.attr('id') + 'Feedback');
      feedbackFor($input).text(message);
    }

    function clearField($input) {
      $input.removeClass('is-invalid').removeAttr('aria-invalid').removeAttr('aria-describedby');
      feedbackFor($input).text('');
    }

    function clearForm($form) {
      $form.find('.form-control, .form-select').each(function () {
        clearField($(this));
      });
    }

    // Returns the first failing rule's message, or null if all rules pass.
    function firstError(value, rules) {
      for (var i = 0; i < rules.length; i++) {
        if (!rules[i].test(value)) return rules[i].message;
      }
      return null;
    }

    // Wires blur (initial check) then input (live correction, once touched).
    function attachRealTime($input, rules) {
      var touched = false;

      function check() {
        var error = firstError($input.val(), rules);
        if (error) {
          setInvalid($input, error);
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
      var $firstInvalid = null;
      var allValid = true;
      fieldConfigs.forEach(function (cfg) {
        var error = firstError(cfg.$input.val(), cfg.rules);
        if (error) {
          setInvalid(cfg.$input, error);
          allValid = false;
          if (!$firstInvalid) $firstInvalid = cfg.$input;
        } else {
          clearField(cfg.$input);
        }
      });
      if ($firstInvalid) $firstInvalid.trigger('focus');
      return allValid;
    }

    var rules = {
      required: function (message) {
        return { test: function (v) { return String(v == null ? '' : v).trim().length > 0; }, message: message };
      },
      email: function (message) {
        return { test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v).trim()); }, message: message };
      },
      minLength: function (min, message) {
        return { test: function (v) { return String(v).trim().length >= min; }, message: message };
      },
      numeric: function (message) {
        return { test: function (v) { return v !== '' && v != null && !isNaN(v); }, message: message };
      },
      min: function (min, message) {
        return { test: function (v) { return v !== '' && v != null && !isNaN(v) && parseFloat(v) >= min; }, message: message };
      }
    };

    return {
      attachRealTime: attachRealTime,
      validateAll: validateAll,
      clearField: clearField,
      clearForm: clearForm,
      rules: rules
    };
  })();

  // In-modal / in-page feedback.
  function showAdminError($alert, message) {
    if (!$alert || !$alert.length) return;
    $alert.find('.js-modal-alert-text').text(message);
    $alert.removeClass('d-none alert-success').addClass('alert-danger');
    $alert.trigger('focus');
  }

  function showAdminSuccess($alert, message) {
    if (!$alert || !$alert.length) return;
    $alert.find('.js-modal-alert-text').text(message);
    $alert.removeClass('d-none alert-danger').addClass('alert-success');
    $alert.trigger('focus');
  }

  // Manual close.
  $(document).on('click', '.js-modal-alert-close', function () {
    $(this).closest('.js-modal-alert').addClass('d-none');
  });

  // #adminLogoutBtn previously had no JS handler at all — a bare <a href="logout.php">.
  $(document).on('click', '#adminLogoutBtn', function (e) {
    e.preventDefault();
    var href = $(this).attr('href');
    confirmAction({
      title: 'Log out of the admin panel?',
      confirmLabel: 'Log Out',
      variant: 'warning'
    }).then(function (confirmed) {
      if (confirmed) window.location = href;
    });
  });

  // The four "Edit X" row-action modals are all opened via bootstrap.Modal.getOrCreateInstance.show from a row button's click.
  function restoreFocusOnHide($modal, $trigger) {
    $modal.one('hidden.bs.modal', function () {
      if ($trigger && $trigger.length && document.body.contains($trigger.get(0))) {
        $trigger.trigger('focus');
      }
    });
  }

  // ConfirmAction — moved to js/confirm.js as window.PMSConfirm.

  // Page-specific wiring.
  $(function () {

    // ---- Users (admin_users.php) -----------------------------------------
    if ($('#usersTable').length) {
      $('#usersTable').DataTable({
        order: [[5, 'desc']],
        columnDefs: [{ orderable: false, targets: [1, 6] }]
      });
    }

    if ($('#editUserForm').length) {
      var $editUserModal = $('#editUserModal');
      var $editUserAlert = $editUserModal.find('.js-modal-alert');

      AdminValidation.attachRealTime($('#editUserName'), [
        AdminValidation.rules.required('Please enter the user\'s name.')
      ]);
      AdminValidation.attachRealTime($('#editUserEmail'), [
        AdminValidation.rules.required('Please enter the user\'s email address.'),
        AdminValidation.rules.email('Please enter a valid email address.')
      ]);

      $(document).on('click', '.edit-user', function () {
        var userId = $(this).data('id');
        var userName = $(this).data('name');
        var userEmail = $(this).data('email');

        AdminValidation.clearForm($('#editUserForm'));
        $editUserAlert.addClass('d-none');
        $('#editUserId').val(userId);
        $('#editUserName').val(userName);
        $('#editUserEmail').val(userEmail);

        restoreFocusOnHide($editUserModal, $(this));
        bootstrap.Modal.getOrCreateInstance($editUserModal.get(0)).show();
      });

      $('#editUserForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var isValid = AdminValidation.validateAll([
          { $input: $('#editUserName'), rules: [AdminValidation.rules.required('Please enter the user\'s name.')] },
          {
            $input: $('#editUserEmail'), rules: [
              AdminValidation.rules.required('Please enter the user\'s email address.'),
              AdminValidation.rules.email('Please enter a valid email address.')
            ]
          }
        ]);
        if (!isValid) return;

        var submitBtn = $form.find('button[type="submit"]');
        PMSMotion.setButtonLoading(submitBtn, true);
        $editUserAlert.addClass('d-none');

        $.ajax({
          url: 'admin_update_user.php',
          type: 'POST',
          data: $form.serialize(),
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading(submitBtn, false);
              showAdminError($editUserAlert, response.error || 'Failed to update user.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading(submitBtn, false);
            showAdminError($editUserAlert, 'An error occurred while updating the user.');
          }
        });
      });
    }

    if ($('.delete-user').length || $('#usersTable').length) {
      var $usersPageAlert = $('#pageAlert');

      $(document).on('click', '.delete-user', function () {
        var $btn = $(this);
        var userId = $btn.data('id');

        confirmAction({
          title: 'Delete user',
          body: 'Are you sure you want to delete this user? This action cannot be undone.',
          confirmLabel: 'Delete',
          variant: 'danger'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $usersPageAlert.addClass('d-none');

          $.ajax({
            url: 'admin_delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                $btn.closest('tr').fadeOut(400, function () { $(this).remove(); });
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($usersPageAlert, response.error || 'Failed to delete user.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($usersPageAlert, 'An error occurred while deleting the user.');
            }
          });
        });
      });
    }

    // ---- Vehicles (admin_vehicles.php) ------------------------------------
    if ($('#vehiclesTable').length) {
      $('#vehiclesTable').DataTable({
        order: [[0, 'desc']],
        columnDefs: [
          { targets: [6, 7], orderable: false, searchable: false }
        ]
      });
    }

    if ($('#addVehicleForm').length) {
      AdminValidation.attachRealTime($('#addTitle'), [
        AdminValidation.rules.required('Please enter the car title.')
      ]);
      AdminValidation.attachRealTime($('#addPrice'), [
        AdminValidation.rules.required('Please enter a price per day.'),
        AdminValidation.rules.min(0, 'Price must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#addUnits'), [
        AdminValidation.rules.required('Please enter the number of available units.'),
        AdminValidation.rules.min(0, 'Units must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#addSeats'), [
        AdminValidation.rules.required('Please enter the number of seats.'),
        AdminValidation.rules.min(1, 'Seats must be at least 1.')
      ]);

      $('#addVehicleForm').on('submit', function (e) {
        var isValid = AdminValidation.validateAll([
          { $input: $('#addTitle'), rules: [AdminValidation.rules.required('Please enter the car title.')] },
          {
            $input: $('#addPrice'), rules: [
              AdminValidation.rules.required('Please enter a price per day.'),
              AdminValidation.rules.min(0, 'Price must be zero or greater.')
            ]
          },
          {
            $input: $('#addUnits'), rules: [
              AdminValidation.rules.required('Please enter the number of available units.'),
              AdminValidation.rules.min(0, 'Units must be zero or greater.')
            ]
          },
          {
            $input: $('#addSeats'), rules: [
              AdminValidation.rules.required('Please enter the number of seats.'),
              AdminValidation.rules.min(1, 'Seats must be at least 1.')
            ]
          }
        ]);
        if (!isValid) {
          e.preventDefault();
          return;
        }
        PMSMotion.setButtonLoading($(this).find('button[type="submit"]'), true);
      });
    }

    if ($('#editVehicleForm').length) {
      AdminValidation.attachRealTime($('#editTitle'), [
        AdminValidation.rules.required('Please enter the car title.')
      ]);
      AdminValidation.attachRealTime($('#editPrice'), [
        AdminValidation.rules.required('Please enter a price per day.'),
        AdminValidation.rules.min(0, 'Price must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#editUnits'), [
        AdminValidation.rules.required('Please enter the number of available units.'),
        AdminValidation.rules.min(0, 'Units must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#editSeats'), [
        AdminValidation.rules.required('Please enter the number of seats.'),
        AdminValidation.rules.min(1, 'Seats must be at least 1.')
      ]);

      $(document).on('click', '.editVehicleBtn', function () {
        AdminValidation.clearForm($('#editVehicleForm'));
        $('#editVehicleModal').find('.js-modal-alert').addClass('d-none');

        $('#editVehicleId').val($(this).data('id'));
        $('#editTitle').val($(this).data('title'));
        $('#editCategory').val($(this).data('category'));
        $('#editPrice').val($(this).data('price'));
        $('#editUnits').val($(this).data('units'));
        $('#editSeats').val($(this).data('seats'));
        $('#editFuel').val($(this).data('fuel'));
        $('#editTransmission').val($(this).data('transmission'));
        $('#editActive').prop('checked', $(this).data('active') == 1);

        restoreFocusOnHide($('#editVehicleModal'), $(this));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editVehicleModal')).show();
      });

      $('#editVehicleForm').on('submit', function (e) {
        var isValid = AdminValidation.validateAll([
          { $input: $('#editTitle'), rules: [AdminValidation.rules.required('Please enter the car title.')] },
          {
            $input: $('#editPrice'), rules: [
              AdminValidation.rules.required('Please enter a price per day.'),
              AdminValidation.rules.min(0, 'Price must be zero or greater.')
            ]
          },
          {
            $input: $('#editUnits'), rules: [
              AdminValidation.rules.required('Please enter the number of available units.'),
              AdminValidation.rules.min(0, 'Units must be zero or greater.')
            ]
          },
          {
            $input: $('#editSeats'), rules: [
              AdminValidation.rules.required('Please enter the number of seats.'),
              AdminValidation.rules.min(1, 'Seats must be at least 1.')
            ]
          }
        ]);
        if (!isValid) {
          e.preventDefault();
          return;
        }
        PMSMotion.setButtonLoading($(this).find('button[type="submit"]'), true);
      });
    }

    $(document).on('click', '.delete-vehicle', function (e) {
      e.preventDefault();
      var href = $(this).attr('href');
      var title = $(this).data('title');

      confirmAction({
        title: 'Delete vehicle',
        body: 'Delete ' + title + '? This will also permanently delete every booking made for this vehicle and every transaction on those bookings.',
        confirmLabel: 'Delete',
        variant: 'danger'
      }).then(function (confirmed) {
        if (confirmed) window.location.href = href;
      });
    });

    // ---- Vouchers (admin_vouchers.php) ------------------------------------
    if ($('#addVoucherForm').length) {
      var $addVoucherAlert = $('#addVoucherModal').find('.js-modal-alert');
      var $editVoucherAlert = $('#editVoucherModal').find('.js-modal-alert');
      var $vouchersPageAlert = $('#pageAlert');

      var voucherFieldRules = function (prefix) {
        return [
          { $input: $('#' + prefix + 'code'), rules: [AdminValidation.rules.required('Please enter a voucher code.')] },
          {
            $input: $('#' + prefix + 'discount_pct'), rules: [
              AdminValidation.rules.required('Please enter a discount percentage.'),
              AdminValidation.rules.min(0, 'Discount percentage must be zero or greater.')
            ]
          },
          {
            $input: $('#' + prefix + 'discount_amount'), rules: [
              AdminValidation.rules.required('Please enter a discount amount.'),
              AdminValidation.rules.min(0, 'Discount amount must be zero or greater.')
            ]
          },
          {
            $input: $('#' + prefix + 'usage_limit'), rules: [
              AdminValidation.rules.required('Please enter a usage limit.'),
              AdminValidation.rules.min(1, 'Usage limit must be at least 1.')
            ]
          }
        ];
      };

      voucherFieldRules('').forEach(function (cfg) { AdminValidation.attachRealTime(cfg.$input, cfg.rules); });
      voucherFieldRules('edit_').forEach(function (cfg) { AdminValidation.attachRealTime(cfg.$input, cfg.rules); });

      $('#addVoucherForm').on('submit', function (e) {
        e.preventDefault();
        if (!AdminValidation.validateAll(voucherFieldRules(''))) return;

        var formData = new FormData($('#addVoucherForm').get(0));
        formData.append('action', 'create');
        var $btn = $('#saveVoucher');
        PMSMotion.setButtonLoading($btn, true);
        $addVoucherAlert.addClass('d-none');

        $.ajax({
          url: 'admin_vouchers.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function (result) {
            if (result.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($addVoucherAlert, result.message || 'Error creating voucher.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading($btn, false);
            showAdminError($addVoucherAlert, 'An error occurred while creating the voucher.');
          }
        });
      });

      $(document).on('click', '.edit-voucher', function () {
        var $button = $(this);
        AdminValidation.clearForm($('#editVoucherForm'));
        $editVoucherAlert.addClass('d-none');

        $('#edit_id').val($button.data('id'));
        $('#edit_code').val($button.data('code'));
        $('#edit_discount_pct').val($button.data('discount-pct'));
        $('#edit_discount_amount').val($button.data('discount-amount'));
        $('#edit_usage_limit').val($button.data('usage-limit'));
        restoreFocusOnHide($('#editVoucherModal'), $button);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editVoucherModal')).show();
      });

      $('#editVoucherForm').on('submit', function (e) {
        e.preventDefault();
        if (!AdminValidation.validateAll(voucherFieldRules('edit_'))) return;

        var formData = new FormData($('#editVoucherForm').get(0));
        formData.append('action', 'update');
        var $btn = $('#updateVoucher');
        PMSMotion.setButtonLoading($btn, true);
        $editVoucherAlert.addClass('d-none');

        $.ajax({
          url: 'admin_vouchers.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function (result) {
            if (result.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($editVoucherAlert, result.message || 'Error updating voucher.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading($btn, false);
            showAdminError($editVoucherAlert, 'An error occurred while updating the voucher.');
          }
        });
      });

      $(document).on('click', '.delete-voucher', function () {
        var $btn = $(this);
        var id = $btn.data('id');

        confirmAction({
          title: 'Delete voucher',
          body: 'Are you sure you want to delete this voucher?',
          confirmLabel: 'Delete',
          variant: 'danger'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $vouchersPageAlert.addClass('d-none');

          $.ajax({
            url: 'admin_vouchers.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function (result) {
              if (result.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($vouchersPageAlert, result.message || 'Error deleting voucher.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($vouchersPageAlert, 'An error occurred while deleting the voucher.');
            }
          });
        });
      });
    }

    // ---- Transactions (view-all-data.php) ---------------------------------
    if ($('#transactionsTable').length) {
      $('#transactionsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
          search: 'Search bookings:',
          emptyTable: 'No transactions found',
          zeroRecords: 'No matching bookings found'
        },
        columnDefs: [
          { targets: -1, orderable: false, searchable: false }
        ]
      });
      $('#transactionsTable_filter input').attr('aria-label', 'Search bookings');
    }

    if ($('#editTransactionForm').length) {
      var $editTransactionAlert = $('#editTransactionModal').find('.js-modal-alert');

      AdminValidation.attachRealTime($('#editPickupTime'), [
        AdminValidation.rules.required('Please enter a pickup time.')
      ]);
      AdminValidation.attachRealTime($('#editDropoffTime'), [
        AdminValidation.rules.required('Please enter a dropoff time.')
      ]);

      $(document).on('click', '.edit-transaction', function () {
        var id = $(this).data('id');
        var pickup = $(this).data('pickup');
        var dropoff = $(this).data('dropoff');

        AdminValidation.clearForm($('#editTransactionForm'));
        $editTransactionAlert.addClass('d-none');

        $('#editTransactionId').val(id);
        $('#editPickupTime').val(String(pickup).replace(' ', 'T').substr(0, 5));
        $('#editDropoffTime').val(String(dropoff).replace(' ', 'T').substr(0, 5));

        restoreFocusOnHide($('#editTransactionModal'), $(this));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editTransactionModal')).show();
      });

      $('#editTransactionForm').on('submit', function (e) {
        e.preventDefault();
        var isValid = AdminValidation.validateAll([
          { $input: $('#editPickupTime'), rules: [AdminValidation.rules.required('Please enter a pickup time.')] },
          { $input: $('#editDropoffTime'), rules: [AdminValidation.rules.required('Please enter a dropoff time.')] }
        ]);
        if (!isValid) return;

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        // Confirm before mutating a customer's live booking.
        confirmAction({
          title: 'Update booking times?',
          body: "Update this booking's pickup and drop-off times? The customer is not notified of this change.",
          confirmLabel: 'Update',
          variant: 'warning'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $editTransactionAlert.addClass('d-none');

          $.ajax({
            url: 'update_booking_time.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($editTransactionAlert, response.error || 'Failed to update booking.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($editTransactionAlert, 'An error occurred while updating the booking.');
            }
          });
        });
      });
    }

    if ($('.delete-transaction').length || $('#transactionsTable').length) {
      var $transactionsPageAlert = $('#pageAlert');

      $(document).on('click', '.delete-transaction', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        var row = $btn.closest('tr');

        confirmAction({
          title: 'Delete transaction',
          body: 'Are you sure you want to delete this transaction? This action cannot be undone and will also delete its associated payment record.',
          confirmLabel: 'Delete',
          variant: 'danger'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $transactionsPageAlert.addClass('d-none');

          $.ajax({
            url: 'delete_booking.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                row.fadeOut(400, function () { $(this).remove(); });
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($transactionsPageAlert, response.error || 'Failed to delete transaction.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($transactionsPageAlert, 'An error occurred while deleting the transaction.');
            }
          });
        });
      });
    }

    // Transaction detail approval no longer happens from a list-row Confirm button.
    if ($('#transactionDetail').length) {
      var $detail = $('#transactionDetail');
      var detailId = $detail.data('id');
      var $detailAlert = $('#pageAlert');

      function detailAction(url, data, $btn, failMsg) {
        PMSMotion.setButtonLoading($btn, true);
        $detailAlert.addClass('d-none');
        $.ajax({
          url: url, type: 'POST', data: data, dataType: 'json',
          success: function (response) {
            if (response && response.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($detailAlert, (response && response.error) || failMsg);
            }
          },
          error: function (xhr) {
            PMSMotion.setButtonLoading($btn, false);
            var msg = failMsg;
            try { msg = JSON.parse(xhr.responseText).error || failMsg; } catch (e) {}
            showAdminError($detailAlert, msg);
          }
        });
      }

      $('#btnApproveBooking').on('click', function () {
        var $btn = $(this);
        confirmAction({
          title: 'Approve this booking?',
          body: 'Approve booking #' + detailId + ' for ' + $detail.data('customer') + ' — '
            + $detail.data('vehicle') + ', ' + $detail.data('pickup') + ' to ' + $detail.data('dropoff')
            + ', ₱' + $detail.data('amount') + '. Availability is re-checked on approval.',
          confirmLabel: 'Approve',
          variant: 'success'
        }).then(function (confirmed) {
          if (confirmed) detailAction('admin_confirm_booking.php', { id: detailId }, $btn, 'Failed to approve booking.');
        });
      });

      // Phase 9: record the rental + deposit payment (manual provider).
      $('#btnRecordPayment').on('click', function () {
        var $btn = $(this);
        var total = Number($btn.data('total'));
        var deposit = Number($btn.data('deposit'));
        confirmAction({
          title: 'Record payment?',
          body: 'Record ₱' + total.toLocaleString() + ' rental'
            + (deposit > 0 ? ' + ₱' + deposit.toLocaleString() + ' refundable deposit' : '')
            + ' = ₱' + (total + deposit).toLocaleString() + ' as received for booking #' + detailId
            + '. Simulated payment — no real charge.',
          confirmLabel: 'Record Payment',
          variant: 'success'
        }).then(function (confirmed) {
          if (confirmed) detailAction('payment_process.php', { booking_id: detailId }, $btn, 'Failed to record payment.');
        });
      });

      if ($('#declineForm').length) {
        AdminValidation.attachRealTime($('#declineReason'), [
          AdminValidation.rules.required('Enter a reason for declining.')
        ]);
        $('#declineForm').on('submit', function (e) {
          e.preventDefault();
          if (!AdminValidation.validateAll([
            { $input: $('#declineReason'), rules: [AdminValidation.rules.required('Enter a reason for declining.')] }
          ])) return;
          detailAction('admin_decline_booking.php',
            { id: detailId, reason: $('#declineReason').val() },
            $(this).find('button[type="submit"]'), 'Failed to decline booking.');
        });
      }

      if ($('#requestInfoForm').length) {
        AdminValidation.attachRealTime($('#requestInfoMessage'), [
          AdminValidation.rules.required('Describe what the customer needs to provide.')
        ]);
        $('#requestInfoForm').on('submit', function (e) {
          e.preventDefault();
          if (!AdminValidation.validateAll([
            { $input: $('#requestInfoMessage'), rules: [AdminValidation.rules.required('Describe what the customer needs to provide.')] }
          ])) return;
          detailAction('admin_request_info.php',
            { id: detailId, message: $('#requestInfoMessage').val() },
            $(this).find('button[type="submit"]'), 'Failed to send the request.');
        });
      }

      // Phase 10: pickup, return & inspection.
      if ($('#markPickupForm').length) {
        AdminValidation.attachRealTime($('#fuelLevelOut'), [
          AdminValidation.rules.required('Select the fuel level given to the customer.')
        ]);
        $('#markPickupForm').on('submit', function (e) {
          e.preventDefault();
          if (!AdminValidation.validateAll([
            { $input: $('#fuelLevelOut'), rules: [AdminValidation.rules.required('Select the fuel level given to the customer.')] }
          ])) return;
          detailAction('admin_mark_pickup.php',
            { id: detailId, fuel_level_out: $('#fuelLevelOut').val() },
            $(this).find('button[type="submit"]'), 'Failed to mark pickup.');
        });
      }

      $('#btnMarkReturn').on('click', function () {
        var $btn = $(this);
        confirmAction({
          title: 'Mark this vehicle returned?',
          body: 'Records booking #' + detailId + ' as returned right now and opens its inspection. ' +
            'You can correct the exact return time from the inspection form afterwards.',
          confirmLabel: 'Mark Returned',
          variant: 'success'
        }).then(function (confirmed) {
          if (confirmed) detailAction('admin_mark_return.php', { id: detailId }, $btn, 'Failed to mark return.');
        });
      });

      if ($('#inspectionForm').length) {
        $('#hasDamage').on('change', function () {
          $('#damageNotesWrap').toggle(this.checked);
        });
        $('#inspectionForm').on('submit', function (e) {
          e.preventDefault();
          var $btn = $(this).find('button[type="submit"]');
          var actualReturnAt = $('#actualReturnAt').val(); // "YYYY-MM-DDTHH:mm" or ''
          detailAction('admin_save_inspection.php', {
            booking_id: $(this).data('booking-id'),
            fuel_level_in: $('#fuelLevelIn').val(),
            condition_notes: $('#conditionNotes').val(),
            has_damage: $('#hasDamage').is(':checked') ? 1 : 0,
            damage_notes: $('#damageNotes').val(),
            actual_return_at: actualReturnAt ? actualReturnAt.replace('T', ' ') + ':00' : ''
          }, $btn, 'Failed to save the inspection.');
        });
      }

      if ($('#reportDamageForm').length) {
        AdminValidation.attachRealTime($('#damageDescription'), [
          AdminValidation.rules.required('Describe the damage.')
        ]);
        AdminValidation.attachRealTime($('#damageEstimatedAmount'), [
          AdminValidation.rules.required('Enter an estimated cost.')
        ]);
        $('#reportDamageForm').on('submit', function (e) {
          e.preventDefault();
          if (!AdminValidation.validateAll([
            { $input: $('#damageDescription'), rules: [AdminValidation.rules.required('Describe the damage.')] },
            { $input: $('#damageEstimatedAmount'), rules: [AdminValidation.rules.required('Enter an estimated cost.')] }
          ])) return;

          var $form = $(this);
          var $btn = $form.find('button[type="submit"]');
          var formData = new FormData();
          formData.append('booking_id', $form.data('booking-id'));
          formData.append('description', $('#damageDescription').val());
          formData.append('estimated_amount', $('#damageEstimatedAmount').val());
          var files = document.getElementById('damagePhotos').files;
          for (var i = 0; i < files.length; i++) {
            formData.append('photos[]', files[i]);
          }

          PMSMotion.setButtonLoading($btn, true);
          $.ajax({
            url: 'admin_report_damage.php', type: 'POST', data: formData,
            processData: false, contentType: false, dataType: 'json',
            success: function (response) {
              if (response && response.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($detailAlert, (response && response.error) || 'Failed to report the damage.');
              }
            },
            error: function (xhr) {
              PMSMotion.setButtonLoading($btn, false);
              var msg = 'Failed to report the damage.';
              try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
              showAdminError($detailAlert, msg);
            }
          });
        });
      }

      if ($('#settleForm').length) {
        $(document).on('change', '.settle-damage-waive', function () {
          $(this).closest('.settle-damage-row').find('.settle-damage-amount').prop('disabled', this.checked);
        });

        $('#settleForm').on('submit', function (e) {
          e.preventDefault();
          var $form = $(this);
          var $btn = $form.find('button[type="submit"]');

          var overtime = parseFloat($('#settleOvertime').val()) || 0;
          var fuel = parseFloat($('#settleFuel').val()) || 0;
          var damageAmount = {};
          var damageReason = {};
          var damageWaived = {};
          var damageTotal = 0;

          $form.find('.settle-damage-row').each(function () {
            var id = $(this).data('id');
            var waived = $(this).find('.settle-damage-waive').is(':checked');
            var reason = $(this).find('.settle-damage-reason').val();
            damageReason[id] = reason;
            if (waived) {
              damageWaived[id] = 1;
            } else {
              var amount = parseFloat($(this).find('.settle-damage-amount').val()) || 0;
              damageAmount[id] = amount;
              damageTotal += amount;
            }
          });

          var total = overtime + fuel + damageTotal;

          confirmAction({
            title: 'Finalize settlement for booking #' + $form.data('booking-id') + '?',
            body: 'Total deductions: ₱' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
              + '. This settles the deposit, records the outcome, and marks the booking completed. It cannot be undone.',
            confirmLabel: 'Finalize Settlement',
            variant: 'danger'
          }).then(function (confirmed) {
            if (!confirmed) return;
            detailAction('admin_settle_deposit.php', {
              booking_id: $form.data('booking-id'),
              overtime_amount: overtime,
              overtime_reason: $('#settleOvertimeReason').val(),
              fuel_amount: fuel,
              fuel_reason: $('#settleFuelReason').val(),
              damage_amount: damageAmount,
              damage_reason: damageReason,
              damage_waived: damageWaived
            }, $btn, 'Failed to finalize settlement.');
          });
        });
      }
    }

    // ---- Dashboard (admin-dashboard.php) -----------------------------------
    if ($('#dashboardMetricSet').length) {
      $('#dashboardMetricSet').on('change', function () {
        var $select = $(this);
        var $cards = $('#dashboardMetricCards');
        var $status = $('#dashboardMetricStatus');
        $select.prop('disabled', true);
        $.ajax({
          url: 'admin_dashboard_data.php',
          type: 'GET',
          data: { metric_set: $select.val() },
          dataType: 'json'
        }).done(function (payload) {
          if (!payload || !payload.success || !payload.metrics) {
            showAdminError($('#dashboardAlert'), 'Failed to refresh dashboard statistics.');
            return;
          }
          $cards.find('.js-dashboard-metric-value').each(function (index) {
            var metric = payload.metrics[index];
            if (!metric) return;
            var value = Number(metric.value);
            var formatted = Number.isInteger(value) ? value.toLocaleString() : value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            $(this).text((metric.prefix || '') + formatted + (metric.suffix || ''));
          });
          $cards.find('.js-dashboard-metric-label').each(function (index) {
            if (payload.metrics[index]) $(this).text(payload.metrics[index].label);
          });
          $status.text('Updated just now');
        }).fail(function () {
          showAdminError($('#dashboardAlert'), 'Failed to refresh dashboard statistics.');
        }).always(function () {
          $select.prop('disabled', false);
        });
      });
    }

    if ($('#messagesTable').length) {
      $('#messagesTable').DataTable({ order: [[0, 'desc']] });
    }

    // The three Business Overview charts. window.PMS_DASHBOARD_CHARTS is a small inline <script> block admin-dashboard.php writes right.
    if (window.PMS_DASHBOARD_CHARTS && window.Chart) {
      (function () {
        var data = window.PMS_DASHBOARD_CHARTS;
        var chartsByKey = { revenue: null, status: null, top: null };

        function allCharts() {
          return Object.keys(chartsByKey).map(function (k) { return chartsByKey[k]; }).filter(Boolean);
        }

        function themeColors() {
          var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
          return {
            text: dark ? '#e9ecef' : '#212529',
            grid: dark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.1)'
          };
        }

        // Theme toggle doesn't dispatch an event.
        function applyThemeColors() {
          var colors = themeColors();
          Chart.defaults.color = colors.text;
          Chart.defaults.borderColor = colors.grid;
          allCharts().forEach(function (chart) {
            ['x', 'y'].forEach(function (axis) {
              if (chart.options.scales && chart.options.scales[axis]) {
                chart.options.scales[axis].ticks.color = colors.text;
                chart.options.scales[axis].grid.color = colors.grid;
              }
            });
            if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
              chart.options.plugins.legend.labels.color = colors.text;
            }
            chart.update();
          });
        }

        var initColors = themeColors();
        Chart.defaults.color = initColors.text;
        Chart.defaults.borderColor = initColors.grid;

        var revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
          chartsByKey.revenue = new Chart(revenueCtx, {
            type: 'bar',
            data: {
              labels: data.revenue,
              datasets: [{ label: 'Revenue (₱)', data: data.revenueData, backgroundColor: '#0d6efd' }]
            },
            options: {
              responsive: true,
              plugins: { legend: { display: false } },
              scales: {
                x: { ticks: { color: initColors.text }, grid: { color: initColors.grid } },
                y: {
                  beginAtZero: true,
                  ticks: { color: initColors.text, callback: function (v) { return '₱' + Number(v).toLocaleString(); } },
                  grid: { color: initColors.grid }
                }
              }
            }
          });
        }

        var statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
          chartsByKey.status = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
              labels: data.statusLabels,
              datasets: [{ data: data.statusData, backgroundColor: data.statusColors }]
            },
            options: {
              responsive: true,
              plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, color: initColors.text } } }
            }
          });
        }

        var topCtx = document.getElementById('topVehiclesChart');
        if (topCtx) {
          chartsByKey.top = new Chart(topCtx, {
            type: 'bar',
            data: {
              labels: data.topVehicleLabels,
              datasets: [{ label: 'Bookings', data: data.topVehicleData, backgroundColor: '#20c997' }]
            },
            options: {
              indexAxis: 'y',
              responsive: true,
              plugins: { legend: { display: false } },
              scales: {
                x: { beginAtZero: true, ticks: { color: initColors.text, precision: 0 }, grid: { color: initColors.grid } },
                y: { ticks: { color: initColors.text }, grid: { color: initColors.grid } }
              }
            }
          });
        }

        new MutationObserver(applyThemeColors).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

        // Filter form: period / category / status.
        function setTableBody(tbodyEl, rows) {
          while (tbodyEl.firstChild) tbodyEl.removeChild(tbodyEl.firstChild);
          rows.forEach(function (cells) {
            var tr = document.createElement('tr');
            cells.forEach(function (text) {
              var td = document.createElement('td');
              td.textContent = text; // textContent, never innerHTML -- vehicle titles are admin-editable text
              tr.appendChild(td);
            });
            tbodyEl.appendChild(tr);
          });
        }

        function applyDashboardData(payload) {
          if (chartsByKey.revenue) {
            chartsByKey.revenue.data.labels = payload.revenue.labels;
            chartsByKey.revenue.data.datasets[0].data = payload.revenue.data;
            chartsByKey.revenue.update();
          }
          document.getElementById('revenueEmptyState').hidden = payload.revenue.total != 0;
          document.getElementById('revenueCaption').textContent = 'Revenue by month (trailing 12 months, ending ' + payload.window_label + ')';
          setTableBody(document.getElementById('revenueTableBody'), payload.revenue.labels.map(function (label, i) {
            return [label, '₱' + Number(payload.revenue.data[i]).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })];
          }));

          if (chartsByKey.status) {
            chartsByKey.status.data.labels = payload.status.labels;
            chartsByKey.status.data.datasets[0].data = payload.status.data;
            chartsByKey.status.data.datasets[0].backgroundColor = payload.status.colors;
            chartsByKey.status.update();
          }
          document.getElementById('statusEmptyState').hidden = payload.status.total !== 0;
          document.getElementById('statusEmptyMonth').textContent = payload.month_label;
          document.getElementById('statusCaption').textContent = 'Bookings by status (' + payload.month_label + ')';
          setTableBody(document.getElementById('statusTableBody'), payload.status.labels.map(function (label, i) {
            return [label, String(payload.status.data[i])];
          }));

          if (chartsByKey.top) {
            chartsByKey.top.data.labels = payload.top_vehicles.labels;
            chartsByKey.top.data.datasets[0].data = payload.top_vehicles.data;
            chartsByKey.top.update();
          }
          var topEmpty = payload.top_vehicles.labels.length === 0;
          document.getElementById('topVehiclesEmptyState').hidden = !topEmpty;
          document.getElementById('topVehiclesEmptyMonth').textContent = payload.month_label;
          document.getElementById('topVehiclesCaption').textContent = 'Top vehicles by bookings (' + payload.month_label + ')';
          setTableBody(document.getElementById('topVehiclesTableBody'), payload.top_vehicles.labels.map(function (label, i) {
            return [String(i + 1), label, String(payload.top_vehicles.data[i])];
          }));
        }

        var $filterForm = $('#dashboardFilterForm');
        if ($filterForm.length) {
          $filterForm.on('submit', function (e) {
            e.preventDefault();
            var $btn = $('#btnApplyDashboardFilter');
            PMSMotion.setButtonLoading($btn, true);
            $.ajax({
              url: 'admin_dashboard_data.php',
              type: 'GET',
              data: $filterForm.serialize(),
              dataType: 'json',
              success: function (payload) {
                PMSMotion.setButtonLoading($btn, false);
                if (payload && payload.success) {
                  applyDashboardData(payload);
                } else {
                  showAdminError($('#dashboardAlert'), (payload && payload.error) || 'Failed to refresh the charts.');
                }
              },
              error: function (xhr) {
                PMSMotion.setButtonLoading($btn, false);
                var msg = 'Failed to refresh the charts.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e2) {}
                showAdminError($('#dashboardAlert'), msg);
              }
            });
          });
        }
      })();
    }

    // ---- Reviews (admin_reviews.php) ---------------------------------------
    if ($('#reviewsTable').length) {
      $('#reviewsTable').DataTable({ order: [[0, 'desc']] });

      var $reviewsPageAlert = $('#pageAlert');

      $(document).on('click', '.js-toggle-review', function () {
        var $btn = $(this);
        var reviewId = $btn.data('id');
        var action = $btn.data('action');
        confirmAction({
          title: (action === 'hide' ? 'Hide' : 'Unhide') + ' this review?',
          body: action === 'hide'
            ? 'Hiding removes it from the vehicle\'s public reviews and rating average. The review and its text are kept, not deleted.'
            : 'Unhiding makes it visible again on the vehicle\'s public reviews and rating average.',
          confirmLabel: action === 'hide' ? 'Hide Review' : 'Unhide Review',
          variant: action === 'hide' ? 'warning' : 'success'
        }).then(function (confirmed) {
          if (!confirmed) return;
          PMSMotion.setButtonLoading($btn, true);
          $reviewsPageAlert.addClass('d-none');
          $.ajax({
            url: 'admin_review_moderate.php', type: 'POST',
            data: { review_id: reviewId, action: action }, dataType: 'json',
            success: function (response) {
              if (response && response.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($reviewsPageAlert, (response && response.error) || 'Failed to update the review.');
              }
            },
            error: function (xhr) {
              PMSMotion.setButtonLoading($btn, false);
              var msg = 'Failed to update the review.';
              try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
              showAdminError($reviewsPageAlert, msg);
            }
          });
        });
      });

      $(document).on('click', '.js-respond-review', function () {
        var $btn = $(this);
        $('#respondReviewId').val($btn.data('id'));
        $('#respondReviewText').val($btn.data('response') || '');
        $('#respondReviewModal').find('.js-modal-alert').addClass('d-none');
        new bootstrap.Modal(document.getElementById('respondReviewModal')).show();
      });

      $('#btnSaveReviewResponse').on('click', function () {
        var $btn = $(this);
        var $modalAlert = $('#respondReviewModal').find('.js-modal-alert');
        var response = $('#respondReviewText').val().trim();
        if (!response) {
          showAdminError($modalAlert, 'Please enter a response.');
          return;
        }
        PMSMotion.setButtonLoading($btn, true);
        $.ajax({
          url: 'admin_review_respond.php', type: 'POST',
          data: { review_id: $('#respondReviewId').val(), response: response }, dataType: 'json',
          success: function (data) {
            if (data && data.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($modalAlert, (data && data.error) || 'Failed to save the response.');
            }
          },
          error: function (xhr) {
            PMSMotion.setButtonLoading($btn, false);
            var msg = 'Failed to save the response.';
            try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
            showAdminError($modalAlert, msg);
          }
        });
      });
    }

    // ---- Settings (admin_settings.php) -------------------------------------
    if ($('#profileForm').length) {
      var $settingsAlert = $('#settingsAlert');

      // Server-renders the current email straight into #settingsEmail's value attribute.
      var lastSavedEmail = $('#settingsEmail').val();

      AdminValidation.attachRealTime($('#settingsName'), [
        AdminValidation.rules.required('Please enter your name.')
      ]);
      AdminValidation.attachRealTime($('#settingsEmail'), [
        AdminValidation.rules.required('Please enter your email address.'),
        AdminValidation.rules.email('Please enter a valid email address.')
      ]);

      function submitProfileForm($form, $btn) {
        PMSMotion.setButtonLoading($btn, true);
        $settingsAlert.addClass('d-none');

        $.ajax({
          url: 'admin_update_profile.php',
          type: 'POST',
          data: $form.serialize(),
          dataType: 'json',
          success: function (response) {
            PMSMotion.setButtonLoading($btn, false);
            if (response.success) {
              lastSavedEmail = $('#settingsEmail').val();
              showAdminSuccess($settingsAlert, 'Profile updated successfully.');
            } else {
              showAdminError($settingsAlert, response.error || 'Failed to update profile.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading($btn, false);
            showAdminError($settingsAlert, 'An error occurred while updating your profile.');
          }
        });
      }

      $('#profileForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var isValid = AdminValidation.validateAll([
          { $input: $('#settingsName'), rules: [AdminValidation.rules.required('Please enter your name.')] },
          {
            $input: $('#settingsEmail'), rules: [
              AdminValidation.rules.required('Please enter your email address.'),
              AdminValidation.rules.email('Please enter a valid email address.')
            ]
          }
        ]);
        if (!isValid) return;

        var $btn = $form.find('button[type="submit"]');
        var newEmail = $('#settingsEmail').val().trim();

        if (newEmail !== lastSavedEmail) {
          confirmAction({
            title: 'Change your admin email?',
            body: 'Change your admin email to ' + newEmail + '? This is the address you\'ll sign in with.',
            confirmLabel: 'Change Email',
            variant: 'primary'
          }).then(function (confirmed) {
            if (confirmed) submitProfileForm($form, $btn);
          });
        } else {
          submitProfileForm($form, $btn);
        }
      });
    }

    if ($('#passwordForm').length) {
      var $passwordAlert = $('#settingsAlert');

      AdminValidation.attachRealTime($('#currentPassword'), [
        AdminValidation.rules.required('Please enter your current password.')
      ]);
      AdminValidation.attachRealTime($('#newPassword'), [
        AdminValidation.rules.required('Please enter a new password.'),
        AdminValidation.rules.minLength(6, 'New password must be at least 6 characters.')
      ]);
      AdminValidation.attachRealTime($('#confirmNewPassword'), [
        AdminValidation.rules.required('Please confirm your new password.'),
        { test: function (v) { return v === $('#newPassword').val(); }, message: 'Passwords do not match.' }
      ]);

      $('#passwordForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var isValid = AdminValidation.validateAll([
          { $input: $('#currentPassword'), rules: [AdminValidation.rules.required('Please enter your current password.')] },
          {
            $input: $('#newPassword'), rules: [
              AdminValidation.rules.required('Please enter a new password.'),
              AdminValidation.rules.minLength(6, 'New password must be at least 6 characters.')
            ]
          },
          {
            $input: $('#confirmNewPassword'), rules: [
              AdminValidation.rules.required('Please confirm your new password.'),
              { test: function (v) { return v === $('#newPassword').val(); }, message: 'Passwords do not match.' }
            ]
          }
        ]);
        if (!isValid) return;

        var $btn = $form.find('button[type="submit"]');

        // Confirm a credential change before submitting it.
        confirmAction({
          title: 'Change your admin password?',
          body: 'You\'ll use the new password the next time you sign in.',
          confirmLabel: 'Change Password',
          variant: 'primary'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $passwordAlert.addClass('d-none');

          $.ajax({
            url: 'admin_update_profile.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (response) {
              PMSMotion.setButtonLoading($btn, false);
              if (response.success) {
                showAdminSuccess($passwordAlert, response.message || 'Password updated successfully.');
                AdminValidation.clearForm($form);
                $form.trigger('reset');
              } else {
                showAdminError($passwordAlert, response.error || 'Failed to update password.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($passwordAlert, 'An error occurred while updating your password.');
            }
          });
        });
      });
    }
  });

  window.AdminValidation = AdminValidation;
  window.showAdminError = showAdminError;
  window.showAdminSuccess = showAdminSuccess;
  window.confirmAction = window.PMSConfirm;
})();
