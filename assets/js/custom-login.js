// File: assets/js/custom-login.js
document.addEventListener('DOMContentLoaded', function () {
    const formContainer = document.getElementById('readysms-form-container');
    if (!formContainer) {
        return; // Exit if the main form container isn't on the page
    }

    const smsStep1Form = document.getElementById('readysms-sms-step1-form');
    const smsStep2Form = document.getElementById('readysms-sms-step2-form');
    
    const sendOtpButton = document.getElementById('readysms-send-otp-button');
    const verifyOtpButton = document.getElementById('readysms-verify-otp-button');
    
    const phoneNumberInput = document.getElementById('readysms-phone-number');
    const otpInput = document.getElementById('readysms-otp-code');
    
    const messageArea = document.getElementById('readysms-message-area');
    const timerDisplay = document.getElementById('readysms-timer-display');
    const remainingTimeSpan = document.getElementById('readysms-remaining-time');
    const changePhoneLink = document.getElementById('readysms-change-phone-link');

    const redirectUrl = formContainer.dataset.redirectUrl || window.location.href;
    let timerInterval;

    // Update OTP input placeholder and maxlength based on localized otp_length
    const otpLength = readyLoginAjax.otp_length || 6; // Default to 6 if not set
    if (otpInput) {
        otpInput.setAttribute('placeholder', otpLength + ' ' + 'رقمی');
        otpInput.setAttribute('maxlength', otpLength);
    }


    function showMessage(text, type = 'error') {
        if (messageArea) {
            messageArea.textContent = text;
            messageArea.className = 'readysms-message ' + type;
            messageArea.style.display = 'block';
        } else {
            alert(text); // Fallback
        }
    }

    function clearMessage() {
        if (messageArea) {
            messageArea.style.display = 'none';
            messageArea.textContent = '';
        }
    }

    if (sendOtpButton) {
        sendOtpButton.addEventListener('click', function () {
            clearMessage();
            const phoneNumber = phoneNumberInput.value.trim();
            if (!phoneNumber) {
                showMessage(readyLoginAjax.error_phone, 'error');
                phoneNumberInput.focus();
                return;
            }
            if (!/^(09\d{9})$/.test(phoneNumber)) {
                showMessage(readyLoginAjax.error_phone, 'error'); // More generic phone error
                phoneNumberInput.focus();
                return;
            }

            sendOtpButton.disabled = true;
            sendOtpButton.textContent = readyLoginAjax.sending_otp;

            fetch(readyLoginAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({
                    action: 'ready_sms_send_otp',
                    phone_number: phoneNumber,
                    nonce: readyLoginAjax.nonce,
                }),
            })
            .then(response => {
                if (!response.ok) { // Check for non-2xx HTTP status codes
                    // Try to parse error if server sent JSON error object for non-2xx
                    return response.json().then(errData => {
                        throw { success: false, data: errData.data || readyLoginAjax.error_general };
                    }).catch(() => { // If not JSON, or other network error
                        throw { success: false, data: readyLoginAjax.error_general + ' (Status: ' + response.status + ')'};
                    });
                }
                return response.json();
            })
            .then(data => {
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = readyLoginAjax.send_otp_text;
                if (data.success) {
                    showMessage(data.data.message, 'success');
                    startTimer(data.data.remaining_time || readyLoginAjax.timer_duration);
                    if (smsStep1Form) smsStep1Form.style.display = 'none';
                    if (smsStep2Form) smsStep2Form.style.display = 'block';
                    if (otpInput) otpInput.focus();
                } else {
                    showMessage(data.data || readyLoginAjax.error_general, 'error');
                }
            })
            .catch(error => {
                console.error('Send OTP Fetch Error:', error);
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = readyLoginAjax.send_otp_text;
                showMessage(error.data || readyLoginAjax.error_general, 'error');
            });
        });
    }

    function startTimer(duration) {
        let remainingTime = parseInt(duration, 10);
        if (sendOtpButton) sendOtpButton.disabled = true;
        
        if (timerDisplay && remainingTimeSpan) {
            timerDisplay.style.display = 'block';
            remainingTimeSpan.textContent = remainingTime;
        }

        clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            remainingTime--;
            if (remainingTimeSpan) remainingTimeSpan.textContent = remainingTime;
            
            if (remainingTime <= 0) {
                clearInterval(timerInterval);
                if (sendOtpButton) sendOtpButton.disabled = false;
                if (timerDisplay) timerDisplay.style.display = 'none';
            }
        }, 1000);
    }

    if (verifyOtpButton) {
        verifyOtpButton.addEventListener('click', function () {
            clearMessage();
            const phoneNumber = phoneNumberInput.value.trim();
            const otpCode = otpInput.value.trim();
            
            if (!otpCode) {
                showMessage(readyLoginAjax.error_otp_empty, 'error');
                if (otpInput) otpInput.focus();
                return;
            }
            // Validate OTP format based on dynamic length
            const otpRegex = new RegExp(`^\\d{${otpLength}}$`);
            if (!otpRegex.test(otpCode)) {
                showMessage(readyLoginAjax.error_otp_invalid + ' (' + otpLength + ' ' + 'رقمی)', 'error');
                if (otpInput) {
                    otpInput.focus();
                    otpInput.select();
                }
                return;
            }

            verifyOtpButton.disabled = true;
            verifyOtpButton.textContent = readyLoginAjax.verifying_otp;

            fetch(readyLoginAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({
                    action: 'ready_sms_verify_otp',
                    phone_number: phoneNumber,
                    otp_code: otpCode,
                    nonce: readyLoginAjax.nonce,
                    redirect_link: redirectUrl, 
                }),
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => {
                        throw { success: false, data: errData.data || readyLoginAjax.error_general };
                    }).catch(() => {
                        throw { success: false, data: readyLoginAjax.error_general + ' (Status: ' + response.status + ')'};
                    });
                }
                return response.json();
            })
            .then(data => {
                verifyOtpButton.disabled = false;
                verifyOtpButton.textContent = readyLoginAjax.verify_otp_text;
                if (data.success && data.data.redirect_url) {
                    showMessage('ورود موفقیت آمیز بود. در حال انتقال...', 'success');
                    window.location.href = data.data.redirect_url;
                } else {
                    showMessage(data.data || readyLoginAjax.error_general, 'error');
                    if (otpInput) {
                        otpInput.focus();
                        otpInput.select();
                    }
                }
            })
            .catch(error => {
                console.error('Verify OTP Fetch Error:', error);
                verifyOtpButton.disabled = false;
                verifyOtpButton.textContent = readyLoginAjax.verify_otp_text;
                showMessage(error.data || readyLoginAjax.error_general, 'error');
            });
        });
    }

    if(changePhoneLink && smsStep1Form && smsStep2Form) {
        changePhoneLink.addEventListener('click', function(e) {
            e.preventDefault();
            clearMessage();
            clearInterval(timerInterval);
            if (timerDisplay) timerDisplay.style.display = 'none';
            if (sendOtpButton) {
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = readyLoginAjax.send_otp_text;
            }
            if (smsStep2Form) smsStep2Form.style.display = 'none';
            if (smsStep1Form) smsStep1Form.style.display = 'block';
            if (phoneNumberInput) phoneNumberInput.focus();
        });
    }
    
    if (otpInput) {
        otpInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (verifyOtpButton && !verifyOtpButton.disabled) verifyOtpButton.click();
            }
        });
    }
    if (phoneNumberInput) {
        phoneNumberInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (sendOtpButton && !sendOtpButton.disabled) sendOtpButton.click();
            }
        });
    }
});
