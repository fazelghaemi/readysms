<?php
// File: includes/admin-settings.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin scripts and styles.
 */
function readysms_enqueue_admin_assets($hook_suffix) {
    // ... (کد این تابع مشابه نسخه‌های قبلی است، فقط بخش wp_localize_script به‌روز می‌شود) ...
    // ... (اطمینان حاصل کنید که wp_enqueue_media() برای آپلودر لوگو فراخوانی می‌شود اگر صفحه مربوطه باز است)

    $plugin_page_slug_base = 'readysms-settings';
    $load_assets = false;
    // ... (منطق $load_assets مشابه قبل) ...
    $current_page_query = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if (strpos($hook_suffix, 'readysms-') !== false || in_array($current_page_query, ['readysms-settings', 'readysms-google-settings', 'readysms-sms-settings', 'readysms-redirect-settings', 'readysms-tools', 'readysms-form-settings'])) {
        $load_assets = true;
    }


    if (!$load_assets) {
        return;
    }

    // Enqueue WordPress media uploader scripts if on the relevant settings page
    if ($current_page_query === 'readysms-form-settings') { // یا هر اسلاگی که برای تنظیمات فرم و لوگو در نظر می‌گیرید
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
        'export_users_action'     => 'readysms_export_users', // اکشن جدید برای خروجی کاربران
        'otp_length'              => (int) get_option('ready_sms_otp_length', 6),
        'msg_fill_phone'          => __('لطفا شماره تلفن را برای تست وارد کنید.', 'readysms'),
        'msg_fill_otp'            => __('لطفا کد OTP دریافتی را وارد کنید.', 'readysms'),
        'msg_fill_otp_len_invalid'=> __('فرمت کد OTP صحیح نیست.', 'readysms'),
        'msg_fill_ref_id'         => __('لطفا شناسه مرجع را وارد کنید.', 'readysms'),
        'msg_fill_template_id'    => __('لطفا شناسه قالب را وارد کنید.', 'readysms'),
        'msg_unexpected_error'    => __('یک خطای پیش‌بینی نشده رخ داد. کنسول مرورگر و لاگ خطای PHP را بررسی کنید.', 'readysms'),
        'exporting_users_text'    => __('در حال آماده‌سازی خروجی...', 'readysms'),
        'export_users_btn_text'   => __('خروجی کاربران (CSV)', 'readysms'),
        // ... سایر رشته‌های localize شده قبلی ...
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
        'readysms-settings', // Main slug
        'readysms_render_dashboard_page',
        READYSMS_URL . 'assets/readysms-icon.svg',
        30
    );
    
    add_submenu_page(
        'readysms-settings',
        __('داشبورد', 'readysms'),
        __('داشبورد', 'readysms'),
        'manage_options',
        'readysms-settings', // Same as parent
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

    // صفحه جدید: تنظیمات فرم ورود
    add_submenu_page(
        'readysms-settings',
        __('تنظیمات فرم ورود', 'readysms'),
        __('تنظیمات فرم', 'readysms'),
        'manage_options',
        'readysms-form-settings', // New slug
        'readysms_render_form_settings_page'
    );
    
    // صفحه جدید: تنظیمات تغییر مسیر
    add_submenu_page(
        'readysms-settings',
        __('تنظیمات تغییر مسیر', 'readysms'),
        __('تغییر مسیرها', 'readysms'),
        'manage_options',
        'readysms-redirect-settings', // New slug
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

    // صفحه جدید: ابزارها (برای خروجی کاربران)
     add_submenu_page(
        'readysms-settings',
        __('ابزارها', 'readysms'),
        __('ابزارها', 'readysms'),
        'manage_options',
        'readysms-tools', // New slug
        'readysms_render_tools_page'
    );
}
add_action('admin_menu', 'readysms_add_admin_menu');

/**
 * Register plugin settings.
 */
function readysms_register_settings() {
    // SMS Settings (گروه قبلی)
    register_setting('readysms_sms_options_group', 'ready_sms_api_key', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_sms_options_group', 'ready_sms_number', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_sms_options_group', 'ready_sms_pattern_code', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_sms_options_group', 'ready_sms_otp_length', ['sanitize_callback' => 'readysms_sanitize_otp_length', 'default' => 6, 'type' => 'integer']);
    // تغییر تایمر ارسال مجدد کد (مورد 3)
    register_setting('readysms_sms_options_group', 'ready_sms_resend_timer', ['sanitize_callback' => 'readysms_sanitize_resend_timer', 'default' => 120, 'type' => 'integer']);
    // تنظیم کد کشور (مورد 4)
    register_setting('readysms_sms_options_group', 'ready_sms_country_code_mode', ['sanitize_callback' => 'sanitize_text_field', 'default' => 'iran_only']);


    // Google Settings (گروه قبلی)
    register_setting('readysms_google_options_group', 'ready_google_client_id', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('readysms_google_options_group', 'ready_google_client_secret', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);

    // Form Settings (گروه جدید - مورد 2)
    register_setting('readysms_form_options_group', 'ready_form_logo_url', ['sanitize_callback' => 'esc_url_raw', 'default' => '']);

    // Redirect Settings (گروه جدید - مورد 1)
    register_setting('readysms_redirect_options_group', 'ready_redirect_after_login', ['sanitize_callback' => 'esc_url_raw', 'default' => '']);
    register_setting('readysms_redirect_after_register', ['sanitize_callback' => 'esc_url_raw', 'default' => '']); // معمولا مشابه لاگین یا صفحه خاص
    register_setting('readysms_redirect_after_logout', ['sanitize_callback' => 'esc_url_raw', 'default' => '']);
    register_setting('readysms_redirect_my_account_link', ['sanitize_callback' => 'esc_url_raw', 'default' => '']); // لینک صفحه حساب کاربری
    // redirect_forgot_password نیاز به بررسی بیشتر دارد چون افزونه این قابلیت را مستقیما ندارد
}
add_action('admin_init', 'readysms_register_settings');

// تابع اعتبارسنجی برای تایمر ارسال مجدد
function readysms_sanitize_resend_timer($input) {
    $allowed_values = [30, 60, 120, 190]; // مقادیر مجاز ثانیه
    $input = absint($input);
    if (in_array($input, $allowed_values, true)) {
        return $input;
    }
    return 120; // مقدار پیش‌فرض در صورت ورودی نامعتبر
}

// ... (سایر توابع مثل readysms_sanitize_otp_length و readysms_admin_notices مشابه قبل باقی می‌مانند) ...
// (تابع readysms_render_dashboard_page, readysms_render_sms_settings_page, readysms_render_google_settings_page, readysms_render_api_test_page, readysms_admin_footer_text مشابه قبل با تغییرات جزئی برای هماهنگی منوها)

/**
 * Render SMS settings page.
 */
function readysms_render_sms_settings_page() {
    // ... (بخش‌های بالایی تابع مشابه قبل) ...
    ?>
    <div class="wrap readysms-wrap">
        <h1></h1>
        <div class="dokme-container"></div>
        <form method="post" action="options.php">
            <?php settings_fields('readysms_sms_options_group'); ?>
            <table class="form-table">
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
                    <th scope="row"><label for="ready_sms_pattern_code"><?php esc_html_e('کد پترن OTP (templateID)', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_sms_pattern_code" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text ltr-code" dir="ltr" required placeholder="12345">
                        <p class="description">
                            <?php esc_html_e('کد پترن (الگو) که در سامانه راه پیام برای ارسال پیامک OTP ثبت کرده‌اید.', 'readysms'); ?>
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
                    <th scope="row"><label for="ready_sms_otp_length"><?php esc_html_e('طول کد تایید (OTP)', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_otp_length" name="ready_sms_otp_length">
                            <?php
                            $current_length = get_option('ready_sms_otp_length', 6);
                            for ($i = 4; $i <= 7; $i++) {
                                echo '<option value="' . esc_attr($i) . '" ' . selected($current_length, $i, false) . '>' . sprintf(esc_html__('%d رقمی', 'readysms'), $i) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('تعداد ارقام کد تایید پیامکی که برای کاربر ارسال می‌شود (بین 4 تا 7 رقم).', 'readysms'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="ready_sms_resend_timer"><?php esc_html_e('زمان ارسال مجدد کد (ثانیه)', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_resend_timer" name="ready_sms_resend_timer">
                            <?php
                            $current_timer = get_option('ready_sms_resend_timer', 120);
                            $timer_options = [
                                '30' => __('30 ثانیه', 'readysms'),
                                '60' => __('60 ثانیه', 'readysms'),
                                '120' => __('120 ثانیه (2 دقیقه)', 'readysms'),
                                '190' => __('190 ثانیه (بیش از 3 دقیقه)', 'readysms') // در اصل 180 مرسوم‌تر است
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
                    <th scope="row"><label for="ready_sms_country_code_mode"><?php esc_html_e('فرمت شماره موبایل', 'readysms'); ?></label></th>
                    <td>
                        <select id="ready_sms_country_code_mode" name="ready_sms_country_code_mode">
                            <?php $current_mode = get_option('ready_sms_country_code_mode', 'iran_only'); ?>
                            <option value="iran_only" <?php selected($current_mode, 'iran_only'); ?>><?php esc_html_e('فقط ایران (+98)', 'readysms'); ?></option>
                            <option value="all_countries" <?php selected($current_mode, 'all_countries'); ?>><?php esc_html_e('همه کشورها (نیاز به ورود کد کشور توسط کاربر)', 'readysms'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('اگر "فقط ایران" انتخاب شود، شماره‌های بدون پیش‌شماره با +98 ارسال می‌شوند.', 'readysms'); ?><br>
                            <?php esc_html_e('اگر "همه کشورها" انتخاب شود، کاربر باید شماره را با کد کشور (مثلا +1 یا +44) وارد کند یا در صورت ورود با 0، پیش‌فرض ایران در نظر گرفته می‌شود.', 'readysms'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('ذخیره تنظیمات پیامک', 'readysms')); ?>
        </form>
    </div>
    <?php
}

/**
 * Render Form Settings Page (مورد 2)
 */
function readysms_render_form_settings_page() {
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
     ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات ظاهری فرم ورود', 'readysms'); ?>
        </h1>
        <div class="dokme-container" style="margin:30px 0 20px;">
            <div class="dokme <?php echo ($current_page_slug === 'readysms-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a></div>
            <div class="dokme active"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-form-settings')); ?>"><?php esc_html_e('تنظیمات فرم', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-redirect-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-redirect-settings')); ?>"><?php esc_html_e('تغییر مسیرها', 'readysms'); ?></a></div>
             <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-tools') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-tools')); ?>"><?php esc_html_e('ابزارها', 'readysms'); ?></a></div>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('readysms_form_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_form_logo_url"><?php esc_html_e('لوگوی فرم ورود', 'readysms'); ?></label></th>
                    <td>
                        <input type="text" id="ready_form_logo_url" name="ready_form_logo_url" value="<?php echo esc_attr(get_option('ready_form_logo_url')); ?>" class="regular-text">
                        <button type="button" class="button" id="upload_logo_button"><?php esc_html_e('انتخاب/آپلود لوگو', 'readysms'); ?></button>
                        <p class="description"><?php esc_html_e('آدرس URL لوگویی که می‌خواهید بالای فرم ورود نمایش داده شود. برای بهترین نتیجه از لوگو با پس‌زمینه شفاف (PNG) استفاده کنید.', 'readysms'); ?></p>
                        <div id="logo_preview_wrapper" style="margin-top:10px;">
                            <?php $logo_url = get_option('ready_form_logo_url'); ?>
                            <?php if ($logo_url) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" id="logo_preview" style="max-width:200px; max-height:100px; border:1px solid #ddd; padding:5px;">
                                <button type="button" class="button button-link-delete" id="remove_logo_button" style="margin-right:10px;"><?php esc_html_e('حذف لوگو', 'readysms'); ?></button>
                            <?php else: ?>
                                <img src="#" id="logo_preview" style="max-width:200px; max-height:100px; border:1px solid #ddd; padding:5px; display:none;">
                                 <button type="button" class="button button-link-delete" id="remove_logo_button" style="margin-right:10px; display:none;"><?php esc_html_e('حذف لوگو', 'readysms'); ?></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                 </table>
            <?php submit_button(__('ذخیره تنظیمات فرم', 'readysms')); ?>
        </form>
    </div>
    <?php
}


/**
 * Render Redirect Settings Page (مورد 1)
 */
function readysms_render_redirect_settings_page() {
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    ?>
     <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="" class="readysms-header-logo">
            <?php esc_html_e('تنظیمات تغییر مسیرها', 'readysms'); ?>
        </h1>
         <div class="dokme-container" style="margin:30px 0 20px;">
            <div class="dokme <?php echo ($current_page_slug === 'readysms-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-form-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-form-settings')); ?>"><?php esc_html_e('تنظیمات فرم', 'readysms'); ?></a></div>
            <div class="dokme active"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-redirect-settings')); ?>"><?php esc_html_e('تغییر مسیرها', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-tools') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-tools')); ?>"><?php esc_html_e('ابزارها', 'readysms'); ?></a></div>
        </div>
        <p><?php esc_html_e('در این بخش می‌توانید آدرس‌هایی را که کاربران پس از انجام عملیات مختلف به آن‌ها هدایت می‌شوند، مشخص کنید. اگر خالی بگذارید، از مقادیر پیش‌فرض استفاده خواهد شد.', 'readysms'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('readysms_redirect_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="ready_redirect_after_login"><?php esc_html_e('تغییر مسیر پس از ورود', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_after_login" name="ready_redirect_after_login" value="<?php echo esc_attr(get_option('ready_redirect_after_login')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                        <p class="description"><?php esc_html_e('کاربر پس از ورود موفق به این آدرس هدایت می‌شود. (پیش‌فرض: صفحه اصلی)', 'readysms'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="ready_redirect_after_register"><?php esc_html_e('تغییر مسیر پس از عضویت', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_after_register" name="ready_redirect_after_register" value="<?php echo esc_attr(get_option('ready_redirect_after_register')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                        <p class="description"><?php esc_html_e('کاربر پس از عضویت موفق به این آدرس هدایت می‌شود. (پیش‌فرض: همان آدرس پس از ورود یا صفحه اصلی)', 'readysms'); ?></p>
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
                    <th scope="row"><label for="ready_redirect_my_account_link"><?php esc_html_e('لینک صفحه حساب کاربری من', 'readysms'); ?></label></th>
                    <td>
                        <input type="url" id="ready_redirect_my_account_link" name="ready_redirect_my_account_link" value="<?php echo esc_attr(get_option('ready_redirect_my_account_link')); ?>" class="regular-text ltr-code" dir="ltr" placeholder="<?php esc_attr_e('مثال: /my-account یا آدرس کامل', 'readysms'); ?>">
                        <p class="description"><?php esc_html_e('اگر از افزونه‌هایی مانند ووکامرس استفاده می‌کنید، آدرس صفحه "حساب کاربری من" را اینجا وارد کنید. این لینک ممکن است در بخش‌هایی از افزونه (مثلا پس از ورود) استفاده شود.', 'readysms'); ?></p>
                    </td>
                </tr>
                 </table>
            <?php submit_button(__('ذخیره تنظیمات تغییر مسیر', 'readysms')); ?>
        </form>
    </div>
    <?php
}

/**
 * Render Tools Page (مورد 7)
 */
function readysms_render_tools_page() {
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    ?>
    <div class="wrap readysms-wrap">
        <h1>
            <img src="<?php echo esc_url(READYSMS_URL . 'assets/readystudio-logo-square.png'); ?>" alt="" class="readysms-header-logo">
            <?php esc_html_e('ابزارهای افزونه', 'readysms'); ?>
        </h1>
        <div class="dokme-container" style="margin:30px 0 20px;">
            <div class="dokme <?php echo ($current_page_slug === 'readysms-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-settings')); ?>"><?php esc_html_e('داشبورد', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-sms-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-sms-settings')); ?>"><?php esc_html_e('تنظیمات پیامک', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-google-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-google-settings')); ?>"><?php esc_html_e('تنظیمات گوگل', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-form-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-form-settings')); ?>"><?php esc_html_e('تنظیمات فرم', 'readysms'); ?></a></div>
            <div class="dokme <?php echo ($current_page_slug === 'readysms-redirect-settings') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-redirect-settings')); ?>"><?php esc_html_e('تغییر مسیرها', 'readysms'); ?></a></div>
             <div class="dokme <?php echo ($current_page_slug === 'readysms-api-test') ? 'active' : ''; ?>"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-api-test')); ?>"><?php esc_html_e('تست API', 'readysms'); ?></a></div>
            <div class="dokme active"><a href="<?php echo esc_url(admin_url('admin.php?page=readysms-tools')); ?>"><?php esc_html_e('ابزارها', 'readysms'); ?></a></div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('خروجی کاربران', 'readysms'); ?></span></h2>
            <div class="inside">
                <p><?php esc_html_e('برای دریافت لیست کاربران ثبت‌نام شده در سایت (شامل نام کاربری، ایمیل، و شماره موبایل در صورت وجود به عنوان نام کاربری) با فرمت CSV، روی دکمه زیر کلیک کنید.', 'readysms'); ?></p>
                <button type="button" id="export_users_button" class="button button-primary">
                    <?php esc_html_e('خروجی کاربران (CSV)', 'readysms'); ?>
                </button>
                 <p id="export_users_result" style="margin-top:10px;"></p>
            </div>
        </div>
    </div>
    <?php
}

// توابع readysms_render_dashboard_page, readysms_render_google_settings_page, readysms_render_api_test_page و readysms_admin_footer_text
// مشابه نسخه‌های قبلی باقی می‌مانند، فقط لینک‌های منوهای ناوبری داخلی آن‌ها باید با صفحات جدید هماهنگ شوند.
// (برای اختصار اینجا تکرار نشده‌اند)
// تابع readysms_sanitize_otp_length نیز بدون تغییر باقی می‌ماند.

?>
