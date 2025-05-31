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

    sendOtpButton.addEventListener('click', function () {
        const phoneNumber = phoneNumberInput.value.trim();
        if (phoneNumber) {
            fetch(ezLoginAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ez_sms_send_otp',
                    phone_number: phoneNumber,
                    nonce: ezLoginAjax.nonce,
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
        } else {
            alert('لطفاً شماره تلفن را وارد کنید.');
        }
    });

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

    verifyOtpButton.addEventListener('click', function () {
        const phoneNumber = phoneNumberInput.value.trim();
        const otpCode = otpInput.value.trim();
        if (otpCode) {
            fetch(ezLoginAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ez_sms_verify_otp',
                    phone_number: phoneNumber,
                    otp_code: otpCode,
                    nonce: ezLoginAjax.nonce,
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
        } else {
            alert('لطفاً کد تایید را وارد کنید.');
        }
    });
});