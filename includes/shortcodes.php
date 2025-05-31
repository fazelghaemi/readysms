<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

function ready_login_form_shortcode($atts) {
    $attributes = shortcode_atts([
        'link' => home_url(), // Default redirect link after successful login
        'lostpassword_url' => wp_lostpassword_url(), // Standard WordPress lost password URL
        'register_url' => wp_registration_url() // Standard WordPress registration URL
    ], $atts);

    // Check if user is already logged in
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $logout_url = wp_logout_url(get_permalink()); // Logout and redirect to current page
         return '<div class="readysms-logged-in-message">' .
            sprintf(esc_html__('سلام %s، شما وارد شده‌اید.', 'readysms'), esc_html($current_user->display_name)) .
            ' <a href="' . esc_url($attributes['link']) . '">' . esc_html__('رفتن به صفحه اصلی', 'readysms') . '</a> | ' .
            '<a href="' . esc_url($logout_url) . '">' . esc_html__('خروج', 'readysms') . '</a>'.
            '</div>';
    }

    ob_start(); ?>
    <div id="readysms-form-container" class="readysms-form-wrapper" data-redirect-link="<?php echo esc_url($attributes['link']); ?>">
        <div id="readysms-sms-login-section" class="readysms-section">
            <h3><?php esc_html_e('ورود یا ثبت نام با پیامک', 'readysms'); ?></h3>
            <form id="readysms-sms-login-form">
                <div class="readysms-form-field">
                    <label for="readysms-phone-number"><?php esc_html_e('شماره موبایل', 'readysms'); ?></label>
                    <input type="text" id="readysms-phone-number" name="phone_number" placeholder="<?php esc_attr_e('مثال: 09123456789', 'readysms'); ?>" required dir="ltr">
                </div>
                <button type="button" id="readysms-send-otp-button"><?php esc_html_e('ارسال کد تایید', 'readysms'); ?></button>
                <p id="readysms-timer-display" style="display:none; color: #e74c3c; margin-top:10px;"><?php esc_html_e('زمان باقی‌مانده برای ارسال مجدد: ', 'readysms'); ?><span id="readysms-remaining-time"></span> <?php esc_html_e('ثانیه', 'readysms'); ?></p>
            </form>
            <form id="readysms-verify-otp-form" style="display:none;">
                 <div class="readysms-form-field">
                    <label for="readysms-otp-code"><?php esc_html_e('کد تایید', 'readysms'); ?></label>
                    <input type="text" id="readysms-otp-code" name="otp_code" placeholder="<?php esc_attr_e('کد دریافت شده را وارد کنید', 'readysms'); ?>" required dir="ltr">
                </div>
                <button type="button" id="readysms-verify-otp-button"><?php esc_html_e('ورود / ثبت نام', 'readysms'); ?></button>
            </form>
            <div id="readysms-message-area" class="readysms-message" style="margin-top:15px;"></div>
        </div>

        <?php if (get_option('ready_google_client_id') && get_option('ready_google_client_secret')): ?>
        <div class="readysms-separator"><span><?php esc_html_e('یا', 'readysms'); ?></span></div>
        <div id="readysms-google-login-section" class="readysms-section">     
            <a href="<?php echo esc_url(ready_generate_google_login_url($attributes['link'])); ?>" class="readysms-google-login-button">
                <img src="<?php echo esc_url(READYSMS_URL . 'assets/google-logo.png'); ?>" alt="Google logo" style="width:20px; height:20px; vertical-align:middle; margin-left:8px;">
                <?php esc_html_e('ورود با گوگل', 'readysms'); ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="readysms-links" style="margin-top: 20px; font-size: 0.9em; text-align: center;">
            <a href="<?php echo esc_url($attributes['lostpassword_url']); ?>"><?php esc_html_e('رمز عبور خود را فراموش کرده‌اید؟', 'readysms'); ?></a>
            <?php if (get_option('users_can_register')) : ?>
                | <a href="<?php echo esc_url($attributes['register_url']); ?>"><?php esc_html_e('ثبت نام با ایمیل', 'readysms'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('readysms', 'ready_login_form_shortcode');

// Ensure the google login function is available
if (!function_exists('ready_generate_google_login_url')) {
    include_once READYSMS_DIR . 'includes/google-login.php';
}

?>
