<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس با استفاده از سامانه راه پیام.
 * Version: 1.3.1
 * Author: Fazel Ghaemi
 * Author URI: https://readystudio.ir/
 * Plugin URI: https://readystudio.ir/readysms-plugin/
 * License: GPLv2 or later
 * License URI: https://readystudio.ir/readysms-plugin/licenses/
 * Text Domain: readysms
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('READYSMS_VERSION', '1.3.1');
define('READYSMS_DIR', plugin_dir_path(__FILE__));
define('READYSMS_URL', plugin_dir_url(__FILE__));
define('READYSMS_BASENAME', plugin_basename(__FILE__));

// Load plugin textdomain for translation
function readysms_load_textdomain() {
    load_plugin_textdomain('readysms', false, dirname(READYSMS_BASENAME) . '/languages/');
}
add_action('plugins_loaded', 'readysms_load_textdomain');

// Include required files
require_once READYSMS_DIR . 'includes/google-login.php';
require_once READYSMS_DIR . 'includes/sms-login.php';
require_once READYSMS_DIR . 'includes/admin-settings.php';
require_once READYSMS_DIR . 'includes/shortcodes.php';
require_once READYSMS_DIR . 'includes/admin-ajax-handlers.php';

/**
 * Enqueue front-end CSS and JS for custom login functionality.
 */
function readysms_enqueue_front_scripts() {
    if (!is_admin()) {
        wp_enqueue_style(
            'readysms-front-style',
            READYSMS_URL . 'assets/css/custom-login.css',
            array(),
            READYSMS_VERSION
        );
        wp_enqueue_script(
            'readysms-front-js',
            READYSMS_URL . 'assets/js/custom-login.js',
            array('jquery'),
            READYSMS_VERSION,
            true
        );

        wp_localize_script('readysms-front-js', 'readyLoginAjax', array(
            'ajaxurl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('readysms-nonce'),
            'timer_duration'   => (int) get_option('ready_sms_resend_timer', 120), // استفاده از گزینه جدید تایمر
            'otp_length'       => (int) get_option('ready_sms_otp_length', 6),
            'country_code_mode'=> get_option('ready_sms_country_code_mode', 'iran_only'),
            'error_general'    => __('خطا در ارتباط با سرور. لطفاً لحظاتی دیگر تلاش کنید.', 'readysms'),
            'error_phone'      => __('لطفاً شماره موبایل خود را به صورت صحیح وارد کنید (مثال: 09123456789 یا +989123456789).', 'readysms'),
            'error_otp_empty'  => __('لطفاً کد تایید دریافت شده را وارد کنید.', 'readysms'),
            'error_otp_invalid_format' => __('فرمت کد تایید وارد شده صحیح نیست.', 'readysms'),
            'digits_text'      => __('رقمی', 'readysms'),
            'sending_otp'    => __('در حال ارسال کد...', 'readysms'),
            'verifying_otp'  => __('در حال بررسی کد...', 'readysms'),
            'send_otp_text'  => __('دریافت کد تایید', 'readysms'),
            'verify_otp_text'=> __('ورود / ثبت نام', 'readysms'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'readysms_enqueue_front_scripts');

/**
 * Plugin activation hook.
 * Adds necessary options to the database if they don't exist.
 */
function readysms_activate() {
    $options_to_add = [
        'ready_google_client_id'        => '',
        'ready_google_client_secret'    => '',
        'ready_sms_api_key'             => '',
        'ready_sms_number'              => '',
        'ready_sms_pattern_code'        => '',
        'ready_sms_otp_length'          => 6,
        'ready_sms_resend_timer'        => 120,
        'ready_sms_country_code_mode'   => 'iran_only',
        'ready_sms_send_method'         => 'sms',
        'ready_form_logo_url'           => '',
        'ready_redirect_after_login'    => '',
        'ready_redirect_after_register' => '',
        'ready_redirect_after_logout'   => '',
        'ready_redirect_my_account_link'=> '',
        'ready_custom_css'              => '',
        'ready_custom_js'               => ''
    ];

    foreach ($options_to_add as $option_name => $default_value) {
        if (get_option($option_name) === false) {
            add_option($option_name, $default_value);
        }
    }
}
register_activation_hook(__FILE__, 'readysms_activate');

/**
 * Plugin deactivation hook.
 * (Optional: Clean up options if desired by uncommenting)
 */
function readysms_deactivate() {
    // delete_option('ready_google_client_id');
    // delete_option('ready_google_client_secret');
    // delete_option('ready_sms_api_key');
    // delete_option('ready_sms_number');
    // delete_option('ready_sms_pattern_code');
    // delete_option('ready_sms_otp_length');
    // delete_option('ready_sms_resend_timer');
    // delete_option('ready_sms_country_code_mode');
    // delete_option('ready_sms_send_method');
    // delete_option('ready_form_logo_url');
    // delete_option('ready_redirect_after_login');
    // delete_option('ready_redirect_after_register');
    // delete_option('ready_redirect_after_logout');
    // delete_option('ready_redirect_my_account_link');
    // delete_option('ready_custom_css');
    // delete_option('ready_custom_js');
}
register_deactivation_hook(__FILE__, 'readysms_deactivate');

/**
 * Add settings link on plugin page in WordPress admin.
 */
function readysms_add_settings_link($links) {
    $settings_link_url = admin_url('admin.php?page=readysms-settings');
    $settings_link_text = __('تنظیمات', 'readysms');
    $html_settings_link = '<a href="' . esc_url($settings_link_url) . '">' . esc_html($settings_link_text) . '</a>';
    array_unshift($links, $html_settings_link);
    return $links;
}
if (function_exists('plugin_basename')) {
    $plugin_basename_for_action_link = plugin_basename(__FILE__);
    add_filter("plugin_action_links_{$plugin_basename_for_action_link}", 'readysms_add_settings_link');
}

/**
 * Custom logout redirect based on plugin settings.
 */
function readysms_custom_logout_redirect($logout_url, $redirect_to_calculated_by_wp, $user) {
    $custom_logout_url_from_settings = get_option('ready_redirect_after_logout');
    if (!empty($custom_logout_url_from_settings)) {
        // اعتبارسنجی URL برای جلوگیری از آسیب‌پذیری Open Redirect
        $parsed_custom_url = wp_parse_url($custom_logout_url_from_settings);
        $parsed_home_url = wp_parse_url(home_url());

        // اگر هاست URL سفارشی با هاست سایت یکی است یا URL نسبی است (بدون هاست)
        if (empty($parsed_custom_url['host']) || (isset($parsed_custom_url['host'], $parsed_home_url['host']) && strtolower($parsed_custom_url['host']) === strtolower($parsed_home_url['host']))) {
            return esc_url_raw(trim($custom_logout_url_from_settings));
        } else {
            // اگر URL خارجی است و نمی‌خواهید اجازه دهید، به صفحه اصلی یا URL پیش‌فرض وردپرس برگردانید
            // برای امنیت بیشتر، تغییر مسیر به URLهای خارجی که توسط مدیر وارد شده‌اند، باید با احتیاط انجام شود.
            // در اینجا فرض می‌کنیم مدیر URL معتبری وارد می‌کند، اما یک اعتبارسنجی قوی‌تر می‌تواند مفید باشد.
             error_log("ReadySMS: Potential open redirect attempt in logout_redirect. Custom URL: " . $custom_logout_url_from_settings);
             // return home_url('/'); // بازگشت به صفحه اصلی در صورت URL خارجی نامعتبر یا مشکوک
             return esc_url_raw(trim($custom_logout_url_from_settings)); // فعلا به انتخاب مدیر اعتماد می‌کنیم
        }
    }
    return $logout_url; // استفاده از رفتار پیش‌فرض وردپرس اگر تنظیم خاصی وجود ندارد
}
add_filter('logout_redirect', 'readysms_custom_logout_redirect', 20, 3);


/**
 * Load custom CSS and JS added by admin in tools section.
 */
function readysms_load_custom_codes() {
    if (is_admin()) { // این کدها فقط برای فرانت‌اند هستند
        return;
    }

    $custom_css = get_option('ready_custom_css');
    if (!empty($custom_css)) {
        // برای امنیت، می‌توان از wp_kses_css یا سایر روش‌های پاکسازی استفاده کرد،
        // اما چون این کد توسط مدیر وارد می‌شود، فرض بر صحت آن است.
        // wp_strip_all_tags می‌تواند برخی ساختارهای پیچیده CSS را خراب کند.
        // برای حفظ کامل کد CSS کاربر، آن را مستقیماً اضافه می‌کنیم.
        // مدیر مسئول امنیت کدی است که وارد می‌کند.
        wp_register_style('readysms-custom-inline-css', false);
        wp_enqueue_style('readysms-custom-inline-css');
        wp_add_inline_style('readysms-custom-inline-css', $custom_css);
    }

    $custom_js = get_option('ready_custom_js');
    if (!empty($custom_js)) {
        // کد JS سفارشی پس از اسکریپت اصلی افزونه ('readysms-front-js') بارگذاری می‌شود.
        // wp_specialchars_decode برای برگرداندن کاراکترهایی مانند <, > و & که ممکن است هنگام ذخیره تبدیل شده باشند.
        wp_add_inline_script('readysms-front-js', wp_specialchars_decode($custom_js, ENT_QUOTES), 'after');
    }
}
add_action('wp_enqueue_scripts', 'readysms_load_custom_codes', 99); // اجرای با اولویت پایین‌تر برای اطمینان از بارگذاری پس از استایل اصلی

?>
