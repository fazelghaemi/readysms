<?php
// File: includes/admin-settings.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin scripts and styles.
 */
function readysms_enqueue_admin_assets($hook_suffix) {
    // Only load assets on our plugin's admin pages
    $plugin_pages = [
        'toplevel_page_readysms-settings',
        'ردی-اس‌ام‌اس_page_readysms-google-settings', // Slug can change based on menu title
        'ردی-اس‌ام‌اس_page_readysms-sms-settings',
        'ردی-اس‌ام‌اس_page_readysms-api-test',
    ];
    // A more robust way to get page hooks:
    // After add_menu_page, the hook is returned. Store it and check against it.
    // For now, let's assume the slugs above are correct or use a simpler check.

    // A general check if the page hook belongs to readysms
    if (strpos($hook_suffix, 'readysms-') === false && $hook_suffix !== 'toplevel_page_readysms-settings' ) {
       // Alternative for non-english slugs, check query param 'page'
        if (!isset($_GET['page']) || strpos($_GET['page'], 'readysms-') === false) {
            return;
        }
    }


    wp_enqueue_style('readysms-admin-panel-style', READYSMS_URL . 'assets/css/panel.css', array(), READYSMS_VERSION);
    wp_enqueue_style('toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', array(), '2.1.4');

    wp_enqueue_script('readysms-admin-panel-js', READYSMS_URL . 'assets/js/admin-settings.js', array('jquery'), READYSMS_VERSION, true);
    wp_enqueue_script('toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', array('jquery'), '2.1.4', true);
    
    wp_localize_script('readysms-admin-panel-js', 'readyLoginAdminAjax', array(
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'nonce'                   => wp_create_nonce('readysms-admin-nonce'),
        'send_test_otp_action'    => 'ready_admin_send_test_otp',
        'verify_test_otp_action'  => 'ready_admin_verify_test_otp',
        'check_status_action'     => 'ready_admin_check_sms_status',
        'get_template_action'     => 'ready_admin_get_template_info',
        'get_balance_action'      => 'ready_admin_get_balance',
        'msg_fill_phone'          => __('لطفا شماره تلفن را برای تست وارد کنید.', 'readysms'),
        'msg_fill_otp'            => __('لطفا کد OTP دریافتی را وارد کنید.', 'readysms'),
        'msg_fill_ref_id'         => __('لطفا شناسه مرجع را وارد کنید.', 'readysms'),
        'msg_fill_template_id'    => __('لطفا شناسه قالب را وارد کنید.', 'readysms'),
        'msg_unexpected_error'    => __('یک خطای پیش‌بینی نشده رخ داد. کنسول را بررسی کنید.', 'readysms'),
    ));
}
add_action('admin_enqueue_scripts', 'readysms_enqueue_admin_assets');


/**
 * Add admin menus.
 */
function readysms_add_admin_menu() {
    add_menu_page(
        __('تنظیمات ردی اس‌ام‌اس', 'readysms'),
        __('ردی اس‌ام‌اس', 'readysms'),
        'manage_options',
        'readysms-settings', // Main slug
        'readysms_render_dashboard_page',
        'dashicons-smartphone'
    );
    
    add_submenu_page(
        'readysms-settings', // Parent slug
        __('تنظیمات پیامک', 'readysms'),
        __('تنظیمات پیامک', 'readysms'),
        'manage_options',
        'readysms-sms-settings', // Submenu slug
        'readysms_render_sms_settings_page'
    );

    add_submenu_page(
        'readysms-settings',
        __('تنظیمات ورود با گوگل', 'readysms'),
        __('ورود با گوگل', 'readysms'),
        'manage_options',
        'readysms-google-settings',
        'readysms_render_google_settings_page'
    );
    
    add_submenu_page(
        'readysms-settings',
        __('تست API راه پیام', 'readysms'),
        __('تست API', 'readysms'),
        'manage_options',
        'readysms-api-test',
        'readysms_render_api_test_page'
    );
}
add_action('admin_menu', 'readysms_add_admin_menu');

/**
 * Register plugin settings.
 */
function readysms_register_settings() {
    // SMS Settings
    register_setting('readysms_sms_options_group', 'ready_sms_api_key', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_sms_options_group', 'ready_sms_number', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_sms_options_group', 'ready_sms_pattern_code', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_sms_options_group', 'ready_sms_timer_duration', ['sanitize_callback' => 'absint', 'default' => 120]);

    // Google Settings
    register_setting('readysms_google_options_group', 'ready_google_client_id', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_google_options_group', 'ready_google_client_secret', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
}
add_action('admin_init', 'readysms_register_settings');

/**
 * Display admin notices for settings updates.
 */
function readysms_admin_notices() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        $current_screen = get_current_screen();
        // Check if we are on one of our plugin's settings pages.
        if ($current_screen && strpos($current_screen->id, 'readysms-') !== false) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('تنظیمات با موفقیت ذخیره شد.', 'readysms') . '</p></div>';
        }
    }
}
add_action('admin_notices', 'readysms_admin_notices');


/**
 * Render the main dashboard page.
 */
function readysms_render_dashboard_page() {
    ?>
    <div class="wrap readysms-wrap">
        <div style="text-align:center; margin-bottom:20px;">
            <a href="https://readystudio.ir/readysms-plugin/" target="_blank">
                <img src="<?php echo esc_url(READYSMS_URL . 'assets/banner.jpg'); ?>" style="max-width:100%; height:auto; border-radius:12px;" alt="<?php esc_attr_e('بنر ردی اس‌ام‌اس', 'readysms'); ?>">
            </a>
        </div>

        <h1><?php esc_html_e('داشبورد ردی اس‌ام‌اس', 'readysms'); ?></h1>
        <p><?php esc_html_e('به پنل مدیریت افزونه ورود و ثبت نام با پیامک و گوگل خوش آمدید.', 'readysms'); ?></p>

        <div class="dokme-container" style="margin:20px 0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>" class="dokme page-title-action"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>" class="dokme page-title-action"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>" class="dokme page-title-action"><?php esc_html_e('تست API راه پیام', 'readysms'); ?></a>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('راهنمای استفاده از پلاگین', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('این پلاگین به شما امکان می‌دهد تا ورود کاربران به وب‌سایت خود را از طریق پیامک (با سامانه راه پیام) و همچنین ورود با گوگل مدیریت کنید.', 'readysms'); ?></p>
                <ul style="list-style-type: disc; padding-right: 20px;">
                    <li><?php printf(wp_kses_post(__('برای فعال‌سازی ورود با پیامک، به صفحه <a href="%s">تنظیمات پیامک</a> رفته و اطلاعات API سامانه راه پیام را وارد نمایید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-sms-settings'))); ?></li>
                    <li><?php printf(wp_kses_post(__('برای فعال‌سازی ورود با گوگل، به صفحه <a href="%s">تنظیمات گوگل</a> رفته و شناسه‌های مربوطه را وارد کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-google-settings'))); ?></li>
                    <li><?php printf(wp_kses_post(__('جهت استفاده از فرم ورود، شورت کد %s را در برگه یا نوشته دلخواه خود قرار دهید.', 'readysms')), '<code>[readysms_login_form]</code>'); ?></li>
                    <li><?php printf(wp_kses_post(__('برای راهنمایی بیشتر و مشاهده مستندات کامل، به <a href="%s" target="_blank">وب‌سایت ردی استودیو</a> مراجعه کنید.', 'readysms')), esc_url('https://readystudio.ir/readysms-plugin/')); ?></li>
                </ul>
            </div>
        </div>
         <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('درباره سامانه راه پیام', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('این افزونه برای ارسال پیامک از سرویس‌دهنده راه پیام (Msgway.com) استفاده می‌کند. راه پیام یک سامانه قدرتمند برای ارسال پیامک‌های اعتبارسنجی (OTP) و اطلاع‌رسانی است.', 'readysms'); ?></p>
                <p><?php printf(wp_kses_post(__('برای استفاده از خدمات راه پیام و دریافت کلید API، به <a href="%s" target="_blank">وب‌سایت راه پیام</a> مراجعه کنید.', 'readysms')), esc_url('https://msgway.com/')); ?></p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render SMS settings page.
 */
function readysms_render_sms_settings_page() {
    ?>
    <div class="wrap readysms-wrap">
        <h1><?php esc_html_e('تنظیمات ورود با پیامک (راه پیام)', 'readysms'); ?></h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms_sms_options_group'); 
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('سامانه پیامکی', 'readysms'); ?></th>
                    <td><strong><?php esc_html_e('راه پیام (Msgway.com)', 'readysms'); ?></strong></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_api_key"><?php esc_html_e('کلید API (apiKey)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_api_key" name="ready_sms_api_key" value="<?php echo esc_attr(get_option('ready_sms_api_key')); ?>" class="regular-text" dir="ltr" required>
                         <p class="description"><?php esc_html_e('کلید API که از پنل کاربری خود در سامانه راه پیام دریافت کرده‌اید.', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_number"><?php esc_html_e('شماره ارسال (lineNumber)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_number" name="ready_sms_number" value="<?php echo esc_attr(get_option('ready_sms_number')); ?>" class="regular-text" placeholder="<?php esc_attr_e('اختیاری', 'readysms'); ?>" dir="ltr">
                        <p class="description"><?php esc_html_e('شماره خط اختصاصی شما در سامانه راه پیام. اگر خالی بگذارید، از شماره پیش‌فرض سامانه استفاده می‌شود.', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_pattern_code"><?php esc_html_e('کد پترن (templateID)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_pattern_code" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text" dir="ltr" required>
                        <p class="description">
                            <?php esc_html_e('کد پترن (الگو) که در سامانه راه پیام برای ارسال پیامک OTP ثبت کرده‌اید.', 'readysms'); ?>
                            <?php esc_html_e('این پترن باید حداقل یک پارامتر برای جایگذاری کد تایید داشته باشد. مثال:', 'readysms'); ?>
                            <div class="pattern-code-example" dir="rtl"><code><?php esc_html_e('کد تایید شما: %param1%', 'readysms'); ?></code></div>
                        </p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="ready_sms_timer_duration"><?php esc_html_e('مدت زمان تایمر OTP (ثانیه)', 'readysms'); ?></label></th>
                    <td>
                        <input type="number" id="ready_sms_timer_duration" name="ready_sms_timer_duration" value="<?php echo esc_attr(get_option('ready_sms_timer_duration', 120)); ?>" class="small-text" min="30" max="300">
                        <p class="description"><?php esc_html_e('مدت زمانی (به ثانیه) که کاربر باید برای درخواست مجدد کد OTP منتظر بماند. (مثلا: 120)', 'readysms'); ?></p>
                    </td>
                </tr>
            </table>
            <p style="margin-top:15px;">
                 <?php printf(wp_kses_post(__('برای اطمینان از صحت تنظیمات و عملکرد API، از صفحه <a href="%s">تست API</a> استفاده کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-api-test'))); ?>
            </p>
            <?php submit_button(__('ذخیره تنظیمات پیامک', 'readysms')); ?>
        </form>
    </div>
    <?php
}


/**
 * Render Google login settings page.
 */
function readysms_render_google_settings_page() {
    ?>
    <div class="wrap readysms-wrap">
        <h1><?php esc_html_e('تنظیمات ورود با گوگل', 'readysms'); ?></h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms_google_options_group'); 
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_google_client_id"><?php esc_html_e('Google Client ID', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_google_client_id" name="ready_google_client_id" value="<?php echo esc_attr(get_option('ready_google_client_id')); ?>" class="regular-text" dir="ltr">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_google_client_secret"><?php esc_html_e('Google Client Secret', 'readysms'); ?></label></th>
                    <td>
                        <input type="password" id="ready_google_client_secret" name="ready_google_client_secret" value="<?php echo esc_attr(get_option('ready_google_client_secret')); ?>" class="regular-text" dir="ltr">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Redirect URI مجاز', 'readysms'); ?></th>
                    <td>
                        <p><code><?php echo esc_url(home_url('/index.php')); ?></code></p>
                        <p class="description"><?php esc_html_e('این آدرس را باید در بخش "Authorized redirect URIs" پروژه خود در Google Cloud Console وارد کنید.', 'readysms'); ?></p>
                    </td>
                </tr>
            </table>
            <p>
                <?php printf(wp_kses_post(__('درصورتی که نمی‌دانید چطور این شناسه‌ها را از گوگل دریافت کنید، <a href="%s" target="_blank">این راهنما را در وب‌سایت ردی استودیو مطالعه کنید</a>.', 'readysms')), esc_url('https://readystudio.ir/register-with-google-account/')); ?>
            </p>
             <p class="description" style="color:red;">
                <?php esc_html_e('توجه: برای استفاده از ورود با گوگل، داشتن هاست خارج از ایران و فعال بودن SSL (HTTPS) الزامی است. همچنین، سرویس‌های گوگل ممکن است برای کاربران ایرانی با محدودیت‌هایی مواجه باشند.', 'readysms'); ?>
            </p>
            <?php submit_button(__('ذخیره تنظیمات گوگل', 'readysms')); ?>
        </form>
    </div>
    <?php
}


/**
 * Render API Test page.
 */
function readysms_render_api_test_page() {
    ?>
    <div class="wrap readysms-wrap">
        <h1><?php esc_html_e('تست API سامانه راه پیام', 'readysms'); ?></h1>
        <p><?php esc_html_e('در این بخش می‌توانید عملکرد APIهای مختلف سامانه راه پیام را با استفاده از تنظیمات ذخیره شده در صفحه "تنظیمات پیامک"، آزمایش کنید.', 'readysms'); ?></p>
        <p><?php printf(wp_kses_post(__('ابتدا مطمئن شوید که <a href="%s">تنظیمات پیامک</a> را به درستی وارد و ذخیره کرده‌اید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-sms-settings'))); ?></p>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('1. تست ارسال و تایید OTP', 'readysms'); ?></span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_test_phone_number"><?php esc_html_e('شماره تلفن برای تست', 'readysms'); ?></label></th>
                        <td><input type="text" id="admin_test_phone_number" placeholder="<?php esc_attr_e('مثال: 09123456789', 'readysms'); ?>" class="regular-text" dir="ltr"></td>
                    </tr>
                </table>
                <button type="button" id="admin_send_test_otp_button" class="button button-primary"><?php esc_html_e('ارسال پیامک OTP آزمایشی', 'readysms'); ?></button>
                
                <div id="admin_verify_otp_section" style="display: none; margin-top: 20px; padding-top:15px; border-top: 1px dashed #ccc;">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="admin_test_otp_code"><?php esc_html_e('کد OTP دریافتی', 'readysms'); ?></label></th>
                            <td><input type="text" id="admin_test_otp_code" placeholder="<?php esc_attr_e('کد 6 رقمی', 'readysms'); ?>" class="regular-text" dir="ltr"></td>
                        </tr>
                    </table>
                    <button type="button" id="admin_verify_test_otp_button" class="button"><?php esc_html_e('بررسی کد OTP', 'readysms'); ?></button>
                </div>
                <div id="admin_test_otp_result" class="api-test-result" style="margin-top: 10px; display:none;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('2. تست دریافت وضعیت پیامک', 'readysms'); ?></span></h2>
            <div class="inside">
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_status_reference_id"><?php esc_html_e('شناسه مرجع پیامک', 'readysms'); ?></label></th>
                        <td>
                            <input type="text" id="admin_status_reference_id" placeholder="<?php esc_attr_e('مثال: MSG-XXXXXXXXXXXX', 'readysms'); ?>" class="regular-text" dir="ltr">
                            <p class="description"><?php esc_html_e('این شناسه پس از ارسال موفقیت‌آمیز پیامک توسط API بازگردانده می‌شود (OTPReferenceId).', 'readysms'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="admin_check_status_button" class="button"><?php esc_html_e('بررسی وضعیت', 'readysms'); ?></button>
                <div id="admin_status_result" class="api-test-result" style="margin-top: 10px; display:none;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('3. تست دریافت اطلاعات قالب پیام', 'readysms'); ?></span></h2>
            <div class="inside">
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_template_id_test"><?php esc_html_e('شناسه قالب (Template ID)', 'readysms'); ?></label></th>
                        <td>
                            <input type="text" id="admin_template_id_test" placeholder="<?php esc_attr_e('مثال: 1001', 'readysms'); ?>" class="regular-text" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" dir="ltr">
                             <p class="description"><?php esc_html_e('شناسه قالبی که می‌خواهید اطلاعات آن را از راه پیام دریافت کنید.', 'readysms'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="admin_get_template_button" class="button"><?php esc_html_e('دریافت اطلاعات قالب', 'readysms'); ?></button>
                <div id="admin_template_result" class="api-test-result" style="margin-top: 10px; display:none;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('4. تست دریافت موجودی حساب', 'readysms'); ?></span></h2>
            <div class="inside">
                <button type="button" id="admin_get_balance_button" class="button"><?php esc_html_e('دریافت موجودی', 'readysms'); ?></button>
                <div id="admin_balance_result" class="api-test-result" style="margin-top: 10px; display:none;"></div>
            </div>
        </div>
    </div>
    <style>
        .readysms-wrap .postbox { margin-bottom: 20px; }
        .readysms-wrap .api-test-result { padding: 10px; border: 1px solid #ccd0d4; background: #f6f7f7; margin-top:10px; max-height: 250px; overflow-y: auto; direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-all; font-size: 12px; line-height: 1.6; }
        .readysms-wrap .api-test-result.success { border-left: 4px solid #4CAF50; } /* Green for success */
        .readysms-wrap .api-test-result.error { border-left: 4px solid #F44336; } /* Red for error */
    </style>
    <?php
}

/**
 * Add "Powered by ReadyStudio" to admin footer.
 */
function readysms_admin_footer_text() {
    $current_screen = get_current_screen();
    if ($current_screen && strpos($current_screen->id, 'readysms-') !== false) {
        echo '<span id="footer-thankyou" style="float:left !important;">'.
             sprintf(
                wp_kses_post( __( 'افزونه ردی اس‌ام‌اس، ارائه شده توسط <a href="%s" target="_blank">ردی استودیو</a>.', 'readysms' ) ),
                'https://readystudio.ir/'
             ) .
             '</span>';
    }
}
// Use a higher priority to ensure it can override or appear correctly
add_filter('admin_footer_text', 'readysms_admin_footer_text', 20);
?>
