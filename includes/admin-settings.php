<?php
// File: admin-settings.php
// این فایل مربوط به بخش تنظیمات پیشخوان (ادمین) افزونه Readysms است.
// شامل تنظیمات ورود با پیامک و گوگل، راهنماهای استفاده و بخش تست API (ارسال، وضعیت، قالب و موجودی) می‌باشد.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * بارگذاری استایل‌ها و اسکریپت‌های ادمین، به همراه Toastr برای اعلان‌های toast
 */
function ready_login_enqueue_admin_assets() { // Renamed function for clarity
    // Correct path for panel.css assuming 'assets' is in the plugin root
    wp_enqueue_style('readysms-admin-style', READYSMS_URL . 'assets/css/panel.css', array(), READYSMS_VERSION);
    wp_enqueue_style('toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', array(), '2.1.4');

    wp_enqueue_script('readysms-admin-js', READYSMS_URL . 'assets/js/admin-settings.js', array('jquery'), READYSMS_VERSION, true);
    wp_enqueue_script('toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', array('jquery'), '2.1.4', true);
    
    wp_localize_script('readysms-admin-js', 'readyLoginAdminAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('readysms-admin-nonce'), // Used by new AJAX handlers
        'send_test_otp_action' => 'ready_admin_send_test_otp',
        'verify_test_otp_action' => 'ready_admin_verify_test_otp',
        'check_status_action' => 'ready_admin_check_sms_status',
        'get_template_action' => 'ready_admin_get_template_info',
        'get_balance_action' => 'ready_admin_get_balance'
    ));
}
add_action('admin_enqueue_scripts', 'ready_login_enqueue_admin_assets');


/**
 * افزودن منوی اصلی و زیرمنوها در پیشخوان وردپرس
 */
function ready_login_add_admin_menu() {
    add_menu_page(
        'تنظیمات ردی اس‌ام‌اس',            // عنوان صفحه تنظیمات
        'ردی اس‌ام‌اس',                    // عنوان منو به فارسی
        'manage_options',
        'readysms-settings',
        'ready_login_render_dashboard',
        'dashicons-smartphone' // Changed icon
    );
    
    add_submenu_page(
        'readysms-settings',
        'تنظیمات ورود با گوگل',
        'ورود با گوگل',
        'manage_options',
        'readysms-google-settings',
        'ready_login_render_google_settings'
    );
    
    add_submenu_page(
        'readysms-settings',
        'تنظیمات پیامک',
        'ورود با پیامک',
        'manage_options',
        'readysms-sms-settings',
        'ready_login_render_sms_settings'
    );
     add_submenu_page( // Optional: A dedicated page for API testing if dashboard gets too crowded
        'readysms-settings',
        'تست API راه پیام',
        'تست API',
        'manage_options',
        'readysms-api-test',
        'ready_login_render_api_test_page' // New function to render this page
    );
}
add_action('admin_menu', 'ready_login_add_admin_menu');

/**
 * ثبت تنظیمات
 */
function ready_login_register_settings() {
    // تنظیمات پیامک (راه‌پیام)
    register_setting('readysms-sms-options', 'ready_sms_api_key', 'sanitize_text_field');
    register_setting('readysms-sms-options', 'ready_sms_number', 'sanitize_text_field');
    register_setting('readysms-sms-options', 'ready_sms_pattern_code', 'sanitize_text_field');
    register_setting('readysms-sms-options', 'ready_sms_timer_duration', 'absint'); // Add timer here as well

    // تنظیمات ورود با گوگل
    register_setting('ready-google-options', 'ready_google_client_id', 'sanitize_text_field');
    register_setting('ready-google-options', 'ready_google_client_secret', 'sanitize_text_field');
}
add_action('admin_init', 'ready_login_register_settings');

/**
 * نمایش نوتیفیکیشن پس از ذخیره تنظیمات
 */
function ready_login_admin_notices() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        // Check which page we are on to show relevant message or a general one
        $current_screen = get_current_screen();
        $message = __('تنظیمات با موفقیت ذخیره شد.', 'readysms');

        // Optionally, customize messages based on the settings page
        // if (strpos($current_screen->id, 'readysms-sms-settings') !== false) {
        //    $message = __('تنظیمات پیامک با موفقیت ذخیره شد.', 'readysms');
        // } elseif (strpos($current_screen->id, 'readysms-google-settings') !== false) {
        //    $message = __('تنظیمات گوگل با موفقیت ذخیره شد.', 'readysms');
        // }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
add_action('admin_notices', 'ready_login_admin_notices');

/**
 * صفحه داشبورد اصلی افزونه (پیشخوان)
 */
function ready_login_render_dashboard() {
    ?>
    <div class="wrap readysms-wrap">
        <div style="text-align:center; margin-bottom:20px;">
            <a href="https://blog.msgway.com/contact/" target="_blank">
                <img src="<?php echo esc_url(READYSMS_URL . 'assets/banner.jpg'); ?>" style="max-width:100%; height:auto; border-radius:12px;" alt="بنر ردی اس‌ام‌اس">
            </a>
        </div>

        <h1><?php esc_html_e('داشبورد ردی اس‌ام‌اس', 'readysms'); ?></h1>
        <div class="dokme-container" style="margin-bottom:20px;">
            <div class="dokme" style="display:inline-block; margin-right:10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>" class="button button-primary"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a>
            </div>
            <div class="dokme" style="display:inline-block; margin-right:10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>" class="button button-secondary"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a>
            </div>
             <div class="dokme" style="display:inline-block;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>" class="button button-secondary"><?php esc_html_e('تست API راه پیام', 'readysms'); ?></a>
            </div>
        </div>

        <h3><?php esc_html_e('راهنمای استفاده از پلاگین', 'readysms'); ?></h3>
        <div class="instruction-box" style="background:#f7f9fc; padding:15px; border:1px solid #d1e3f0; border-radius:8px; margin-bottom:20px;">
            <p><?php esc_html_e('این پلاگین به شما امکان می‌دهد تا ورود کاربران به وب‌سایت خود را از طریق پیامک (با سامانه راه پیام) و همچنین ورود با گوگل مدیریت کنید.', 'readysms'); ?></p>
            <ul style="list-style-type: disc; padding-right: 20px; line-height:1.8; margin:0;">
                <li><?php esc_html_e('برای ورود با پیامک: به صفحه تنظیمات پیامک رفته و کلید API، شماره ارسال (اختیاری) و کد پترن خود در سامانه راه پیام را وارد کنید.', 'readysms'); ?></li>
                <li><?php esc_html_e('برای ورود با گوگل: به صفحه تنظیمات گوگل رفته و Google Client ID و Client Secret را وارد نمایید.', 'readysms'); ?></li>
                <li><?php printf(wp_kses_post(__('جهت استفاده از فرم ورود، شورت کد %s را در برگه یا نوشته دلخواه خود قرار دهید.', 'readysms')), '<code>[readysms]</code>'); ?></li>
                <li><?php printf(wp_kses_post(__('برای راهنمایی بیشتر، به <a href="%s" target="_blank">مستندات افزونه</a> مراجعه کنید.', 'readysms')), esc_url('https://readystudio.ir/readysms-plugin/')); ?></li>
            </ul>
        </div>

        <h3><?php esc_html_e('راهنمای ورود با گوگل', 'readysms'); ?></h3>
        <div class="google-instruction" style="background:#fefbd8; padding:15px; border:1px solid #f5c76b; border-radius:8px; margin-bottom:20px;">
            <?php esc_html_e('توجه: برای استفاده از ورود با گوگل، داشتن هاست خارج از ایران توصیه می‌شود، زیرا سرویس‌های گوگل ممکن است برای کاربران ایرانی با محدودیت‌هایی مواجه باشند.', 'readysms'); ?>
            <br><br>
            <?php esc_html_e('در صورت بروز مشکل در نمایش دکمه ورود با گوگل، می‌توانید با افزودن کد CSS زیر به بخش سفارشی‌سازی قالب خود، آن را مخفی کنید:', 'readysms'); ?>
            <br>
            <code>.google-login-section { display:none !important; }</code>
        </div>

        <h3><?php esc_html_e('درباره سامانه راه پیام', 'readysms'); ?></h3>
        <div class="instruction-box" style="background:#fcf8e3; padding:15px; border:1px solid #faebcc; border-radius:8px; margin-bottom:20px;">
            <p><?php esc_html_e('سامانه راه پیام ابزاری قدرتمند برای ارسال پیامک‌های اعتبارسنجی (OTP) و اطلاع‌رسانی است.', 'readysms'); ?></p>
            <ul style="list-style-type: disc; padding-right: 20px; line-height:1.8; margin:0;">
                <li><?php esc_html_e('ارسال سریع و مطمئن پیامک‌های تایید (OTP).', 'readysms'); ?></li>
                <li><?php esc_html_e('امکان تعریف پترن‌های پیامکی برای ارسال‌های استاندارد.', 'readysms'); ?></li>
                <li><?php esc_html_e('ارائه API برای توسعه‌دهندگان جهت اتصال به سایر نرم‌افزارها.', 'readysms'); ?></li>
            </ul>
            <p><?php printf(wp_kses_post(__('جهت استفاده بهینه از این سامانه، لطفاً تنظیمات مربوط به API (کلید API، شماره ارسال و کد پترن) را به درستی در <a href="%s">صفحه تنظیمات پیامک</a> وارد نمایید و از بخش <a href="%s">تست API</a> از صحت عملکرد آن اطمینان حاصل کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-sms-settings')), esc_url(admin_url('admin.php?page=readysms-api-test'))); ?></p>
        </div>
    </div>
    <?php
}


/**
 * صفحه تنظیمات ورود با گوگل
 */
function ready_login_render_google_settings() {
    ?>
    <div class="wrap readysms-wrap">
        <h1><?php esc_html_e('تنظیمات ورود با گوگل', 'readysms'); ?></h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields('ready-google-options'); 
                do_settings_sections('ready-google-options'); // This function call is usually for pages added via add_settings_section
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_google_client_id"><?php esc_html_e('Google Client ID', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_google_client_id" name="ready_google_client_id" value="<?php echo esc_attr(get_option('ready_google_client_id')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_google_client_secret"><?php esc_html_e('Google Client Secret', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_google_client_secret" name="ready_google_client_secret" value="<?php echo esc_attr(get_option('ready_google_client_secret')); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            <p>
                <?php printf(wp_kses_post(__('درصورتی که نمی‌دانید چطور این پارامترها را پیدا کنید، <a href="%s" target="_blank">این راهنما را مطالعه کنید</a>.', 'readysms')), esc_url('https://readystudio.ir/register-with-google-account/')); ?>
            </p>
            <?php submit_button(__('ذخیره تنظیمات', 'readysms')); ?>
        </form>
    </div>
    <?php
}

/**
 * صفحه تنظیمات ورود با پیامک (Msgway) در پیشخوان وردپرس
 */
function ready_login_render_sms_settings() {
    ?>
    <div class="wrap readysms-wrap">
        <div style="text-align:center; margin-bottom:20px;">
             <a href="https://blog.msgway.com/contact/" target="_blank">
                <img src="<?php echo esc_url(READYSMS_URL . 'assets/banner.jpg'); ?>" style="max-width:100%; height:auto; border-radius:12px;" alt="بنر ردی اس‌ام‌اس">
            </a>
        </div>
        <h1><?php esc_html_e('تنظیمات ورود با پیامک (راه پیام)', 'readysms'); ?></h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms-sms-options'); 
                // do_settings_sections('readysms-sms-options'); // Only if you use add_settings_section
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('سامانه پیامکی', 'readysms'); ?></th>
                    <td><?php esc_html_e('راه پیام (Msgway.com)', 'readysms'); ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_api_key"><?php esc_html_e('کلید API (apiKey)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_api_key" name="ready_sms_api_key" value="<?php echo esc_attr(get_option('ready_sms_api_key')); ?>" class="regular-text" dir="ltr">
                         <p class="description"><?php esc_html_e('کلید API دریافت شده از پنل راه پیام.', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_number"><?php esc_html_e('شماره ارسال (lineNumber)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_number" name="ready_sms_number" value="<?php echo esc_attr(get_option('ready_sms_number')); ?>" class="regular-text" placeholder="<?php esc_attr_e('اختیاری', 'readysms'); ?>">
                        <p class="description"><?php esc_html_e('شماره خط اختصاصی شما در راه پیام. در صورت خالی رها کردن، از شماره پیش‌فرض سامانه استفاده می‌شود. (مثال: 3000xxxxx)', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_pattern_code"><?php esc_html_e('کد پترن (templateID)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_pattern_code" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('کد پترن تعریف شده در سامانه راه پیام برای ارسال OTP. پترن باید شامل یک پارامتر برای کد OTP باشد (مثال: کد تایید: %param1%).', 'readysms'); ?></p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="ready_sms_timer_duration"><?php esc_html_e('مدت زمان تایمر OTP (ثانیه)', 'readysms'); ?></label></th>
                    <td>
                        <input type="number" id="ready_sms_timer_duration" name="ready_sms_timer_duration" value="<?php echo esc_attr(get_option('ready_sms_timer_duration', 120)); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('مدت زمانی که کاربر باید برای درخواست مجدد کد OTP منتظر بماند.', 'readysms'); ?></p>
                    </td>
                </tr>
            </table>
            <p style="margin-top:15px;">
                 <?php printf(wp_kses_post(__('در صورتی که با نحوه کار این افزونه آشنا نیستید، <a href="%s" target="_blank">این آموزش را مطالعه کنید</a>.', 'readysms')), esc_url('https://readystudio.ir/readysms-plugin/')); ?>
                 <?php printf(wp_kses_post(__('همچنین، برای اطمینان از صحت تنظیمات، می‌توانید از بخش <a href="%s">تست API</a> استفاده کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-api-test'))); ?>
            </p>
            <?php submit_button(__('ذخیره تنظیمات پیامک', 'readysms')); ?>
        </form>
        
        <hr/>
        <h3><?php esc_html_e('توجه', 'readysms'); ?></h3>
        <p><?php esc_html_e('این افزونه از سامانه راه پیام برای ارسال پیامک استفاده می‌کند.', 'readysms'); ?></p>
        <div class="dokme">
            <a href="https://msgway.com/" target="_blank" class="button"><?php esc_html_e('وب‌سایت راه پیام', 'readysms'); ?></a>
        </div>
    </div>
    <?php
}

/**
 * صفحه تست API سامانه راه پیام
 */
function ready_login_render_api_test_page() {
    ?>
    <div class="wrap readysms-wrap">
        <h1><?php esc_html_e('بخش تست API سامانه راه پیام', 'readysms'); ?></h1>
        <p><?php esc_html_e('در این بخش می‌توانید عملکرد APIهای مختلف سامانه راه پیام را با استفاده از تنظیمات ذخیره شده، آزمایش کنید.', 'readysms'); ?></p>

        <div class="api-test-section postbox">
            <h2 class="hndle"><span><?php esc_html_e('1. تست ارسال پیامک تاییدی (OTP)', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('شماره تلفن خود را برای دریافت پیامک آزمایشی وارد کنید:', 'readysms'); ?></p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="test_phone_number_otp"><?php esc_html_e('شماره تلفن', 'readysms'); ?></label></th>
                        <td><input type="text" id="test_phone_number_otp" placeholder="<?php esc_attr_e('مثال: 09123456789', 'readysms'); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <button type="button" id="send_test_otp_button" class="button button-primary"><?php esc_html_e('ارسال پیامک آزمایشی', 'readysms'); ?></button>
                <div id="test_otp_verification_section" style="display: none; margin-top: 15px; padding-top:15px; border-top: 1px dashed #ccc;">
                    <p><?php esc_html_e('کد تایید دریافت شده را وارد کنید:', 'readysms'); ?></p>
                     <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="test_otp_code"><?php esc_html_e('کد تایید', 'readysms'); ?></label></th>
                            <td><input type="text" id="test_otp_code" placeholder="<?php esc_attr_e('کد 6 رقمی', 'readysms'); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                    <button type="button" id="verify_test_otp_button" class="button"><?php esc_html_e('بررسی کد تایید', 'readysms'); ?></button>
                </div>
                <div id="send_test_otp_result" class="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <div class="api-test-section postbox">
            <h2 class="hndle"><span><?php esc_html_e('2. تست دریافت وضعیت ارسال پیامک', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('شناسه مرجع پیامک (OTPReferenceID یا BatchID) را وارد کنید:', 'readysms'); ?></p>
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="status_reference_id"><?php esc_html_e('شناسه مرجع', 'readysms'); ?></label></th>
                        <td><input type="text" id="status_reference_id" placeholder="<?php esc_attr_e('مثال: MSG-XXXXXXXX', 'readysms'); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <button type="button" id="check_status_button" class="button"><?php esc_html_e('بررسی وضعیت', 'readysms'); ?></button>
                <div id="status_result" class="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <div class="api-test-section postbox">
            <h2 class="hndle"><span><?php esc_html_e('3. تست دریافت اطلاعات قالب پیام', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('شناسه قالب (Template ID) را وارد کنید (این شناسه باید با کد پترن تنظیم شده در افزونه یکسان باشد تا تست معنی‌دار باشد):', 'readysms'); ?></p>
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="template_id_test"><?php esc_html_e('شناسه قالب', 'readysms'); ?></label></th>
                        <td><input type="text" id="template_id_test" placeholder="<?php esc_attr_e('مثال: 1001', 'readysms'); ?>" class="regular-text" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>"></td>
                    </tr>
                </table>
                <button type="button" id="get_template_button" class="button"><?php esc_html_e('دریافت اطلاعات قالب', 'readysms'); ?></button>
                <div id="template_result" class="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <div class="api-test-section postbox">
            <h2 class="hndle"><span><?php esc_html_e('4. تست دریافت موجودی حساب', 'readysms'); ?></span></h2>
            <div class="inside">
                <button type="button" id="get_balance_button" class="button"><?php esc_html_e('دریافت موجودی', 'readysms'); ?></button>
                <div id="balance_result" class="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
    </div>
    <style>
        .readysms-wrap .postbox { margin-bottom: 20px; }
        .readysms-wrap .api-test-result { padding: 10px; border: 1px solid #eee; background: #f9f9f9; margin-top:10px; max-height: 200px; overflow-y: auto; direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-all; }
        .readysms-wrap .api-test-result.success { border-left: 3px solid green; }
        .readysms-wrap .api-test-result.error { border-left: 3px solid red; }
    </style>
    <?php
}


/**
 * افزودن متن "Powered by ReadyStudio" به صورت مینیمال در پایین تمامی صفحات پیشخوان
 */
function ready_login_render_power_by() {
    echo '<div class="power-by-readystudio" style="position: fixed; bottom: 5px; right: 5px; font-size: 12px; color: #888; z-index: 9999; background: #f0f0f1; padding: 2px 5px; border-radius:3px;">
            <a href="https://readystudio.ir/" target="_blank" style="color: #555; text-decoration: none;">Powered by ReadyStudio</a>
          </div>';
}
add_action('admin_footer', 'ready_login_render_power_by');
?>
