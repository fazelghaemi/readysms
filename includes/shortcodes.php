<?php
// File: includes/shortcodes.php

if (!defined('ABSPATH')) {
    exit;
}

function readysms_login_form_shortcode($atts, $content = null) {
    $attributes = shortcode_atts([
        'redirect_to'      => '', // Custom redirect URL after successful login
        'lostpassword_url' => wp_lostpassword_url(),
        'register_url'     => wp_registration_url(),
        'show_google'      => 'yes', // 'yes' or 'no'
        'show_sms'         => 'yes', // 'yes' or 'no'
    ], $atts, 'readysms_login_form');

    // If redirect_to is not set or empty, use current page URL
    $final_redirect_url = !empty($attributes['redirect_to']) ? esc_url($attributes['redirect_to']) : esc_url(home_url(add_query_arg(null, null)));


    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $logout_url = wp_logout_url($final_redirect_url);
         return '<div class="readysms-form-container readysms-logged-in-message">' .
            sprintf(esc_html__('سلام %s، شما با موفقیت وارد شده‌اید.', 'readysms'), '<strong>' . esc_html($current_user->display_name) . '</strong>') .
            '<p><a href="' . esc_url($final_redirect_url) . '">' . esc_html__('ادامه', 'readysms') . '</a> | ' .
            '<a href="' . esc_url($logout_url) . '">' . esc_html__('خروج از حساب کاربری', 'readysms') . '</a></p>'.
            '</div>';
    }

    ob_start(); 
    ?>
    <div id="readysms-form-container" class="readysms-form-wrapper" data-redirect-url="<?php echo $final_redirect_url; ?>">
        
        <?php if (strtolower($attributes['show_sms']) === 'yes'): ?>
        <div id="readysms-sms-login-section" class="readysms-section">
            <h3><?php esc_html_e('ورود یا ثبت نام با شماره موبایل', 'readysms'); ?></h3>
            
            <div id="readysms-sms-step1-form">
                <div class="readysms-form-field">
                    <label for="readysms-phone-number"><?php esc_html_e('شماره موبایل خود را وارد کنید:', 'readysms'); ?></label>
                    <input type="tel" id="readysms-phone-number" name="phone_number" placeholder="<?php esc_attr_e('مثال: 09123456789', 'readysms'); ?>" required dir="ltr" autocomplete="tel">
                </div>
                <button type="button" id="readysms-send-otp-button" class="readysms-button readysms-button-primary"><?php esc_html_e('دریافت کد تایید', 'readysms'); ?></button>
                <p id="readysms-timer-display" style="display:none; color: #e74c3c; margin-top:10px; font-size:0.9em;">
                    <?php esc_html_e('ارسال مجدد کد تا: ', 'readysms'); ?><span id="readysms-remaining-time"></span> <?php esc_html_e('ثانیه دیگر', 'readysms'); ?>
                </p>
            </div>

            <div id="readysms-sms-step2-form" style="display:none;">
                 <div class="readysms-form-field">
                    <label for="readysms-otp-code"><?php esc_html_e('کد تایید ۶ رقمی ارسال شده را وارد کنید:', 'readysms'); ?></label>
                    <input type="text" id="readysms-otp-code" name="otp_code" placeholder="<?php esc_attr_e('کد تایید', 'readysms'); ?>" required dir="ltr" autocomplete="one-time-code" inputmode="numeric" maxlength="6">
                </div>
                <button type="button" id="readysms-verify-otp-button" class="readysms-button readysms-button-primary"><?php esc_html_e('ورود / ثبت نام', 'readysms'); ?></button>
                <p><a href="#" id="readysms-change-phone-link" style="font-size:0.9em;"><?php esc_html_e('تغییر شماره موبایل', 'readysms'); ?></a></p>
            </div>
            <div id="readysms-message-area" class="readysms-message" style="margin-top:15px; display:none;"></div>
        </div>
        <?php endif; ?>


        <?php 
        $google_client_id = get_option('ready_google_client_id');
        $google_client_secret = get_option('ready_google_client_secret');
        if (strtolower($attributes['show_google']) === 'yes' && !empty($google_client_id) && !empty($google_client_secret)): 
        ?>
            <?php if (strtolower($attributes['show_sms']) === 'yes'): // Show separator only if SMS login is also shown ?>
            <div class="readysms-separator"><span><?php esc_html_e('یا', 'readysms'); ?></span></div>
            <?php endif; ?>

            <div id="readysms-google-login-section" class="readysms-section">     
                <a href="<?php echo esc_url(ready_generate_google_login_url($final_redirect_url)); ?>" class="readysms-button readysms-google-login-button">
                    <img src="<?php echo esc_url(READYSMS_URL . 'assets/google-logo.png'); ?>" alt="Google" style="width:18px; height:18px; vertical-align:middle; margin-left:8px; margin-right: -5px;">
                    <?php esc_html_e('ورود با حساب گوگل', 'readysms'); ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if (strtolower($attributes['show_sms']) === 'yes' || (strtolower($attributes['show_google']) === 'yes' && !empty($google_client_id) && !empty($google_client_secret))): ?>
        <div class="readysms-links" style="margin-top: 20px; font-size: 0.9em; text-align: center; padding-top:10px; border-top:1px solid #eee;">
            <?php if (get_option('users_can_register')) : ?>
                <a href="<?php echo esc_url($attributes['register_url']); ?>"><?php esc_html_e('ثبت نام با ایمیل و رمز عبور', 'readysms'); ?></a> | 
            <?php endif; ?>
            <a href="<?php echo esc_url(wp_login_url($final_redirect_url)); ?>"><?php esc_html_e('ورود با نام کاربری و رمز عبور', 'readysms'); ?></a>
             <br>
            <a href="<?php echo esc_url($attributes['lostpassword_url']); ?>"><?php esc_html_e('رمز عبور خود را فراموش کرده‌اید؟', 'readysms'); ?></a>
        </div>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('readysms_login_form', 'readysms_login_form_shortcode'); // Changed shortcode name for clarity
?>
