// File: assets/js/admin-settings.js
jQuery(document).ready(function($) {
    if (typeof readyLoginAdminAjax === 'undefined') {
        console.error('ReadySMS Admin: Localization object (readyLoginAdminAjax) not found.');
        return;
    }

    const adminOtpLength = readyLoginAdminAjax.otp_length || 6;
    const adminTestOtpInput = $('#admin_test_otp_code');

    if (adminTestOtpInput.length) {
        adminTestOtpInput.attr('placeholder', adminOtpLength + ' ' + 'رقمی');
        adminTestOtpInput.attr('maxlength', adminOtpLength);
    }

    // Helper to display results and manage button states
    function handleApiResponse(buttonElement, resultDivId, response, successBtnText, errorBtnText) {
        buttonElement.prop('disabled', false).text(successBtnText); // Reset button text assuming success, override on error
        const resultDiv = $('#' + resultDivId);
        if (!resultDiv.length) return;

        let messageHtml = '';
        let isSuccess = response.success;

        if (isSuccess) {
            messageHtml = '<p><strong>' + (response.data.message || 'موفقیت آمیز بود.') + '</strong></p>';
             if (response.data.response_data || (response.data.credit && response.data.currency)) {
                messageHtml += '<pre>' + JSON.stringify(response.data.response_data || response.data, null, 2) + '</pre>';
            }
        } else {
            buttonElement.text(errorBtnText || successBtnText); // Use specific error button text or default back
            const errorMessage = response.data ? (response.data.message || response.data) : readyLoginAdminAjax.msg_unexpected_error;
            messageHtml = '<p><strong>' + errorMessage + '</strong></p>';
            if (response.data && response.data.response_data) { // If PHP sent additional data with error
                messageHtml += '<pre>' + JSON.stringify(response.data.response_data, null, 2) + '</pre>';
            }
        }
        resultDiv.html(messageHtml).removeClass('success error').addClass(isSuccess ? 'success' : 'error').show();
        
        // Toastr notifications (optional, remove if not using toastr)
        if (typeof toastr !== 'undefined') {
            if(isSuccess) {
                toastr.success(response.data.message || 'عملیات موفقیت آمیز بود.');
            } else {
                const toastMessage = response.data ? (response.data.message || response.data) : 'خطا در انجام عملیات.';
                toastr.error(toastMessage);
            }
        }
    }
    
    function handleApiFailure(buttonElement, resultDivId, defaultBtnText) {
        buttonElement.prop('disabled', false).text(defaultBtnText);
        const resultDiv = $('#' + resultDivId);
        if (resultDiv.length) {
            resultDiv.html('<p><strong>' + readyLoginAdminAjax.msg_unexpected_error + '</strong></p>')
                     .removeClass('success').addClass('error').show();
        }
        if (typeof toastr !== 'undefined') {
            toastr.error(readyLoginAdminAjax.msg_unexpected_error);
        }
    }


    // 1. Send Test OTP
    $('#admin_send_test_otp_button').on('click', function() {
        const phone = $('#admin_test_phone_number').val().trim();
        const resultDivId = 'admin_test_otp_result';
        const $thisButton = $(this);
        const defaultButtonText = readyLoginAdminAjax.send_otp_btn_text;

        $('#' + resultDivId).hide().empty(); 

        if (!phone) {
            handleApiResponse($thisButton, resultDivId, {success: false, data: {message: readyLoginAdminAjax.msg_fill_phone}}, defaultButtonText, defaultButtonText);
            return;
        }
        if (!/^(09\d{9})$/.test(phone)) {
             handleApiResponse($thisButton, resultDivId, {success: false, data: {message: 'قالب شماره موبایل صحیح نیست (مثال: 09123456789)'}}, defaultButtonText, defaultButtonText);
            return;
        }

        $thisButton.prop('disabled', true).text(readyLoginAdminAjax.sending_text);

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.send_test_otp_action,
            nonce: readyLoginAdminAjax.nonce,
            phone_number: phone
        })
        .done(function(response) {
            handleApiResponse($thisButton, resultDivId, response, defaultButtonText, defaultButtonText);
            if (response.success) {
                $('#admin_verify_otp_section').slideDown();
                adminTestOtpInput.focus();
            } else {
                $('#admin_verify_otp_section').slideUp();
            }
        })
        .fail(function() {
            handleApiFailure($thisButton, resultDivId, defaultButtonText);
            $('#admin_verify_otp_section').slideUp();
        });
    });

    // 2. Verify Test OTP
    $('#admin_verify_test_otp_button').on('click', function() {
        const phone = $('#admin_test_phone_number').val().trim();
        const otp = adminTestOtpInput.val().trim();
        const resultDivId = 'admin_test_otp_result';
        const $thisButton = $(this);
        const defaultButtonText = readyLoginAdminAjax.verify_otp_btn_text;

        const otpRegex = new RegExp(`^\\d{${adminOtpLength}}$`);
        if (!otp || !otpRegex.test(otp)) {
            const errorMsg = readyLoginAdminAjax.msg_fill_otp_len_invalid + ' (' + adminOtpLength + ' ' + 'رقمی)';
            handleApiResponse($thisButton, resultDivId, {success: false, data: {message: errorMsg}}, defaultButtonText, defaultButtonText);
            return;
        }

        $thisButton.prop('disabled', true).text(readyLoginAdminAjax.verifying_text);

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.verify_test_otp_action,
            nonce: readyLoginAdminAjax.nonce,
            phone_number: phone,
            otp_code: otp
        })
        .done(function(response) {
            handleApiResponse($thisButton, resultDivId, response, defaultButtonText, defaultButtonText);
        })
        .fail(function() {
            handleApiFailure($thisButton, resultDivId, defaultButtonText);
        });
    });

    // 3. Check Message Status
    $('#admin_check_status_button').on('click', function() {
        const refId = $('#admin_status_reference_id').val().trim();
        const resultDivId = 'admin_status_result';
        const $thisButton = $(this);
        const defaultButtonText = readyLoginAdminAjax.check_status_btn_text;

        $('#' + resultDivId).hide().empty();
        if (!refId) {
             handleApiResponse($thisButton, resultDivId, {success: false, data: {message: readyLoginAdminAjax.msg_fill_ref_id}}, defaultButtonText, defaultButtonText);
            return;
        }
        $thisButton.prop('disabled', true).text(readyLoginAdminAjax.fetching_text);

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.check_status_action,
            nonce: readyLoginAdminAjax.nonce,
            reference_id: refId
        })
        .done(function(response) {
            handleApiResponse($thisButton, resultDivId, response, defaultButtonText, defaultButtonText);
        })
        .fail(function() {
            handleApiFailure($thisButton, resultDivId, defaultButtonText);
        });
    });

    // 4. Get Template Info
    $('#admin_get_template_button').on('click', function() {
        const templateId = $('#admin_template_id_test').val().trim();
        const resultDivId = 'admin_template_result';
        const $thisButton = $(this);
        const defaultButtonText = readyLoginAdminAjax.get_template_btn_text;

        $('#' + resultDivId).hide().empty();
        if (!templateId) {
            handleApiResponse($thisButton, resultDivId, {success: false, data: {message: readyLoginAdminAjax.msg_fill_template_id}}, defaultButtonText, defaultButtonText);
            return;
        }
        $thisButton.prop('disabled', true).text(readyLoginAdminAjax.fetching_text);

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.get_template_action,
            nonce: readyLoginAdminAjax.nonce,
            template_id_to_test: templateId
        })
        .done(function(response) {
            handleApiResponse($thisButton, resultDivId, response, defaultButtonText, defaultButtonText);
        })
        .fail(function() {
            handleApiFailure($thisButton, resultDivId, defaultButtonText);
        });
    });

    // 5. Get Balance
    $('#admin_get_balance_button').on('click', function() {
        const resultDivId = 'admin_balance_result';
        const $thisButton = $(this);
        const defaultButtonText = readyLoginAdminAjax.get_balance_btn_text;

        $('#' + resultDivId).hide().empty();
        $thisButton.prop('disabled', true).text(readyLoginAdminAjax.fetching_text);

        $.post(readyLoginAdminAjax.ajaxurl, {
            action: readyLoginAdminAjax.get_balance_action,
            nonce: readyLoginAdminAjax.nonce
        })
        .done(function(response) {
            handleApiResponse($thisButton, resultDivId, response, defaultButtonText, defaultButtonText);
        })
        .fail(function() {
            handleApiFailure($thisButton, resultDivId, defaultButtonText);
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
            "preventDuplicates": true, // Prevent same toast from showing multiple times
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "7000", // Longer timeout
            "extendedTimeOut": "2000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut",
            "rtl": (jQuery('body').hasClass('rtl'))
        };
    }
});
