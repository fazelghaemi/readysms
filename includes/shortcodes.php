<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

function ready_login_form_shortcode($atts) {
    $attributes = shortcode_atts(['link' => home_url()], $atts);
    ob_start(); ?>
    <div id="readysms-form" data-redirect-link="<?php echo esc_url($attributes['link']); ?>">
        <div id="sms-login-section">
            <h3>ورود با پیامک</h3>
            <form id="sms-login-form">
                <input type="text" id="phone-number" name="phone_number" placeholder="شماره تلفن" required>
                <button type="button" id="send-otp">ارسال کد</button>
                <p id="timer-display" style="display:none;">زمان باقی‌مانده: <span id="remaining-time"></span> ثانیه</p>
            </form>
            <form id="verify-otp-form" style="display:none;">
                <input type="text" id="otp-code" name="otp_code" placeholder="کد تایید" required>
                <button type="button" id="verify-otp">ورود</button>
                <p id="otp-error" style="display:none; color: red;">کد تایید نادرست است.</p>
            </form>
        </div>
        <div id="google-login-section">     
            <a href="<?php echo esc_url(ready_generate_google_login_url($attributes['link'])); ?>" class="google-login-button">ورود با گوگل</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('readysms', 'ready_login_form_shortcode');