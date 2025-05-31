<?php
// File: includes/shortcodes.php

if (!defined('ABSPATH')) {
    exit;
}

function readysms_login_form_shortcode($atts, $content = null) {
    $attributes = shortcode_atts([
        'redirect_to'      => '', 
        'show_google'      => 'yes', 
        'show_sms'         => 'yes',
        // پارامترهای lostpassword_url و register_url حذف شدند تا با مورد 6 هماهنگ باشند
    ], $atts, 'readysms_login_form');

    // تعیین لینک بازگشت نهایی
    // اولویت با تنظیمات ادمین، سپس پارامتر شورت‌کد، سپس صفحه فعلی
    $admin_redirect_after_login = get_option('ready_redirect_after_login');
    $admin_redirect_after_register = get_option('ready_redirect_after_register'); // ممکن است بخواهید برای ثبت‌نام لینک متفاوتی داشته باشید

    $final_redirect_url = home_url(add_query_arg(null, null)); // پیش‌فرض: صفحه فعلی

    if (!empty($attributes['redirect_to'])) {
        $final_redirect_url = esc_url($attributes['redirect_to']);
    } elseif (!empty($admin_redirect_after_login)) { // اگر پارامتر شورت‌کد خالی بود، از تنظیمات ادمین برای لاگین استفاده کن
        $final_redirect_url = esc_url($admin_redirect_after_login);
    }
    // برای ثبت‌نام، اگر لینک مجزا تنظیم شده بود از آن استفاده می‌کنیم، وگرنه همان لینک لاگین
    $final_register_redirect_url = !empty($admin_redirect_after_register) ? esc_url($admin_redirect_after_register) : $final_redirect_url;


    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        // استفاده از لینک "حساب کاربری من" از تنظیمات اگر موجود باشد، در غیر این صورت لینک بازگشت پیش‌فرض
        $my_account_link = get_option('ready_redirect_my_account_link');
        $dashboard_link = !empty($my_account_link) ? esc_url($my_account_link) : $final_redirect_url;
        
        $logout_url = wp_logout_url($final_redirect_url); // پس از خروج به کجا برود (از تنظیمات افزونه خوانده خواهد شد توسط هوک)
        
         return '<div class="readysms-form-container readysms-logged-in-message">' .
            sprintf(esc_html__('سلام %s، شما با موفقیت وارد شده‌اید.', 'readysms'), '<strong>' . esc_html($current_user->display_name) . '</strong>') .
            '<p><a href="' . esc_url($dashboard_link) . '">' . esc_html__('رفتن به حساب کاربری', 'readysms') . '</a> | ' .
            '<a href="' . esc_url($logout_url) . '">' . esc_html__('خروج از حساب کاربری', 'readysms') . '</a></p>'.
            '</div>';
    }

    ob_start(); 
    ?>
    <div id="readysms-form-container" class="readysms-form-wrapper" 
         data-redirect-url="<?php echo $final_redirect_url; ?>" 
         data-register-redirect-url="<?php echo $final_register_redirect_url; ?>">
        
        <?php
        // تغییر 2: نمایش لوگوی سایت
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
        $google_client_id = get_option('ready_google_client_id');
        $google_client_secret = get_option('ready_google_client_secret');
        if (strtolower($attributes['show_google']) === 'yes' && !empty($google_client_id) && !empty($google_client_secret)): 
        ?>
            <?php if (strtolower($attributes['show_sms']) === 'yes'): ?>
            <div class="readysms-separator"><span><?php esc_html_e('یا', 'readysms'); ?></span></div>
            <?php endif; ?>

            <div id="readysms-google-login-section" class="readysms-section">     
                <a href="<?php echo esc_url(ready_generate_google_login_url($final_redirect_url)); ?>" class="readysms-button readysms-google-login-button">
                    <img src="<?php echo esc_url(READYSMS_URL . 'assets/google-logo.png'); ?>" alt="Google" style="width:18px; height:18px; vertical-align:middle; margin-left:8px; margin-right: -5px;">
                    <?php esc_html_e('ورود با حساب گوگل', 'readysms'); ?>
                </a>
            </div>
        <?php endif; ?>

        <?php
        // تغییر 6: حذف لینک‌های پیش‌فرض وردپرس
        // این شورت‌کد به خودی خود این لینک‌ها را اضافه نمی‌کند.
        // اگر این لینک‌ها توسط پوسته یا افزونه دیگری در صفحه‌ای که شورت‌کد قرار دارد، نمایش داده می‌شوند،
        // باید از طریق تنظیمات پوسته یا CSS سفارشی آن‌ها را مخفی کنید.
        // مثال CSS برای مخفی کردن لینک‌های استاندارد فرم ورود وردپرس (اگر در همان صفحه باشند):
        // .login #nav, .login #backtoblog { display: none !important; }
        // این CSS باید در فایل استایل پوسته شما یا بخش CSS سفارشی وردپرس اضافه شود.
        ?>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('readysms_login_form', 'readysms_login_form_shortcode');

?>
