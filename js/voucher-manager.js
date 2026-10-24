$(document).ready(function () {
  function showFloatingAlert(message, type = "danger") {
    $("#bookingAlert")
      .removeClass("d-none alert-danger alert-success alert-warning")
      .addClass("alert-" + type)
      .text(message);
  }


  const VoucherManager = {
    async loadVouchers() {
      try {
        const response = await fetch("get_vouchers.php");
        const vouchers = await response.json();


        const $select = $("#voucherSelect").empty();
        $select.append('<option value="">Select a voucher (optional)</option>');


        vouchers.forEach((voucher) => {
          const discount =
            voucher.discount_pct > 0
              ? `${voucher.discount_pct}% off`
              : `₱${voucher.discount_amount} off`;


          $select.append(
            `<option value="${voucher.code}">${voucher.code} - ${discount}</option>`
          );
        });
      } catch (err) {
        console.error("Error loading vouchers:", err);
      }
    },


    async applyVoucher(baseAmount, voucherCode) {
      try {
        // Determine a reliable amount.
        let amount = 0;
        if (baseAmount !== undefined && baseAmount !== null) {
          amount = parseFloat(baseAmount) || 0;
        }


        if (!amount || amount <= 0) {
          // 1) Try to compute from rate * days if possible
          try {
            const rentalDateStr = $("#rental_date").val();
            const returnDateStr = $("#return_date").val();
            const rateText = $("#vehicle_rate").text() || '';
            const rate = parseFloat(rateText.replace(/[₱,]/g, '')) || 0;
            if (rentalDateStr && returnDateStr && rate > 0) {
              const rentalDate = new Date(rentalDateStr);
              const returnDate = new Date(returnDateStr);
              let days = Math.ceil((returnDate - rentalDate) / (1000 * 60 * 60 * 24));
              if (isNaN(days) || days <= 0) days = 1;
              amount = days * rate;
            }
          } catch (e) {
            console.debug('voucher-manager: compute rate*days failed', e);
          }
        }


        if ((!amount || amount <= 0) && $('#bookingPreview').length) {
          try {
            const previewText = $('#bookingPreview').text() || '';
            const match = previewText.match(/Total[:\s]*₱?([\d,]+(?:\.\d+)?)/i);
            if (match && match[1]) {
              amount = parseFloat(match[1].replace(/,/g, '')) || amount;
            }
          } catch (e) {
            console.debug('voucher-manager: bookingPreview parse failed', e);
          }
        }


        if ((!amount || amount <= 0) && $('#finalAmount').length) {
          amount = parseFloat($('#finalAmount').text().replace(/[₱,]/g, '')) || amount;
        }


        if ((!amount || amount <= 0) && $('#totalAmount').length) {
          const dataVal = parseFloat($('#totalAmount').data('original')) || 0;
          const txtVal = parseFloat($('#totalAmount').text().replace(/[₱,]/g, '')) || 0;
          amount = dataVal || txtVal || amount;
        }


        // Final validation
        if (!voucherCode) {
          return { success: false, message: 'Please select a voucher.' };
        }
        if (!amount || amount <= 0) {
          return { success: false, message: 'Please preview the booking first before applying voucher.' };
        }


        const response = await fetch("apply_voucher.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            voucher_code: voucherCode,
            total_amount: amount,
          }),
        });


        const result = await response.json();
        return result;
      } catch (err) {
        console.error("Error applying voucher:", err);
        return { success: false, message: "Error applying voucher" };
      }
    },


    async updateBookingSummary(baseAmount) {
      const $baseAmount = $("#baseAmount");
      const $discountAmount = $("#discountAmount");
      const $finalAmount = $("#finalAmount");


      $baseAmount.text(`₱${baseAmount.toLocaleString()}`);


      const voucher = $("#voucherSelect").val();
      if (!voucher) {
        $discountAmount.text("-₱0.00");
        $finalAmount.text(`₱${baseAmount.toLocaleString()}`);
        return;
      }


      const result = await this.applyVoucher(baseAmount, voucher);
      if (result.success) {
        $discountAmount.text(`-₱${result.discount.toLocaleString()}`);
        $finalAmount.text(`₱${result.final_total.toLocaleString()}`);
        // store final total on a known element so other scripts can read it
        if ($('#totalAmount').length) {
          $('#totalAmount').data('original', result.final_total);
          // also update visible/input value if it's an input
          if ($('#totalAmount').is('input')) $('#totalAmount').val(result.final_total);
          else $('#totalAmount').text(`₱${result.final_total.toLocaleString()}`);
        }
      } else {
        showFloatingAlert(result.message, "warning");
        $("#voucherSelect").val("");
        $discountAmount.text("-₱0.00");
        $finalAmount.text(`₱${baseAmount.toLocaleString()}`);
      }
    },
  };


  // Event Listeners.
  $("#bookingModal").on("shown.bs.modal", function () {
    VoucherManager.loadVouchers();
  });


  $("#voucherSelect").on("change", async function () {
    const $select = $(this);
    const baseAmount = calculateBaseAmount();
    PMSMotion.setButtonLoading($select, true);
    try {
      await VoucherManager.updateBookingSummary(baseAmount);
    } finally {
      PMSMotion.setButtonLoading($select, false);
    }
  });


  // ✅ Helper: calculate base amount
  function calculateBaseAmount() {
    const rentalDateStr = $("#rental_date").val();
    const returnDateStr = $("#return_date").val();


    // Primary method: compute from rate * days if rate element exists
    const rateText = $('#vehicle_rate').text() || '';
    const rate = parseFloat(rateText.replace(/[₱,]/g, '')) || 0;
    if (rentalDateStr && returnDateStr && rate > 0) {
      const rentalDate = new Date(rentalDateStr);
      const returnDate = new Date(returnDateStr);
      let days = Math.ceil((returnDate - rentalDate) / (1000 * 60 * 60 * 24));
      if (isNaN(days) || days <= 0) days = 1;
      const total = days * rate;
      console.log('Calculated base amount (rate*days):', total);
      return total;
    }


    // Fallbacks: try to parse 'Total' from booking preview or known elements
    try {
      const previewText = $('#bookingPreview').text() || '';
      const match = previewText.match(/Total[:\s]*₱?([\d,]+(?:\.\d+)?)/i);
      if (match && match[1]) {
        const val = parseFloat(match[1].replace(/,/g, '')) || 0;
        if (val > 0) {
          console.log('Calculated base amount (from bookingPreview):', val);
          return val;
        }
      }
    } catch (e) {
      console.debug('voucher-manager: bookingPreview parse failed', e);
    }


    if ($('#finalAmount').length) {
      const val = parseFloat($('#finalAmount').text().replace(/[₱,]/g, '')) || 0;
      if (val > 0) {
        console.log('Calculated base amount (from #finalAmount):', val);
        return val;
      }
    }


    if ($('#totalAmount').length) {
      const dataVal = parseFloat($('#totalAmount').data('original')) || 0;
      const txtVal = parseFloat($('#totalAmount').text().replace(/[₱,]/g, '')) || 0;
      const val = dataVal || txtVal;
      if (val > 0) {
        console.log('Calculated base amount (from #totalAmount):', val);
        return val;
      }
    }


    // If all else fails, return 0 so caller may show a helpful message
    return 0;
  }
});



