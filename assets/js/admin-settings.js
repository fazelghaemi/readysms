// File: assets/js/admin-settings.js
jQuery(document).ready(function($) {
    if (typeof readyLoginAdminAjax === 'undefined') {
        // console.error('ReadySMS Admin: Localization object not found.');
        return;
    }

    // Helper to display results
    function displayResult(elementId, data, isSuccess) {
        const resultDiv = $('#' + elementId);
        if (!resultDiv.length) return;

        let messageHtml = '';
        if (isSuccess) {
            messageHtml = '<p><strong>' + (data.message || 'موفقیت آمیز بود.') + '</strong></p>';
             if (data.response_data || (data.credit && data.currency)) { // For balance
                messageHtml += '<pre>' + JSON.stringify(data.response_data || data, null, 2) + '</pre>';
            }
        } else {
            messageHtml = '<p><strong>' + (data.message || data.data || 'خطا رخ داد.') + '</strong></p>';
            if (data.response_data) {
                messageHtml += '<pre>' + JSON.stringify(data.response_data, null, 2) + '</pre>';
            }
        }
        resultDiv.html(messageHtml).removeClass('success error').addClass(isSuccess ? 'success' : 'error').show();
        if (typeof toastr !== 'undefined') {
            if(isSuccess) toastr.success(data.message || 'عملیات موفقیت آمیز بود.');
            else toastr.error(data.message || data.data || 'خطا در انجام عملیات.');
        }
    }

    // 1. Send Test OTP
    $('#admin_send_test_otp_button').on('click', function() {
        const phone = $('#admin_test_phone_number').val().trim();
        const resultDivId = 'admin_test_otp_result';
        $('#' + resultDivId).hide().empty(); // Clear previous result

        if (!phone) {
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_fill_phone }, false);
            return;
        }
        if (!/^(09\d{9})$/.test(phone)) {
            displayResult(resultDivId, { message: 'قالب شماره موبایل صحیح نیست (مثال: 09123456789)' }, false);
            return;
        }


        $(this).prop('disabled', true).text('در حال ارسال...');

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.send_test_otp_action,
            nonce: readyLoginAdminAjax.nonce,
            phone_number: phone
        }, function(response) {
            $('#admin_send_test_otp_button').prop('disabled', false).text('ارسال پیامک OTP آزمایشی');
            if (response.success) {
                displayResult(resultDivId, response.data, true);
                $('#admin_verify_otp_section').show();
                $('#admin_test_otp_code').focus();
            } else {
                displayResult(resultDivId, response.data || {message: 'خطای ناشناخته'}, false);
                $('#admin_verify_otp_section').hide();
            }
        }).fail(function() {
            $('#admin_send_test_otp_button').prop('disabled', false).text('ارسال پیامک OTP آزمایشی');
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_unexpected_error }, false);
             $('#admin_verify_otp_section').hide();
        });
    });

    // 2. Verify Test OTP
    $('#admin_verify_test_otp_button').on('click', function() {
        const phone = $('#admin_test_phone_number').val().trim(); // Assumed phone is still there
        const otp = $('#admin_test_otp_code').val().trim();
        const resultDivId = 'admin_test_otp_result'; // Display result in the same area

        if (!otp) {
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_fill_otp }, false);
            return;
        }
         if (!/^\d{6}$/.test(otp)) {
            displayResult(resultDivId, { message: 'کد تایید باید ۶ رقم باشد.' }, false);
            return;
        }

        $(this).prop('disabled', true).text('در حال بررسی...');

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.verify_test_otp_action,
            nonce: readyLoginAdminAjax.nonce,
            phone_number: phone,
            otp_code: otp
        }, function(response) {
            $('#admin_verify_test_otp_button').prop('disabled', false).text('بررسی کد OTP');
            displayResult(resultDivId, response.data || {message: 'خطای ناشناخته'}, response.success);
        }).fail(function() {
            $('#admin_verify_test_otp_button').prop('disabled', false).text('بررسی کد OTP');
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_unexpected_error }, false);
        });
    });

    // 3. Check Message Status
    $('#admin_check_status_button').on('click', function() {
        const refId = $('#admin_status_reference_id').val().trim();
        const resultDivId = 'admin_status_result';
        $('#' + resultDivId).hide().empty();

        if (!refId) {
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_fill_ref_id }, false);
            return;
        }
        $(this).prop('disabled', true).text('در حال بررسی...');

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.check_status_action,
            nonce: readyLoginAdminAjax.nonce,
            reference_id: refId
        }, function(response) {
             $('#admin_check_status_button').prop('disabled', false).text('بررسی وضعیت');
            displayResult(resultDivId, response.data || {message: 'خطای ناشناخته'}, response.success);
        }).fail(function() {
            $('#admin_check_status_button').prop('disabled', false).text('بررسی وضعیت');
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_unexpected_error }, false);
        });
    });

    // 4. Get Template Info
    $('#admin_get_template_button').on('click', function() {
        const templateId = $('#admin_template_id_test').val().trim();
        const resultDivId = 'admin_template_result';
        $('#' + resultDivId).hide().empty();

        if (!templateId) {
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_fill_template_id }, false);
            return;
        }
        $(this).prop('disabled', true).text('در حال دریافت...');

        $.post(readyLoginAjax.ajaxurl, {
            action: readyLoginAdminAjax.get_template_action,
            nonce: readyLoginAdminAjax.nonce,
            template_id_to_test: templateId // Changed from 'template_id' to match PHP
        }, function(response) {
            $('#admin_get_template_button').prop('disabled', false).text('دریافت اطلاعات قالب');
            displayResult(resultDivId, response.data || {message: 'خطای ناشناخته'}, response.success);
        }).fail(function() {
            $('#admin_get_template_button').prop('disabled', false).text('دریافت اطلاعات قالب');
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_unexpected_error }, false);
        });
    });

    // 5. Get Balance
    $('#admin_get_balance_button').on('click', function() {
        const resultDivId = 'admin_balance_result';
        $('#' + resultDivId).hide().empty();
        $(this).prop('disabled', true).text('در حال دریافت...');

        $.post(readyLoginAjax.ajaxurl, {
            action: readyLoginAdminAjax.get_balance_action,
            nonce: readyLoginAdminAjax.nonce
        }, function(response) {
            $('#admin_get_balance_button').prop('disabled', false).text('دریافت موجودی');
            displayResult(resultDivId, response.data || {message: 'خطای ناشناخته'}, response.success);
        }).fail(function() {
            $('#admin_get_balance_button').prop('disabled', false).text('دریافت موجودی');
            displayResult(resultDivId, { message: readyLoginAdminAjax.msg_unexpected_error }, false);
        });
    });

    // Initialize Toastr options (optional)
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-left", // RTL friendly
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut",
            "rtl": ($('body').hasClass('rtl')) // Auto-detect RTL
        };
    }
});
