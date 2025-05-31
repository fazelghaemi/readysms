// File: assets/js/custom-login.js
document.addEventListener('DOMContentLoaded', function () {
    const formContainer = document.getElementById('readysms-form-container');
    if (!formContainer) {
        // console.warn('ReadySMS: Login form container not found.');
        return; 
    }

    // اطمینان از وجود آبجکت readyLoginAjax و پارامترهای ضروری
    if (typeof readyLoginAjax === 'undefined' || !readyLoginAjax.ajaxurl) {
        console.error('ReadySMS: Localization object (readyLoginAjax) or ajaxurl not found.');
        if (formContainer) { // نمایش خطا در فرم اگر المان پیام وجود دارد
            const tempMsgArea = document.getElementById('readysms-message-area');
            if(tempMsgArea) {
                tempMsgArea.textContent = 'خطای اساسی در بارگذاری افزونه. با مدیر سایت تماس بگیرید.';
                tempMsgArea.className = 'readysms-message error';
                tempMsgArea.style.display = 'block';
            } else {
                alert('خطای اساسی در بارگذاری افزونه. با مدیر سایت تماس بگیرید.');
            }
        }
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

    // دریافت طول کد OTP از تنظیمات PHP (ارسال شده با wp_localize_script)
    const otpExpectedLength = parseInt(readyLoginAjax.otp_length, 10) || 6; // مقدار پیش‌فرض ۶ اگر تعریف نشده باشد
    // console.log('ReadySMS: Expected OTP Length:', otpExpectedLength); // برای دیباگ در کنسول مرورگر

    if (otpInput) {
        let placeholderText = otpExpectedLength + ' ' + 'رقمی'; // مثال: ۶ رقمی
        try { // برای اطمینان از اینکه تابع ترجمه در readyLoginAjax.php وجود دارد و عدد را فارسی می‌کند
             placeholderText = otpExpectedLength + ' ' + (readyLoginAjax.digits_text || 'رقمی');
        } catch(e){}
        otpInput.setAttribute('placeholder', placeholderText);
        otpInput.setAttribute('maxlength', otpExpectedLength);
    }


    function showUserMessage(text, type = 'error') {
        if (messageArea) {
            messageArea.textContent = text;
            messageArea.className = 'readysms-message ' + type; // 'success' یا 'error'
            messageArea.style.display = 'block';
        } else {
            alert(text); // حالت اضطراری اگر المان پیام وجود نداشته باشد
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
            if (!/^(09\d{9})$/.test(phoneNumber)) {
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
                    phone_number: phoneNumber,
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
                    startOtpTimer(data.data.remaining_time || readyLoginAjax.timer_duration);
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

    function startOtpTimer(duration) {
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
            const phoneNumber = phoneNumberInput.value.trim(); // شماره موبایل باید از مرحله اول در دسترس باشد
            const otpCodeByUser = otpInput.value.trim();
            
            if (!otpCodeByUser) {
                showUserMessage(readyLoginAjax.error_otp_empty, 'error');
                if (otpInput) otpInput.focus();
                return;
            }

            // اعتبارسنجی طول کد OTP وارد شده بر اساس طول تنظیم شده
            const otpValidationRegex = new RegExp(`^\\d{${otpExpectedLength}}$`);
            if (!otpValidationRegex.test(otpCodeByUser)) {
                // ایجاد پیام خطای پویا برای طول کد
                let dynamicOtpErrorMessage = (readyLoginAjax.error_otp_invalid_format || 'فرمت کد تایید صحیح نیست.');
                dynamicOtpErrorMessage += ' ' + '(' + otpExpectedLength + ' ' + (readyLoginAjax.digits_text || 'رقمی') + ')';
                showUserMessage(dynamicOtpErrorMessage, 'error');
                
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
                    otp_code: otpCodeByUser,
                    nonce: readyLoginAjax.nonce,
                    redirect_link: redirectUrl, 
                }),
            })
            .then(response => {
                 if (!response.ok) {
                    return response.json().then(errData => Promise.reject(errData.data || readyLoginAjax.error_general + ' (V' + response.status + ')'))
                                     .catch(() => Promise.reject(readyLoginAjax.error_general + ' (V' + response.status + ')'));
                }
                return response.json();
            })
            .then(data => {
                verifyOtpButton.disabled = false;
                verifyOtpButton.textContent = readyLoginAjax.verify_otp_text;
                if (data.success && data.data.redirect_url) {
                    showUserMessage('ورود موفقیت آمیز بود. در حال انتقال شما...', 'success');
                    window.location.href = data.data.redirect_url;
                } else {
                    showUserMessage(data.data || readyLoginAjax.error_general, 'error');
                    if (otpInput) {
                        otpInput.focus();
                        otpInput.select();
                    }
                }
            })
            .catch(errorText => {
                console.error('Verify OTP Fetch Error:', errorText);
                verifyOtpButton.disabled = false;
                verifyOtpButton.textContent = readyLoginAjax.verify_otp_text;
                showUserMessage(typeof errorText === 'string' ? errorText : readyLoginAjax.error_general, 'error');
            });
        });
    }

    if(changePhoneLink && smsStep1Form && smsStep2Form) {
        changePhoneLink.addEventListener('click', function(e) {
            e.preventDefault();
            clearUserMessage();
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
    
    // ارسال با Enter در فیلدها
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
