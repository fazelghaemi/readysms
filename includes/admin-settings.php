<?php
// File: includes/admin-settings.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin scripts and styles.
 */
function readysms_enqueue_admin_assets($hook_suffix) {
    $plugin_page_slug_base = 'readysms-settings'; // The main slug for add_menu_page

    $allowed_hooks = [
        'toplevel_page_' . $plugin_page_slug_base,
        $plugin_page_slug_base . '_page_readysms-google-settings',
        $plugin_page_slug_base . '_page_readysms-sms-settings',
        $plugin_page_slug_base . '_page_readysms-api-test',
    ];

    if (!in_array($hook_suffix, $allowed_hooks)) {
        $current_page_query = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $allowed_page_slugs_for_query = ['readysms-settings', 'readysms-google-settings', 'readysms-sms-settings', 'readysms-api-test'];
        if (!in_array($current_page_query, $allowed_page_slugs_for_query)) {
            return;
        }
    }

    wp_enqueue_style('readysms-admin-panel-style', READYSMS_URL . 'assets/css/panel.css', array(), READYSMS_VERSION);

    wp_enqueue_script('readysms-admin-panel-js', READYSMS_URL . 'assets/js/admin-settings.js', array('jquery'), READYSMS_VERSION, true);
    
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
        'msg_unexpected_error'    => __('یک خطای پیش‌بینی نشده رخ داد. کنسول مرورگر و لاگ خطای PHP را بررسی کنید.', 'readysms'),
    ));
}
add_action('admin_enqueue_scripts', 'readysms_enqueue_admin_assets');


/**
 * Add admin menus.
 */
function readysms_add_admin_menu() {
    // Main Menu Page
    add_menu_page(
        __('داشبورد ردی اس‌ام‌اس', 'readysms'), // Page Title
        __('ردی اس‌ام‌اس', 'readysms'),        // Menu Title
        'manage_options',                       // Capability
        'readysms-settings',                    // Menu Slug (main slug)
        'readysms_render_dashboard_page',       // Callback function
        READYSMS_URL . 'assets/readysms-icon.svg', // Icon URL - Changed to your SVG icon
        30  // Position
    );
    
    // Submenu: Dashboard
    add_submenu_page(
        'readysms-settings',                    // Parent Slug
        __('داشبورد', 'readysms'),              // Page Title
        __('داشبورد', 'readysms'),              // Menu Title
        'manage_options',                       // Capability
        'readysms-settings',                    // Menu Slug (same as parent for main content)
        'readysms_render_dashboard_page'        // Callback function
    );
    
    add_submenu_page(
        'readysms-settings',                    // Parent Slug
        __('تنظیمات پیامک', 'readysms'),      // Page Title
        __('تنظیمات پیامک', 'readysms'),    // Menu Title
        'manage_options',                       // Capability
        'readysms-sms-settings',                // Menu Slug
        'readysms_render_sms_settings_page'     // Callback function
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
    // New setting for OTP length
    register_setting('readysms_sms_options_group', 'ready_sms_otp_length', [
        'sanitize_callback' => 'readysms_sanitize_otp_length', // Custom sanitize callback
        'default'           => 6, // Default to 6 digits
        'type'              => 'integer'
    ]);


    // Google Settings
    register_setting('readysms_google_options_group', 'ready_google_client_id', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_google_options_group', 'ready_google_client_secret', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
}
add_action('admin_init', 'readysms_register_settings');

/**
 * Sanitize callback for OTP length.
 * Ensures the value is an integer between 4 and 7.
 */
function readysms_sanitize_otp_length($input) {
    $input = absint($input); // Ensure it's a positive integer
    if ($input < 4) {
        return 4;
    }
    if ($input > 7) {
        return 7;
    }
    return $input;
}


/**
 * Display admin notices for settings updates.
 */
function readysms_admin_notices() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        $current_screen = get_current_screen();
        $is_readysms_page = $current_screen && (
            strpos($current_screen->id, 'readysms-') !== false || 
            $current_screen->id === 'toplevel_page_readysms-settings' ||
            (isset($_GET['page']) && in_array(sanitize_text_field($_GET['page']), ['readysms-settings', 'readysms-sms-settings', 'readysms-google-settings', 'readysms-api-test']))
        );

        if ($is_readysms_page) {
            echo '<div class="notice notice-success is-dismissible" style="border-right: 4px solid #00635D; margin-top:15px;"><p style="font-family: \'Yekan\', sans-serif;">' . esc_html__('تنظیمات با موفقیت ذخیره شد.', 'readysms') . '</p></div>';
        }
    }
}
add_action('admin_notices', 'readysms_admin_notices');


/**
 * Render the main dashboard page (callback for main menu and first submenu).
 */
function readysms_render_dashboard_page() {
    $msgway_affiliate_link = 'https://www.msgway.com/r/lr';
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'readysms-settings';
    ?>
    <div class="wrap readysms-wrap">
        <?php if (get_option('ready_sms_api_key') === '' || get_option('ready_sms_pattern_code') === ''): ?>
        <div class="notice notice-warning is-dismissible" style="border-right: 4px solid #F59E0B; margin-top:15px;">
            <p style="font-family: 'Yekan', sans-serif;">
                <strong><?php esc_html_e('پیکربندی ناقص:', 'readysms'); ?></strong>
                <?php printf(
                    wp_kses_post(__('لطفاً برای فعال‌سازی کامل ورود با پیامک، <a href="%s">کلید API و کد پترن راه پیام</a> را در تنظیمات وارد کنید.', 'readysms')),
                    esc_url(admin_url('admin.php?page=readysms-sms-settings'))
                ); ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="readysms-plugin-banner" style="text-align:center; margin-bottom:30px;">
            <a href="https://readystudio.ir/readysms-plugin/?utm_source=plugin_dashboard&utm_medium=banner&utm_campaign=readysms" target="_blank">
                <img src="<?php echo esc_url(READYSMS_URL . 'assets/banner.jpg'); ?>" alt="<?php esc_attr_e('بنر افزونه ردی اس‌ام‌اس', 'readysms'); ?>" style="max-width: 800px; width:100%; height:auto; border-radius: var(--rs-border-radius-lg); box-shadow: var(--rs-shadow-lg);">
            </a>
        </div>

        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('داشبورد ردی اس‌ام‌اس', 'readysms'); ?>
        </h1>
        <p><?php esc_html_e('به پنل مدیریت افزونه ورود و ثبت نام با پیامک و گوگل، محصولی از ردی استودیو، خوش آمدید.', 'readysms'); ?></p>

        <div class="dokme-container" style="margin:30px 0;">
            <div class="dokme <?php echo ($current_page_slug === 'readysms-settings' || $current_page_slug === '') ? 'active' : ''; ?>">
                 <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API راه پیام', 'readysms'); ?></a>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('راهنمای سریع و شروع به کار', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><strong><?php esc_html_e('برای استفاده کامل از امکانات افزونه ردی اس‌ام‌اس، مراحل زیر را دنبال کنید:', 'readysms'); ?></strong></p>
                <ul style="list-style-type: decimal; padding-right: 20px; margin-top:10px;">
                    <li><?php printf(wp_kses_post(__('<strong>تنظیمات پیامک:</strong> به بخش <a href="%s">تنظیمات پیامک</a> بروید و اطلاعات حساب کاربری خود در سامانه راه پیام (کلید API، شماره ارسال (اختیاری)، طول کد OTP و کد پترن OTP) را وارد نمایید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-sms-settings'))); ?></li>
                    <li><?php printf(wp_kses_post(__('<strong>تست API:</strong> پس از وارد کردن اطلاعات، از بخش <a href="%s">تست API</a> برای اطمینان از صحت عملکرد اتصال به راه پیام استفاده کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-api-test'))); ?></li>
                    <li><?php printf(wp_kses_post(__('<strong>ورود با گوگل (اختیاری):</strong> اگر مایل به استفاده از ورود با گوگل هستید، به بخش <a href="%s">تنظیمات گوگل</a> مراجعه کرده و شناسه‌های مربوط به پروژه گوگل خود را وارد کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-google-settings'))); ?></li>
                    <li><?php printf(wp_kses_post(__('<strong>استفاده از شورت‌کد:</strong> شورت‌کد %s را در هر برگه یا نوشته‌ای که می‌خواهید فرم ورود/ثبت‌نام نمایش داده شود، قرار دهید.', 'readysms')), '<code>[readysms_login_form]</code>'); ?></li>
                </ul>
                <p class="mt-3"><?php printf(wp_kses_post(__('برای مشاهده مستندات کامل، آموزش‌های ویدیویی و دریافت پشتیبانی، به <a href="%s" target="_blank">صفحه افزونه ردی اس‌ام‌اس در وب‌سایت ردی استودیو</a> مراجعه فرمایید.', 'readysms')), esc_url('https://readystudio.ir/readysms-plugin/?utm_source=plugin_dashboard&utm_medium=link&utm_campaign=readysms')); ?></p>
            </div>
        </div>

         <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('درباره سامانه پیامکی راه پیام', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('افزونه ردی اس‌ام‌اس برای ارسال پیامک‌های تاییدیه (OTP) از سرویس‌دهنده معتبر ایرانی، <strong>راه پیام (Msgway.com)</strong>، استفاده می‌کند.', 'readysms'); ?></p>
                <p><?php esc_html_e('راه پیام با ارائه پنل کاربری ساده و API قدرتمند، امکان ارسال سریع و مطمئن پیامک را برای کسب‌وکارهای آنلاین فراهم می‌آورد.', 'readysms'); ?></p>
                <p class="mt-3"><?php printf(wp_kses_post(__('برای ثبت نام در سامانه راه پیام، مشاهده تعرفه‌ها و دریافت کلید API، لطفاً از طریق لینک زیر اقدام نمایید: <br><a href="%s" target="_blank" class="button button-primary" style="margin-top:10px;">ثبت نام و ورود به پنل راه پیام</a>', 'readysms')), esc_url($msgway_affiliate_link)); ?></p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render SMS settings page.
 */
function readysms_render_sms_settings_page() {
    $msgway_affiliate_link = 'https://www.msgway.com/r/lr';
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات ورود با پیامک (راه پیام)', 'readysms'); ?>
        </h1>

         <div class="dokme-container" style="margin:30px 0 20px;">
            <div class="dokme <?php echo ($current_page_slug === 'readysms-settings' || $current_page_slug === '') ? 'active' : ''; ?>">
                 <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API راه پیام', 'readysms'); ?></a>
            </div>
        </div>

        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms_sms_options_group'); 
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('سامانه پیامکی متصل', 'readysms'); ?></th>
                    <td>
                        <strong><?php esc_html_e('راه پیام (Msgway.com)', 'readysms'); ?></strong><br>
                        <a href="<?php echo esc_url($msgway_affiliate_link); ?>" target="_blank"><?php esc_html_e('ورود به پنل راه پیام', 'readysms'); ?></a>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_api_key"><?php esc_html_e('کلید API (apiKey)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_api_key" name="ready_sms_api_key" value="<?php echo esc_attr(get_option('ready_sms_api_key')); ?>" class="regular-text ltr-code" dir="ltr" required placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                         <p class="description"><?php esc_html_e('کلید API که از پنل کاربری خود در سامانه راه پیام دریافت کرده‌اید.', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_number"><?php esc_html_e('شماره ارسال (lineNumber)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_number" name="ready_sms_number" value="<?php echo esc_attr(get_option('ready_sms_number')); ?>" class="regular-text ltr-code" placeholder="<?php esc_attr_e('اختیاری (مثلا: 3000xxxx)', 'readysms'); ?>" dir="ltr">
                        <p class="description"><?php esc_html_e('شماره خط اختصاصی شما در سامانه راه پیام. اگر خالی بگذارید، از شماره پیش‌فرض سامانه برای ارسال استفاده می‌شود.', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_otp_length"><?php esc_html_e('طول کد تایید (OTP)', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_otp_length" name="ready_sms_otp_length">
                            <?php
                            $current_length = get_option('ready_sms_otp_length', 6);
                            for ($i = 4; $i <= 7; $i++) {
                                echo '<option value="' . esc_attr($i) . '" ' . selected($current_length, $i, false) . '>' . esc_html(sprintf(__('%d رقمی', 'readysms'), $i)) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('تعداد ارقام کد تایید پیامکی که برای کاربر ارسال می‌شود (بین 4 تا 7 رقم).', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_pattern_code"><?php esc_html_e('کد پترن OTP (templateID)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_pattern_code" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text ltr-code" dir="ltr" required placeholder="12345">
                        <p class="description">
                            <?php esc_html_e('کد پترن (الگو) که در سامانه راه پیام برای ارسال پیامک OTP ثبت کرده‌اید.', 'readysms'); ?>
                            <?php esc_html_e('این پترن باید شامل یک پارامتر برای جایگذاری کد تایید باشد. مثال محتوای پترن در راه پیام:', 'readysms'); ?>
                            <div class="pattern-code-example" style="font-family: inherit; direction:rtl; text-align:right; white-space: pre-wrap; line-height: 1.8;"><code><?php
                                echo esc_html__('بفرمائید کد تأیید: %param1%', 'readysms') . "\n"; // \n for new line
                                echo esc_html(get_bloginfo('name')); // Site name
                            ?></code></div>
                             <p class="description"><?php esc_html_e('در مثال بالا، %param1% همان کد ورود تولید شده توسط افزونه خواهد بود.', 'readysms'); ?></p>
                        </p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="ready_sms_timer_duration"><?php esc_html_e('مدت زمان تایمر OTP (ثانیه)', 'readysms'); ?></label></th>
                    <td>
                        <input type="number" id="ready_sms_timer_duration" name="ready_sms_timer_duration" value="<?php echo esc_attr(get_option('ready_sms_timer_duration', 120)); ?>" class="small-text" min="30" max="300" step="10" dir="ltr">
                        <p class="description"><?php esc_html_e('مدت زمانی (به ثانیه) که کاربر باید برای درخواست مجدد کد OTP منتظر بماند. (پیشنهادی: 60 تا 180 ثانیه)', 'readysms'); ?></p>
                    </td>
                </tr>
            </table>
            <p style="margin-top:25px;">
                 <?php printf(wp_kses_post(__('برای اطمینان از صحت عملکرد تنظیمات API، حتماً از صفحه <a href="%s">تست API</a> استفاده کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-api-test'))); ?>
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
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات ورود با گوگل', 'readysms'); ?>
        </h1>

        <div class="dokme-container" style="margin:30px 0 20px;">
             <div class="dokme <?php echo ($current_page_slug === 'readysms-settings' || $current_page_slug === '') ? 'active' : ''; ?>">
                 <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API راه پیام', 'readysms'); ?></a>
            </div>
        </div>

        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms_google_options_group'); 
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_google_client_id"><?php esc_html_e('Google Client ID', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_google_client_id" name="ready_google_client_id" value="<?php echo esc_attr(get_option('ready_google_client_id')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_google_client_secret"><?php esc_html_e('Google Client Secret', 'readysms'); ?></label></th>
                    <td>
                        <input type="password" id="ready_google_client_secret" name="ready_google_client_secret" value="<?php echo esc_attr(get_option('ready_google_client_secret')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="GOCSPX-xxxxxxxxxxxxxxxxxxxxxx">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Authorized Redirect URI', 'readysms'); ?></th>
                    <td>
                        <p><code class="ltr-code" style="user-select: all; cursor: pointer; background: #f0f0f0; padding: 5px 8px; border-radius:4px; display:inline-block;"><?php echo esc_url(home_url('/index.php')); ?></code></p>
                        <p class="description"><?php esc_html_e('این آدرس را کپی کرده و در بخش "Authorized redirect URIs" پروژه خود در Google Cloud Console (بخش Credentials -> OAuth 2.0 Client IDs) وارد کنید.', 'readysms'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="mt-3">
                <?php printf(wp_kses_post(__('اگر با نحوه دریافت شناسه‌های گوگل آشنا نیستید، <a href="%s" target="_blank">راهنمای کامل را در وب‌سایت ردی استودیو مطالعه کنید</a>.', 'readysms')), esc_url('https://readystudio.ir/blog/wordpress-google-login-setup/?utm_source=plugin_settings&utm_medium=link&utm_campaign=readysms')); ?>
            </p>
             <div class="instruction-box google-instruction mt-3" style="border-color: #F56565; background-color: #FFF5F5; color: #C53030;">
                <p><strong><?php esc_html_e('نکات مهم برای ورود با گوگل:', 'readysms'); ?></strong></p>
                <ul>
                    <li><?php esc_html_e('داشتن گواهینامه SSL (HTTPS) برای دامنه شما الزامی است.', 'readysms'); ?></li>
                    <li><?php esc_html_e('هاست شما ترجیحاً باید خارج از ایران باشد، زیرا سرویس‌های گوگل ممکن است برای IPهای ایران با محدودیت مواجه شوند.', 'readysms'); ?></li>
                    <li><?php esc_html_e('اطمینان حاصل کنید که APIهای لازم (مانند Google People API) در پروژه Google Cloud شما فعال باشند.', 'readysms'); ?></li>
                     <li><?php esc_html_e('پس از ذخیره Client ID و Secret، عملکرد ورود با گوگل را به دقت تست کنید.', 'readysms'); ?></li>
                </ul>
            </div>
            <?php submit_button(__('ذخیره تنظیمات گوگل', 'readysms')); ?>
        </form>
    </div>
    <?php
}


/**
 * Render API Test page.
 */
function readysms_render_api_test_page() {
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="" class="readysms-header-logo">
            <?php esc_html_e('تست API سامانه راه پیام', 'readysms'); ?>
        </h1>
        
        <div class="dokme-container" style="margin:30px 0 20px;">
             <div class="dokme <?php echo ($current_page_slug === 'readysms-settings' || $current_page_slug === '') ? 'active' : ''; ?>">
                 <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a>
            </div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API راه پیام', 'readysms'); ?></a>
            </div>
        </div>

        <p><?php esc_html_e('در این بخش می‌توانید عملکرد APIهای مختلف سامانه راه پیام را با استفاده از تنظیمات ذخیره شده، آزمایش کنید.', 'readysms'); ?></p>
        <p><?php printf(wp_kses_post(__('پیش از انجام تست، مطمئن شوید که <a href="%s">تنظیمات پیامک</a> (کلید API و کد پترن) را به درستی وارد و ذخیره کرده‌اید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-sms-settings'))); ?></p>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('1. تست ارسال و تایید OTP', 'readysms'); ?></span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_test_phone_number"><?php esc_html_e('شماره موبایل برای تست', 'readysms'); ?></label></th>
                        <td><input type="text" id="admin_test_phone_number" placeholder="<?php esc_attr_e('مثال: 09123456789', 'readysms'); ?>" class="regular-text ltr-code" dir="ltr"></td>
                    </tr>
                </table>
                <button type="button" id="admin_send_test_otp_button" class="button button-primary"><?php esc_html_e('ارسال پیامک OTP آزمایشی', 'readysms'); ?></button>
                
                <div id="admin_verify_otp_section" style="display: none; margin-top: 25px; padding-top:20px; border-top: 1px dashed var(--rs-border-color);">
                    <table class="form-table" style="box-shadow:none; border:none; background:transparent;">
                        <tr valign="top" style="border-bottom:none;">
                            <th scope="row"><label for="admin_test_otp_code"><?php esc_html_e('کد OTP دریافتی', 'readysms'); ?></label></th>
                            <td><input type="text" id="admin_test_otp_code" placeholder="<?php printf(esc_attr__('کد %d رقمی', 'readysms'), esc_attr(get_option('ready_sms_otp_length', 6))); ?>" class="regular-text ltr-code" dir="ltr" maxlength="<?php echo esc_attr(get_option('ready_sms_otp_length', 6)); ?>"></td>
                        </tr>
                    </table>
                    <button type="button" id="admin_verify_test_otp_button" class="button button-secondary"><?php esc_html_e('بررسی کد OTP', 'readysms'); ?></button>
                </div>
                <div id="admin_test_otp_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('2. تست دریافت وضعیت پیامک', 'readysms'); ?></span></h2>
            <div class="inside">
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_status_reference_id"><?php esc_html_e('شناسه مرجع پیامک', 'readysms'); ?></label></th>
                        <td>
                            <input type="text" id="admin_status_reference_id" placeholder="<?php esc_attr_e('مثال: MSG-XXXXXXXX یا 174868256...', 'readysms'); ?>" class="regular-text ltr-code" dir="ltr">
                            <p class="description"><?php esc_html_e('این شناسه (referenceID یا OTPReferenceId) پس از ارسال موفقیت‌آمیز پیامک توسط API راه پیام بازگردانده می‌شود.', 'readysms'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="admin_check_status_button" class="button button-secondary"><?php esc_html_e('بررسی وضعیت', 'readysms'); ?></button>
                <div id="admin_status_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('3. تست دریافت اطلاعات قالب پیام', 'readysms'); ?></span></h2>
            <div class="inside">
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_template_id_test"><?php esc_html_e('شناسه قالب (Template ID)', 'readysms'); ?></label></th>
                        <td>
                            <input type="text" id="admin_template_id_test" placeholder="<?php esc_attr_e('کد پترن وارد شده در تنظیمات', 'readysms'); ?>" class="regular-text ltr-code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" dir="ltr">
                             <p class="description"><?php esc_html_e('شناسه قالبی که می‌خواهید اطلاعات آن را از راه پیام دریافت کنید. (معمولاً همان کد پترن OTP است)', 'readysms'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="admin_get_template_button" class="button button-secondary"><?php esc_html_e('دریافت اطلاعات قالب', 'readysms'); ?></button>
                <div id="admin_template_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('4. تست دریافت موجودی حساب', 'readysms'); ?></span></h2>
            <div class="inside">
                <button type="button" id="admin_get_balance_button" class="button button-secondary"><?php esc_html_e('دریافت موجودی حساب راه پیام', 'readysms'); ?></button>
                <div id="admin_balance_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Add "Powered by ReadyStudio" to admin footer.
 */
function readysms_admin_footer_text($footer_text) {
    $current_screen = get_current_screen();
    $is_readysms_page = $current_screen && (
        strpos($current_screen->id, 'readysms-') !== false || 
        $current_screen->id === 'toplevel_page_readysms-settings' ||
        (isset($_GET['page']) && in_array(sanitize_text_field($_GET['page']), ['readysms-settings', 'readysms-sms-settings', 'readysms-google-settings', 'readysms-api-test']))
    );

    if ($is_readysms_page) {
        $readystudio_logo_svg_url = READYSMS_URL . 'assets/readystudio-logo.svg';
        $footer_text = '<span id="footer-thankyou" class="readysms-footer-branding">' .
             sprintf(
                wp_kses_post( __( 'افزونه ردی اس‌ام‌اس، با افتخار توسط %s توسعه یافته است.', 'readysms' ) ),
                '<a href="https://readystudio.ir/?utm_source=plugin_footer&utm_medium=link&utm_campaign=readysms" target="_blank" style="display:inline-flex; align-items:center; font-weight:bold; color: var(--rs-primary-color);"><img src="'.esc_url($readystudio_logo_svg_url).'" alt="ReadyStudio" style="height:22px; width:auto; margin-left:7px; vertical-align: middle;">ردی استودیو</a>'
             ) .
             '</span>';
    }
    return $footer_text;
}
add_filter('admin_footer_text', 'readysms_admin_footer_text', 20);
?>
