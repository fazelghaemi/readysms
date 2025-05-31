// File: readysms/assets/js/custom-login.js
// این فایل مربوط به بخش فرانت سایت برای ورود با پیامک است.
// در این فایل اقدامات ارسال و تایید OTP با استفاده از API های راه پیام به‌روز شده‌اند.

document.addEventListener('DOMContentLoaded', function () {
    const smsLoginForm = document.getElementById('sms-login-form');
    const verifyOtpForm = document.getElementById('verify-otp-form');
    const sendOtpButton = document.getElementById('send-otp');
    const verifyOtpButton = document.getElementById('verify-otp');
    const phoneNumberInput = document.getElementById('phone-number');
    const otpInput = document.getElementById('otp-code');
    const otpError = document.getElementById('otp-error');
    const formContainer = document.getElementById('ez-login-form');
    const redirectLink = formContainer.dataset.redirectLink;
    const timerDisplay = document.getElementById('timer-display');
    const remainingTimeSpan = document.getElementById('remaining-time');
    let timerInterval;

    // ارسال درخواست OTP با استفاده از API SEND
    sendOtpButton.addEventListener('click', function () {
        const phoneNumber = phoneNumberInput.value.trim();
        if (!phoneNumber) {
            alert('لطفاً شماره تلفن را وارد کنید.');
            return;
        }

        fetch(readyLoginAjax.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'ready_sms_send_otp',
                phone_number: phoneNumber,
                nonce: readyLoginAjax.nonce,
            }),
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                if (data.success) {
                    alert(data.data.message);
                    startTimer(data.data.remaining_time);
                    smsLoginForm.style.display = 'none';
                    verifyOtpForm.style.display = 'block';
                } else {
                    alert(data.data);
                }
            })
            .catch(() => alert('خطا در ارتباط با سرور.'));
    });

    // راه‌اندازی تایمر جهت ارسال مجدد OTP
    function startTimer(duration) {
        let remainingTime = duration;
        sendOtpButton.disabled = true;
        timerDisplay.style.display = 'block';
        remainingTimeSpan.textContent = remainingTime;

        timerInterval = setInterval(() => {
            remainingTime--;
            remainingTimeSpan.textContent = remainingTime;
            if (remainingTime <= 0) {
                clearInterval(timerInterval);
                sendOtpButton.disabled = false;
                timerDisplay.style.display = 'none';
            }
        }, 1000);
    }

    // تایید OTP با استفاده از API Verify
    verifyOtpButton.addEventListener('click', function () {
        const phoneNumber = phoneNumberInput.value.trim();
        const otpCode = otpInput.value.trim();
        if (!otpCode) {
            alert('لطفاً کد تایید را وارد کنید.');
            return;
        }

        fetch(readyLoginAjax.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'ready_sms_verify_otp',
                phone_number: phoneNumber,
                otp_code: otpCode,
                nonce: readyLoginAjax.nonce,
                redirect_link: redirectLink,
            }),
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.data;
                } else {
                    otpError.style.display = 'block';
                    otpError.textContent = data.data;
                }
            })
            .catch(() => alert('خطا در ارتباط با سرور.'));
    });
});