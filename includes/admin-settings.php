<?php
// File: includes/admin-settings.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin scripts and styles.
 */
function readysms_enqueue_admin_assets($hook_suffix) {
    $plugin_page_slug_base = 'readysms-settings';
    $load_assets = false;

    $allowed_page_slugs_for_query = ['readysms-settings', 'readysms-google-settings', 'readysms-sms-settings', 'readysms-form-settings', 'readysms-redirect-settings', 'readysms-api-test', 'readysms-tools'];
    $allowed_hooks = [
        'toplevel_page_' . $plugin_page_slug_base,
    ];
    foreach ($allowed_page_slugs_for_query as $slug) {
        if ($slug !== $plugin_page_slug_base) {
            $allowed_hooks[] = $plugin_page_slug_base . '_page_' . $slug;
        }
    }
    
    if (in_array($hook_suffix, $allowed_hooks)) {
        $load_assets = true;
    } else {
        $current_page_query = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if (in_array($current_page_query, $allowed_page_slugs_for_query)) {
            $load_assets = true;
        }
    }

    if (!$load_assets) {
        return;
    }

    $current_page_query_for_media = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($current_page_query_for_media === 'readysms-form-settings') {
        wp_enqueue_media();
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
        'export_users_action'     => 'readysms_export_users',
        'otp_length'              => (int) get_option('ready_sms_otp_length', 6),
        'digits_text'             => __('رقمی', 'readysms'),
        'msg_fill_phone'          => __('لطفا شماره تلفن را برای تست وارد کنید.', 'readysms'),
        'msg_fill_otp'            => __('لطفا کد OTP دریافتی را وارد کنید.', 'readysms'),
        'msg_fill_otp_len_invalid'=> __('فرمت کد OTP صحیح نیست.', 'readysms'),
        'msg_fill_ref_id'         => __('لطفا شناسه مرجع را وارد کنید.', 'readysms'),
        'msg_fill_template_id'    => __('لطفا شناسه قالب را وارد کنید.', 'readysms'),
        'msg_unexpected_error'    => __('یک خطای پیش‌بینی نشده رخ داد. کنسول مرورگر و لاگ خطای PHP را بررسی کنید.', 'readysms'),
        'exporting_users_text'    => __('در حال آماده‌سازی خروجی...', 'readysms'),
        'export_users_btn_text'   => __('خروجی کاربران (CSV)', 'readysms'),
        'sending_text'            => __('در حال ارسال...', 'readysms'),
        'verifying_text'          => __('در حال بررسی...', 'readysms'),
        'fetching_text'           => __('در حال دریافت...', 'readysms'),
        'send_otp_btn_text'       => __('ارسال پیامک OTP آزمایشی', 'readysms'),
        'verify_otp_btn_text'     => __('بررسی کد OTP', 'readysms'),
        'check_status_btn_text'   => __('بررسی وضعیت', 'readysms'),
        'get_template_btn_text'   => __('دریافت اطلاعات قالب', 'readysms'),
        'get_balance_btn_text'    => __('دریافت موجودی حساب', 'readysms'),
        'uploader_title'          => __('انتخاب یا آپلود لوگو', 'readysms'),
        'uploader_button_text'    => __('استفاده از این لوگو', 'readysms'),
    ));
}
add_action('admin_enqueue_scripts', 'readysms_enqueue_admin_assets');

/**
 * Add admin menus.
 */
function readysms_add_admin_menu() {
    add_menu_page(
        __('داشبورد ردی اس‌ام‌اس', 'readysms'),
        __('ردی اس‌ام‌اس', 'readysms'),
        'manage_options',
        'readysms-settings',
        'readysms_render_dashboard_page',
        READYSMS_URL . 'assets/readysms-icon.svg',
        30
    );
    add_submenu_page(
        'readysms-settings',
        __('داشبورد', 'readysms'),
        __('داشبورد', 'readysms'),
        'manage_options',
        'readysms-settings',
        'readysms_render_dashboard_page'
    );
    add_submenu_page(
        'readysms-settings',
        __('تنظیمات پیامک', 'readysms'),
        __('تنظیمات پیامک', 'readysms'),
        'manage_options',
        'readysms-sms-settings',
        'readysms_render_sms_settings_page'
    );
    add_submenu_page(
        'readysms-settings',
        __('تنظیمات گوگل', 'readysms'),
        __('ورود با گوگل', 'readysms'),
        'manage_options',
        'readysms-google-settings',
        'readysms_render_google_settings_page'
    );
    add_submenu_page(
        'readysms-settings',
        __('تنظیمات فرم ورود', 'readysms'),
        __('تنظیمات فرم', 'readysms'),
        'manage_options',
        'readysms-form-settings',
        'readysms_render_form_settings_page'
    );
    add_submenu_page(
        'readysms-settings',
        __('تنظیمات تغییر مسیر', 'readysms'),
        __('تغییر مسیرها', 'readysms'),
        'manage_options',
        'readysms-redirect-settings',
        'readysms_render_redirect_settings_page'
    );
    add_submenu_page(
        'readysms-settings',
        __('تست API راه پیام', 'readysms'),
        __('تست API', 'readysms'),
        'manage_options',
        'readysms-api-test',
        'readysms_render_api_test_page'
    );
     add_submenu_page(
        'readysms-settings',
        __('ابزارها', 'readysms'),
        __('ابزارها', 'readysms'),
        'manage_options',
        'readysms-tools',
        'readysms_render_tools_page'
    );
}
add_action('admin_menu', 'readysms_add_admin_menu');

/**
 * Register plugin settings.
 */
function readysms_register_settings() {
    // SMS Settings
    register_setting('readysms_sms_options_group', 'ready_sms_api_key', ['sanitize_callback' => 'sanitize_text_field', 'default' => '', 'type' => 'string']);
    register_setting('readysms_sms_options_group', 'ready_sms_number', ['sanitize_callback' => 'sanitize_text_field', 'default' => '', 'type' => 'string']);
    register_setting('readysms_sms_options_group', 'ready_sms_pattern_code', ['sanitize_callback' => 'sanitize_text_field', 'default' => '', 'type' => 'string']);
    register_setting('readysms_sms_options_group', 'ready_sms_otp_length', ['sanitize_callback' => 'readysms_sanitize_otp_length', 'default' => 6, 'type' => 'integer']);
    register_setting('readysms_sms_options_group', 'ready_sms_resend_timer', ['sanitize_callback' => 'readysms_sanitize_resend_timer', 'default' => 120, 'type' => 'integer']);
    register_setting('readysms_sms_options_group', 'ready_sms_country_code_mode', ['sanitize_callback' => 'sanitize_text_field', 'default' => 'iran_only', 'type' => 'string']);
    register_setting('readysms_sms_options_group', 'ready_sms_send_method', ['sanitize_callback' => 'sanitize_text_field', 'default' => 'sms', 'type' => 'string']); // گزینه روش ارسال

    // Google Settings
    register_setting('readysms_google_options_group', 'ready_google_client_id', ['sanitize_callback' => 'sanitize_text_field', 'default' => '', 'type' => 'string']);
    register_setting('readysms_google_options_group', 'ready_google_client_secret', ['sanitize_callback' => 'sanitize_text_field', 'default' => '', 'type' => 'string']);

    // Form Settings
    register_setting('readysms_form_options_group', 'ready_form_logo_url', ['sanitize_callback' => 'esc_url_raw', 'default' => '', 'type' => 'string']);

    // Redirect Settings
    register_setting('readysms_redirect_options_group', 'ready_redirect_after_login', ['sanitize_callback' => 'esc_url_raw', 'default' => '', 'type' => 'string']);
    register_setting('readysms_redirect_options_group', 'ready_redirect_after_register', ['sanitize_callback' => 'esc_url_raw', 'default' => '', 'type' => 'string']);
    register_setting('readysms_redirect_options_group', 'ready_redirect_after_logout', ['sanitize_callback' => 'esc_url_raw', 'default' => '', 'type' => 'string']);
    register_setting('readysms_redirect_options_group', 'ready_redirect_my_account_link', ['sanitize_callback' => 'esc_url_raw', 'default' => '', 'type' => 'string']);
}
add_action('admin_init', 'readysms_register_settings');

/**
 * Sanitize callback for OTP length.
 */
function readysms_sanitize_otp_length($input) {
    $input = absint($input);
    if ($input < 4) return 4;
    if ($input > 7) return 7;
    return $input;
}

/**
 * Sanitize callback for Resend Timer.
 */
function readysms_sanitize_resend_timer($input) {
    $allowed_values = [30, 60, 120, 180, 240];
    $input = absint($input);
    if (in_array($input, $allowed_values, true)) {
        return $input;
    }
    return 120;
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
            (isset($_GET['page']) && in_array(sanitize_text_field($_GET['page']), ['readysms-settings', 'readysms-sms-settings', 'readysms-google-settings', 'readysms-form-settings', 'readysms-redirect-settings', 'readysms-api-test', 'readysms-tools']))
        );

        if ($is_readysms_page) {
            echo '<div class="notice notice-success is-dismissible" style="border-right: 4px solid #00635D; margin-top:15px;"><p style="font-family: \'Yekan\', sans-serif;">' . esc_html__('تنظیمات با موفقیت ذخیره شد.', 'readysms') . '</p></div>';
        }
    }
}
add_action('admin_notices', 'readysms_admin_notices');

/**
 * Helper function to render navigation tabs.
 */
function readysms_render_admin_nav_tabs($current_page_slug = 'readysms-settings') {
    $tabs = [
        'readysms-settings'          => __('داشبورد', 'readysms'),
        'readysms-sms-settings'      => __('تنظیمات پیامک', 'readysms'),
        'readysms-google-settings'   => __('تنظیمات گوگل', 'readysms'),
        'readysms-form-settings'     => __('تنظیمات فرم', 'readysms'),
        'readysms-redirect-settings' => __('تغییر مسیرها', 'readysms'),
        'readysms-api-test'          => __('تست API', 'readysms'),
        'readysms-tools'             => __('ابزارها', 'readysms'),
    ];
    echo '<div class="dokme-container" style="margin:30px 0 20px;">';
    foreach ($tabs as $slug => $title) {
        $active_class = ($current_page_slug === $slug || ($slug === 'readysms-settings' && empty($current_page_slug) && $current_page_slug !== false )) ? 'active' : '';
        if ($slug === 'readysms-settings' && ($current_page_slug === 'readysms-settings' || empty($current_page_slug))) $active_class = 'active';

        echo '<div class="dokme ' . esc_attr($active_class) . '"><a href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($title) . '</a></div>';
    }
    echo '</div>';
}

/**
 * Render the main dashboard page.
 */
function readysms_render_dashboard_page() {
    $msgway_affiliate_link = 'https://www.msgway.com/r/lr';
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

        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'readysms-settings'); ?>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('راهنمای سریع و شروع به کار', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><strong><?php esc_html_e('برای استفاده کامل از امکانات افزونه ردی اس‌ام‌اس، مراحل زیر را دنبال کنید:', 'readysms'); ?></strong></p>
                <ul style="list-style-type: decimal; padding-right: 20px; margin-top:10px;">
                    <li><?php printf(wp_kses_post(__('<strong>تنظیمات پیامک:</strong> به بخش <a href="%s">تنظیمات پیامک</a> بروید و اطلاعات حساب کاربری خود در سامانه راه پیام (کلید API، شماره ارسال (اختیاری)، طول کد OTP، روش ارسال و کد پترن OTP) را وارد نمایید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-sms-settings'))); ?></li>
                     <li><?php printf(wp_kses_post(__('<strong>تنظیمات فرم و تغییر مسیر:</strong> از بخش‌های <a href="%1$s">تنظیمات فرم</a> (برای لوگو) و <a href="%2$s">تغییر مسیرها</a> برای سفارشی‌سازی بیشتر استفاده کنید.', 'readysms')), esc_url(admin_url('admin.php?page=readysms-form-settings')), esc_url(admin_url('admin.php?page=readysms-redirect-settings'))); ?></li>
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
                <p><?php esc_html_e('راه پیام با ارائه پنل کاربری ساده و API قدرتمند، امکان ارسال سریع و مطمئن پیامک و تماس صوتی را برای کسب‌وکارهای آنلاین فراهم می‌آورد.', 'readysms'); ?></p>
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
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات ورود با پیامک (راه پیام)', 'readysms'); ?>
        </h1>
        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''); ?>
        <form method="post" action="options.php">
            <?php settings_fields('readysms_sms_options_group'); ?>
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
                        <select id="ready_sms_otp_length" name="ready_sms_otp_length" class="regular-text" style="max-width: 150px;">
                            <?php
                            $current_length = get_option('ready_sms_otp_length', 6);
                            for ($i = 4; $i <= 7; $i++) {
                                echo '<option value="' . esc_attr($i) . '" ' . selected($current_length, $i, false) . '>' . sprintf(esc_html__('%s رقمی', 'readysms'), readysms_number_to_persian($i)) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('تعداد ارقام کد تایید که برای کاربر ارسال می‌شود (بین 4 تا 7 رقم).', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_send_method"><?php esc_html_e('روش ارسال کد تایید', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_send_method" name="ready_sms_send_method" class="regular-text" style="max-width: 200px;">
                            <?php $current_method = get_option('ready_sms_send_method', 'sms'); ?>
                            <option value="sms" <?php selected($current_method, 'sms'); ?>><?php esc_html_e('پیامک (SMS)', 'readysms'); ?></option>
                            <option value="ivr" <?php selected($current_method, 'ivr'); ?>><?php esc_html_e('تماس صوتی (IVR)', 'readysms'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('روش ارسال کد تایید به کاربر را انتخاب کنید.', 'readysms'); ?><br>
                            <?php esc_html_e('توجه: برای تماس صوتی، راه پیام از یک قالب صوتی ثابت (templateID: 2) استفاده می‌کند.', 'readysms'); ?>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_pattern_code"><?php esc_html_e('کد پترن پیامک (templateID)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_pattern_code" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php esc_attr_e('فقط برای روش پیامک (SMS) لازم است', 'readysms'); ?>">
                        <p class="description">
                            <?php esc_html_e('کد پترن (الگو) که در سامانه راه پیام برای ارسال پیامک OTP ثبت کرده‌اید (فقط در صورت انتخاب روش ارسال "پیامک" استفاده می‌شود).', 'readysms'); ?>
                            <?php esc_html_e('این پترن باید شامل یک پارامتر برای جایگذاری کد تایید باشد. مثال محتوای پترن در راه پیام:', 'readysms'); ?>
                            <div class="pattern-code-example" style="font-family: inherit; direction:rtl; text-align:right; white-space: pre-wrap; line-height: 1.8;"><code><?php
                                echo esc_html('بفرمائید کد تأیید: %param1%') . "\n";
                                echo esc_html(get_bloginfo('name'));
                            ?></code></div>
                             <p class="description"><?php esc_html_e('در مثال بالا، %param1% همان کد ورود تولید شده توسط افزونه خواهد بود.', 'readysms'); ?></p>
                        </p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="ready_sms_resend_timer"><?php esc_html_e('زمان ارسال مجدد کد (ثانیه)', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_resend_timer" name="ready_sms_resend_timer" class="regular-text" style="max-width: 200px;">
                            <?php
                            $current_timer = get_option('ready_sms_resend_timer', 120);
                            $timer_options = [
                                '30' => __('30 ثانیه', 'readysms'),
                                '60' => __('60 ثانیه (1 دقیقه)', 'readysms'),
                                '120' => __('120 ثانیه (2 دقیقه)', 'readysms'),
                                '180' => __('180 ثانیه (3 دقیقه)', 'readysms'),
                                '240' => __('240 ثانیه (4 دقیقه)', 'readysms')
                            ];
                            foreach ($timer_options as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($current_timer, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('مدت زمانی که کاربر باید برای درخواست مجدد کد OTP منتظر بماند.', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_sms_country_code_mode"><?php esc_html_e('فرمت شماره موبایل ورودی', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_country_code_mode" name="ready_sms_country_code_mode" class="regular-text" style="max-width: 300px;">
                            <?php $current_mode = get_option('ready_sms_country_code_mode', 'iran_only'); ?>
                            <option value="iran_only" <?php selected($current_mode, 'iran_only'); ?>><?php esc_html_e('فقط ایران (پیش‌شماره +98 خودکار یا توسط کاربر)', 'readysms'); ?></option>
                            <option value="all_countries" <?php selected($current_mode, 'all_countries'); ?>><?php esc_html_e('همه کشورها (کاربر باید پیش‌شماره را وارد کند)', 'readysms'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('اگر "فقط ایران" انتخاب شود، شماره‌های با فرمت 09 به طور خودکار با +98 برای API ارسال می‌شوند. کاربر می‌تواند با +98 هم وارد کند.', 'readysms'); ?><br>
                            <?php esc_html_e('اگر "همه کشورها" انتخاب شود، کاربر ملزم به وارد کردن شماره کامل با پیش‌شماره کشور (مانند +1 یا +44) است. شماره‌های با فرمت 09 نیز به عنوان ایرانی در نظر گرفته می‌شوند.', 'readysms'); ?>
                        </p>
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
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات ورود با گوگل', 'readysms'); ?>
        </h1>
        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''); ?>
        <form method="post" action="options.php">
            <?php settings_fields('readysms_google_options_group'); ?>
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
 * Render Form Settings Page.
 */
function readysms_render_form_settings_page() {
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات ظاهری فرم ورود', 'readysms'); ?>
        </h1>
        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''); ?>

        <form method="post" action="options.php">
            <?php settings_fields('readysms_form_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_form_logo_url"><?php esc_html_e('لوگوی فرم ورود', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_form_logo_url" name="ready_form_logo_url" value="<?php echo esc_attr(get_option('ready_form_logo_url')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php esc_attr_e('آدرس URL لوگو یا خالی برای عدم نمایش', 'readysms'); ?>">
                        <button type="button" class="button" id="upload_logo_button" style="margin-right: 5px;"><?php esc_html_e('انتخاب/آپلود لوگو', 'readysms'); ?></button>
                        <p class="description"><?php esc_html_e('آدرس URL لوگویی که می‌خواهید بالای فرم ورود نمایش داده شود. برای بهترین نتیجه از لوگو با پس‌زمینه شفاف (PNG) و عرض مناسب (مثلا حداکثر 150 پیکسل) استفاده کنید.', 'readysms'); ?></p>
                        <div id="logo_preview_wrapper" style="margin-top:15px; padding:10px; border:1px dashed var(--rs-border-color); border-radius:var(--rs-border-radius-md); min-height:50px; display:inline-block; background-color: #f9f9f9;">
                            <?php $logo_url = get_option('ready_form_logo_url'); ?>
                            <img src="<?php echo esc_url($logo_url); ?>" id="logo_preview" style="max-width:200px; max-height:100px; display: <?php echo $logo_url ? 'block' : 'none'; ?>;">
                        </div>
                        <?php if ($logo_url) : ?>
                            <button type="button" class="button button-link-delete" id="remove_logo_button" style="margin-right:10px; vertical-align: top;"><?php esc_html_e('حذف لوگو', 'readysms'); ?></button>
                        <?php else: ?>
                             <button type="button" class="button button-link-delete" id="remove_logo_button" style="margin-right:10px; vertical-align: top; display:none;"><?php esc_html_e('حذف لوگو', 'readysms'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('ذخیره تنظیمات فرم', 'readysms')); ?>
        </form>
    </div>
    <?php
}

/**
 * Render Redirect Settings Page.
 */
function readysms_render_redirect_settings_page() {
    ?>
     <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات تغییر مسیرها', 'readysms'); ?>
        </h1>
        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''); ?>
        <p><?php esc_html_e('در این بخش می‌توانید آدرس‌هایی را که کاربران پس از انجام عملیات مختلف به آن‌ها هدایت می‌شوند، مشخص کنید. اگر هر فیلد خالی بگذارید، از مقادیر پیش‌فرض افزونه یا وردپرس استفاده خواهد شد.', 'readysms'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('readysms_redirect_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_redirect_after_login"><?php esc_html_e('تغییر مسیر پس از ورود موفق', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_after_login" name="ready_redirect_after_login" value="<?php echo esc_attr(get_option('ready_redirect_after_login')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                        <p class="description"><?php esc_html_e('کاربر پس از ورود موفق با پیامک یا گوگل به این آدرس هدایت می‌شود. (پیش‌فرض: صفحه اصلی یا صفحه‌ای که از آن لاگین کرده)', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_redirect_after_register"><?php esc_html_e('تغییر مسیر پس از عضویت جدید', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_after_register" name="ready_redirect_after_register" value="<?php echo esc_attr(get_option('ready_redirect_after_register')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php esc_attr_e('خالی برای استفاده از "تغییر مسیر پس از ورود"', 'readysms'); ?>">
                        <p class="description"><?php esc_html_e('کاربر پس از عضویت جدید (اولین ورود) به این آدرس هدایت می‌شود. اگر خالی باشد، از آدرس "تغییر مسیر پس از ورود" استفاده می‌شود.', 'readysms'); ?></p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="ready_redirect_after_logout"><?php esc_html_e('تغییر مسیر پس از خروج', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_after_logout" name="ready_redirect_after_logout" value="<?php echo esc_attr(get_option('ready_redirect_after_logout')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                        <p class="description"><?php esc_html_e('کاربر پس از خروج از حساب کاربری به این آدرس هدایت می‌شود. (پیش‌فرض: صفحه ورود وردپرس یا صفحه اصلی)', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_redirect_my_account_link"><?php esc_html_e('لینک صفحه "حساب کاربری من"', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_my_account_link" name="ready_redirect_my_account_link" value="<?php echo esc_attr(get_option('ready_redirect_my_account_link')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php esc_attr_e('مثال: /my-account یا آدرس کامل صفحه ووکامرس', 'readysms'); ?>">
                        <p class="description"><?php esc_html_e('اگر از افزونه‌هایی مانند ووکامرس استفاده می‌کنید، آدرس صفحه "حساب کاربری من" را اینجا وارد کنید. این لینک ممکن است در پیام خوشامدگویی به کاربر لاگین شده (در شورت‌کد) استفاده شود.', 'readysms'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('ذخیره تنظیمات تغییر مسیر', 'readysms')); ?>
        </form>
    </div>
    <?php
}

/**
 * Render Tools Page.
 */
function readysms_render_tools_page() {
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('ابزارهای افزونه', 'readysms'); ?>
        </h1>
        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''); ?>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('خروجی کاربران', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('برای دریافت لیست کاربران سایت (شامل نام کاربری، ایمیل، نام نمایشی و تاریخ عضویت) با فرمت CSV، روی دکمه زیر کلیک کنید.', 'readysms'); ?></p>
                <p class="description"><?php esc_html_e('توجه: این عملیات ممکن است بسته به تعداد کاربران، کمی زمان‌بر باشد. فایل به صورت خودکار دانلود خواهد شد.', 'readysms'); ?></p>
                <button type="button" id="export_users_button" class="button button-primary" style="margin-top:10px;">
                    <span class="dashicons dashicons-database-export" style="vertical-align: middle; margin-left: 5px;"></span>
                    <?php esc_html_e('خروجی تمام کاربران (CSV)', 'readysms'); ?>
                </button>
                 <p id="export_users_result" style="margin-top:15px; font-style: italic;"></p>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('دریافت موجودی حساب راه پیام', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('برای مشاهده موجودی فعلی حساب خود در سامانه راه پیام، روی دکمه زیر کلیک کنید.', 'readysms'); ?></p>
                <button type="button" id="admin_get_balance_button" class="button button-secondary">
                    <span class="dashicons dashicons-money-alt" style="vertical-align: middle; margin-left: 5px;"></span>
                    <?php esc_html_e('دریافت موجودی حساب', 'readysms'); ?>
                </button>
                <div id="admin_balance_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render API Test page.
 */
function readysms_render_api_test_page() {
    $current_otp_length = (int) get_option('ready_sms_otp_length', 6);
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="<?php esc_attr_e('لوگوی ردی استودیو', 'readysms'); ?>" class="readysms-header-logo">
            <?php esc_html_e('تست API سامانه راه پیام', 'readysms'); ?>
        </h1>
        <?php readysms_render_admin_nav_tabs(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''); ?>
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
                <button type="button" id="admin_send_test_otp_button" class="button button-primary"><?php esc_html_e('ارسال پیامک/تماس OTP آزمایشی', 'readysms'); ?></button>
                 <p class="description"><?php printf(esc_html__('کد آزمایشی بر اساس "روش ارسال کد" انتخاب شده در %s ارسال خواهد شد.', 'readysms'), '<a href="'.esc_url(admin_url('admin.php?page=readysms-sms-settings')).'">'.esc_html__('تنظیمات پیامک', 'readysms').'</a>'); ?></p>
                
                <div id="admin_verify_otp_section" style="display: none; margin-top: 25px; padding-top:20px; border-top: 1px dashed var(--rs-border-color);">
                    <table class="form-table" style="box-shadow:none; border:none; background:transparent;">
                        <tr valign="top" style="border-bottom:none;">
                            <th scope="row"><label for="admin_test_otp_code"><?php esc_html_e('کد OTP دریافتی', 'readysms'); ?></label></th>
                            <td><input type="text" id="admin_test_otp_code" placeholder="<?php printf(esc_attr__('کد %s رقمی', 'readysms'), readysms_number_to_persian($current_otp_length)); ?>" class="regular-text ltr-code" dir="ltr" maxlength="<?php echo esc_attr($current_otp_length); ?>"></td>
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
                            <p class="description"><?php esc_html_e('این شناسه (referenceID) پس از ارسال موفقیت‌آمیز پیامک توسط API راه پیام بازگردانده می‌شود.', 'readysms'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="admin_check_status_button" class="button button-secondary"><?php esc_html_e('بررسی وضعیت', 'readysms'); ?></button>
                <div id="admin_status_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
            </div>
        </div>
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('3. تست دریافت اطلاعات قالب پیام (مخصوص پیامک)', 'readysms'); ?></span></h2>
            <div class="inside">
                 <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="admin_template_id_test"><?php esc_html_e('شناسه قالب (Template ID)', 'readysms'); ?></label></th>
                        <td>
                            <input type="text" id="admin_template_id_test" placeholder="<?php esc_attr_e('کد پترن وارد شده در تنظیمات', 'readysms'); ?>" class="regular-text ltr-code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" dir="ltr">
                             <p class="description"><?php esc_html_e('شناسه قالبی که می‌خواهید اطلاعات آن را از راه پیام دریافت کنید (فقط برای پیامک).', 'readysms'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="admin_get_template_button" class="button button-secondary"><?php esc_html_e('دریافت اطلاعات قالب', 'readysms'); ?></button>
                <div id="admin_template_result" class="api-test-result" style="margin-top: 15px; display:none;"></div>
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
        (isset($_GET['page']) && in_array(sanitize_text_field($_GET['page']), ['readysms-settings', 'readysms-sms-settings', 'readysms-google-settings', 'readysms-form-settings', 'readysms-redirect-settings', 'readysms-api-test', 'readysms-tools']))
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

/**
 * Helper function to convert English numbers to Persian for display.
 */
if (!function_exists('readysms_number_to_persian')) {
    function readysms_number_to_persian($number) {
        $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($english_digits, $persian_digits, (string) $number);
    }
}
?>
