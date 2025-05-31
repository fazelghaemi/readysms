// File: assets/js/custom-login.js
document.addEventListener('DOMContentLoaded', function () {
    const formContainer = document.getElementById('readysms-form-container');
    if (!formContainer) {
        return; 
    }

    if (typeof readyLoginAjax === 'undefined' || !readyLoginAjax.ajaxurl) {
        console.error('ReadySMS: Localization object (readyLoginAjax) or ajaxurl not found.');
        // ... ( نمایش خطا مشابه قبل ) ...
        return;
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

    const otpExpectedLength = parseInt(readyLoginAjax.otp_length, 10) || 6;
    const currentCountryCodeMode = readyLoginAjax.country_code_mode || 'iran_only'; // 'iran_only' or 'all_countries'

    if (otpInput) {
        let placeholderText = otpExpectedLength + ' ' + (readyLoginAjax.digits_text || 'رقمی');
        otpInput.setAttribute('placeholder', placeholderText);
        otpInput.setAttribute('maxlength', otpExpectedLength);
    }

    function showUserMessage(text, type = 'error') {
        if (messageArea) {
            messageArea.textContent = text;
            messageArea.className = 'readysms-message ' + type;
            messageArea.style.display = 'block';
        } else {
            alert(text);
        }
    }

    function clearUserMessage() {
        if (messageArea) {
            messageArea.style.display = 'none';
            messageArea.textContent = '';
        }
    }

    if (sendOtpButton) {
        sendOtpButton.addEventListener('click', function () {
            clearUserMessage();
            const phoneNumber = phoneNumberInput.value.trim();
            if (!phoneNumber) {
                showUserMessage(readyLoginAjax.error_phone, 'error');
                phoneNumberInput.focus();
                return;
            }

            // تغییر 5: اعتبارسنجی اولیه برای شماره موبایل
            // این اعتبارسنجی می‌تواند ساده‌تر باشد و اعتبارسنجی دقیق‌تر در سمت سرور انجام شود.
            // ^(\+?\d{1,4})?(09\d{9})$  یا  ^(09\d{9})$  یا  ^(\+989\d{9})$
            // برای سادگی فعلی، اجازه می‌دهیم با + شروع شود یا با 09
            let isValidPhoneFormat = false;
            if (currentCountryCodeMode === 'iran_only') {
                isValidPhoneFormat = /^(09\d{9})$/.test(phoneNumber) || /^(\+989\d{9})$/.test(phoneNumber);
            } else { // all_countries
                isValidPhoneFormat = /^\+?\d{7,15}$/.test(phoneNumber); // یک فرمت عمومی‌تر برای شماره‌های بین‌المللی
            }

            if (!isValidPhoneFormat) {
                showUserMessage(readyLoginAjax.error_phone, 'error');
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
                    phone_number: phoneNumber, // ارسال شماره همانطور که کاربر وارد کرده
                    nonce: readyLoginAjax.nonce,
                }),
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => Promise.reject(errData.data || readyLoginAjax.error_general + ' (S' + response.status + ')'))
                                     .catch(() => Promise.reject(readyLoginAjax.error_general + ' (S' + response.status + ')'));
                }
                return response.json();
            })
            .then(data => {
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = readyLoginAjax.send_otp_text;
                if (data.success) {
                    showUserMessage(data.data.message, 'success');
                    // تغییر 3: استفاده از timer_duration از localize
                    startOtpTimer(parseInt(readyLoginAjax.timer_duration, 10) || 120); 
                    if (smsStep1Form) smsStep1Form.style.display = 'none';
                    if (smsStep2Form) smsStep2Form.style.display = 'block';
                    if (otpInput) otpInput.focus();
                } else {
                    showUserMessage(data.data || readyLoginAjax.error_general, 'error');
                }
            })
            .catch(errorText => {
                console.error('Send OTP Fetch Error:', errorText);
                sendOtpButton.disabled = false;
                sendOtpButton.textContent = readyLoginAjax.send_otp_text;
                showUserMessage(typeof errorText === 'string' ? errorText : readyLoginAjax.error_general, 'error');
            });
        });
    }

    function startOtpTimer(duration) { // پارامتر duration از readyLoginAjax.timer_duration می‌آید
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
            clearUserMessage();
            const phoneNumber = phoneNumberInput.value.trim();
            const otpCodeByUser = otpInput.value.trim();
            
            if (!otpCodeByUser) {
                showUserMessage(readyLoginAjax.error_otp_empty, 'error');
                if (otpInput) otpInput.focus();
                return;
            }

            const otpValidationRegex = new RegExp(`^\\d{<span class="math-inline">\{otpExpectedLength\}\}</span>`);
            if (!otpValidationRegex.test(otpCodeByUser)) {
                let dynamicOtpErrorMessage = (readyLoginAjax.error_otp_invalid_format || 'فرمت کد تایید صحیح نیست.');
                dynamicOtpErrorMessage += ' (' + otpExpectedLength + ' ' + (readyLoginAjax.digits_text || 'رقمی') + ')';
                showUserMessage(dynamicOtpErrorMessage, 'error');
                
                if
