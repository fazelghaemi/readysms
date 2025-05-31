// File: assets/js/custom-login.js
document.addEventListener('DOMContentLoaded', function () {
    const formContainer = document.getElementById('readysms-form-container');
    if (!formContainer) {
        // console.warn('ReadySMS: Login form container not found.');
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

    const redirectUrl = formContainer.dataset.redirectUrl || window.location.href; // Default to current page if not set
    let timerInterval;

    function showMessage(text, type = 'error') {
        if (messageArea) {
            messageArea.textContent = text;
            messageArea.className = 'readysms-message ' + type; // 'success' or 'error'
            messageArea.style.display = 'block';
        } else {
            alert(text); // Fallback if message area is somehow missing
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
                return;
            }
            if (!/^(09\d{9})$/.test(phoneNumber)) {
                showMessage('قالب شماره موبایل صحیح نیست (مثال: 09123456789)', 'error');
                return;
            }


            sendOtpButton.disabled = true;
            sendOtpButton.textContent = 'در حال ارسال...';

            fetch(readyLoginAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({
                    action: 'ready_sms_send_otp',
                    phone_number: phoneNumber,
                    nonce: readyLoginAjax.nonce,
                }),
            })
            .then(response => response.json())
            .then(data => {
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = 'دریافت کد تایید';
                if (data.success) {
                    showMessage(data.data.message, 'success');
                    startTimer(data.data.remaining_time || readyLoginAjax.timer_duration);
                    if (smsStep1Form) smsStep1Form.style.display = 'none';
                    if (smsStep2Form) smsStep2Form.style.display = 'block';
                    otpInput.focus();
                } else {
                    showMessage(data.data || readyLoginAjax.error_general, 'error');
                }
            })
            .catch(error => {
                console.error('Send OTP Error:', error);
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = 'دریافت کد تایید';
                showMessage(readyLoginAjax.error_general, 'error');
            });
        });
    }

    function startTimer(duration) {
        let remainingTime = parseInt(duration, 10);
        if (sendOtpButton) sendOtpButton.disabled = true; // Keep it disabled during timer
        
        if (timerDisplay && remainingTimeSpan) {
            timerDisplay.style.display = 'block';
            remainingTimeSpan.textContent = remainingTime;
        }

        clearInterval(timerInterval); // Clear any existing timer
        timerInterval = setInterval(() => {
            remainingTime--;
            if (remainingTimeSpan) remainingTimeSpan.textContent = remainingTime;
            
            if (remainingTime <= 0) {
                clearInterval(timerInterval);
                if (sendOtpButton) sendOtpButton.disabled = false; // Re-enable original send OTP button
                if (timerDisplay) timerDisplay.style.display = 'none';
                // If using a separate resend button, enable it here.
                // For now, we re-enable the main send button (which is hidden at this stage)
                // or expect user to click "change number" to go back.
            }
        }, 1000);
    }

    if (verifyOtpButton) {
        verifyOtpButton.addEventListener('click', function () {
            clearMessage();
            const phoneNumber = phoneNumberInput.value.trim(); // Assuming phone number is still in the input or stored
            const otpCode = otpInput.value.trim();
            
            if (!otpCode) {
                showMessage(readyLoginAjax.error_otp, 'error');
                return;
            }
            if (!/^\d{6}$/.test(otpCode)) {
                showMessage('کد تایید باید ۶ رقم باشد.', 'error');
                return;
            }

            verifyOtpButton.disabled = true;
            verifyOtpButton.textContent = 'در حال بررسی...';

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
            .then(response => response.json())
            .then(data => {
                verifyOtpButton.disabled = false;
                verifyOtpButton.textContent = 'ورود / ثبت نام';
                if (data.success && data.data.redirect_url) {
                    showMessage('ورود موفقیت آمیز بود. در حال انتقال...', 'success');
                    window.location.href = data.data.redirect_url;
                } else {
                    showMessage(data.data || readyLoginAjax.error_general, 'error');
                    otpInput.focus();
                    otpInput.select();
                }
            })
            .catch(error => {
                console.error('Verify OTP Error:', error);
                verifyOtpButton.disabled = false;
                verifyOtpButton.textContent = 'ورود / ثبت نام';
                showMessage(readyLoginAjax.error_general, 'error');
            });
        });
    }

    if(changePhoneLink && smsStep1Form && smsStep2Form) {
        changePhoneLink.addEventListener('click', function(e) {
            e.preventDefault();
            clearMessage();
            clearInterval(timerInterval);
            if (timerDisplay) timerDisplay.style.display = 'none';
            if (sendOtpButton) sendOtpButton.disabled = false; // Re-enable original send OTP button

            smsStep2Form.style.display = 'none';
            smsStep1Form.style.display = 'block';
            phoneNumberInput.focus();
        });
    }
    
    // Allow submitting OTP form with Enter key
    if (otpInput) {
        otpInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // Prevent default form submission if it's part of a <form>
                if (verifyOtpButton) verifyOtpButton.click();
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
