<?php
// File: includes/shortcodes.php

if (!defined('ABSPATH')) {
    exit;
}

function readysms_login_form_shortcode($atts, $content = null) {
    $attributes = shortcode_atts([
        'redirect_to'      => '',    // پارامتر شورت‌کد برای تغییر مسیر
        'show_google'      => get_option('ready_google_client_id') && get_option('ready_google_client_secret') ? 'yes' : 'no', // پیش‌فرض بر اساس تنظیمات گوگل
        'show_sms'         => 'yes', // پیش‌فرض نمایش بخش پیامک
    ], $atts, 'readysms_login_form');

    // تعیین لینک بازگشت نهایی برای ورود
    $final_redirect_url_after_login = home_url('/'); // پیش‌فرض نهایی اگر هیچکدام تنظیم نشده باشند
    $admin_redirect_after_login = get_option('ready_redirect_after_login');
    if (!empty($attributes['redirect_to'])) { // اولویت اول با پارامتر شورت‌کد
        $final_redirect_url_after_login = esc_url($attributes['redirect_to']);
    } elseif (!empty($admin_redirect_after_login)) { // اولویت دوم با تنظیمات ادمین
        $final_redirect_url_after_login = esc_url($admin_redirect_after_login);
    }

    // تعیین لینک بازگشت نهایی برای ثبت‌نام
    $final_redirect_url_after_register = $final_redirect_url_after_login; // پیش‌فرض: مشابه لینک ورود
    $admin_redirect_after_register = get_option('ready_redirect_after_register');
    if (!empty($admin_redirect_after_register)) { // اگر تنظیمات ادمین برای ثبت‌نام متفاوت بود
        $final_redirect_url_after_register = esc_url($admin_redirect_after_register);
    }
    // اگر پارامتر شورت‌کد redirect_to برای ثبت‌نام هم معنی می‌دهد (معمولاً خیر، پس جداگانه بررسی نمی‌کنیم)


    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $my_account_link_from_settings = get_option('ready_redirect_my_account_link');
        // اگر مدیر لینک "حساب کاربری من" را تنظیم کرده، از آن استفاده کن، در غیر این صورت به صفحه اصلی یا ریدایرکت لاگین برو
        $dashboard_or_my_account_link = !empty($my_account_link_from_settings) ? esc_url($my_account_link_from_settings) : $final_redirect_url_after_login;
        
        // لینک خروج از تنظیمات خوانده می‌شود و توسط هوک logout_redirect مدیریت می‌شود
        $logout_url = wp_logout_url($final_redirect_url_after_login); 
        
         return '<div class="readysms-form-container readysms-logged-in-message">' .
            sprintf(esc_html__('سلام %s، شما با موفقیت وارد شده‌اید.', 'readysms'), '<strong>' . esc_html($current_user->display_name) . '</strong>') .
            '<p><a href="' . esc_url($dashboard_or_my_account_link) . '">' . esc_html__('رفتن به حساب کاربری', 'readysms') . '</a> | ' .
            '<a href="' . esc_url($logout_url) . '">' . esc_html__('خروج از حساب کاربری', 'readysms') . '</a></p>'.
            '</div>';
    }

    ob_start(); 
    ?>
    <div id="readysms-form-container" class="readysms-form-wrapper" 
         data-redirect-url="<?php echo esc_url($final_redirect_url_after_login); ?>" 
         data-register-redirect-url="<?php echo esc_url($final_redirect_url_after_register); ?>">
        
        <?php
        // تغییر 2: نمایش لوگوی سایت از تنظیمات
        $form_logo_url = get_option('ready_form_logo_url');
        if (!empty($form_logo_url)) :
        ?>
            <div class="readysms-form-logo">
                <img src="<?php echo esc_url($form_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?> <?php esc_attr_e('لوگو', 'readysms'); ?>">
            </div>
        <?php endif; ?>

        <?php if (strtolower($attributes['show_sms']) === 'yes'): ?>
        <div id="readysms-sms-login-section" class="readysms-section">
            <h3><?php esc_html_e('ورود یا ثبت نام با شماره موبایل', 'readysms'); ?></h3>
            
            <div id="readysms-sms-step1-form">
                <div class="readysms-form-field">
                    <label for="readysms-phone-number"><?php esc_html_e('شماره موبایل خود را وارد کنید:', 'readysms'); ?></label>
                    <input type="tel" id="readysms-phone-number" name="phone_number" placeholder="<?php esc_attr_e('مثال: 09123456789 یا +989123456789', 'readysms'); ?>" required dir="ltr" autocomplete="tel">
                </div>
                <button type="button" id="readysms-send-otp-button" class="readysms-button readysms-button-primary"><?php esc_html_e('دریافت کد تایید', 'readysms'); ?></button>
                <p id="readysms-timer-display" style="display:none; color: #e74c3c; margin-top:10px; font-size:0.9em;">
                    <?php esc_html_e('ارسال مجدد کد تا: ', 'readysms'); ?><span id="readysms-remaining-time"></span> <?php esc_html_e('ثانیه دیگر', 'readysms'); ?>
                </p>
            </div>

            <div id="readysms-sms-step2-form" style="display:none;">
                 <div class="readysms-form-field">
                    <label for="readysms-otp-code"><?php esc_html_e('کد تایید ارسال شده را وارد کنید:', 'readysms'); ?></label>
                    <input type="text" id="readysms-otp-code" name="otp_code" placeholder="<?php esc_attr_e('کد تایید', 'readysms'); // Placeholder توسط JS به‌روز می‌شود ?>" required dir="ltr" autocomplete="one-time-code" inputmode="numeric">
                </div>
                <button type="button" id="readysms-verify-otp-button" class="readysms-button readysms-button-primary"><?php esc_html_e('ورود / ثبت نام', 'readysms'); ?></button>
                <p><a href="#" id="readysms-change-phone-link" style="font-size:0.9em;"><?php esc_html_e('تغییر شماره موبایل یا ارسال مجدد', 'readysms'); ?></a></p>
            </div>
            <div id="readysms-message-area" class="readysms-message" style="margin-top:15px; display:none;"></div>
        </div>
        <?php endif; ?>


        <?php 
        // نمایش دکمه گوگل فقط اگر show_google="yes" باشد و Client ID و Secret تنظیم شده باشند
        if (strtolower($attributes['show_google']) === 'yes'):
            $google_client_id = get_option('ready_google_client_id');
            $google_client_secret = get_option('ready_google_client_secret');
            if (!empty($google_client_id) && !empty($google_client_secret)): 
            ?>
                <?php if (strtolower($attributes['show_sms']) === 'yes'): // نمایش جداکننده فقط اگر بخش پیامک هم فعال است ?>
                <div class="readysms-separator"><span><?php esc_html_e('یا', 'readysms'); ?></span></div>
                <?php endif; ?>

                <div id="readysms-google-login-section" class="readysms-section">     
                    <a href="<?php echo esc_url(ready_generate_google_login_url($final_redirect_url_after_login)); ?>" class="readysms-button readysms-google-login-button">
                        <img src="<?php echo esc_url(READYSMS_URL . 'assets/google-logo.png'); ?>" alt="Google" style="width:18px; height:18px; vertical-align:middle; margin-left:8px; margin-right: -5px;">
                        <?php esc_html_e('ورود با حساب گوگل', 'readysms'); ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        // تغییر 6: عدم نمایش لینک‌های پیش‌فرض ورود وردپرس توسط این شورت‌کد
        // این شورت‌کد به طور پیش‌فرض این لینک‌ها را اضافه نمی‌کند.
        // اگر در صفحه‌ای که این شورت‌کد استفاده می‌شود، این لینک‌ها توسط پوسته یا افزونه دیگری نمایش داده می‌شوند،
        // مدیر سایت باید از طریق تنظیمات پوسته یا CSS سفارشی آن‌ها را مدیریت کند.
        // مثال CSS برای مخفی کردن لینک‌های استاندارد فرم ورود وردپرس (اگر در همان صفحه باشند):
        // /* .login #nav, .login #backtoblog { display: none !important; } */
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('readysms_login_form', 'readysms_login_form_shortcode');

?>
