<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس با استفاده از سامانه راه پیام.
 * Version: 1.3.0 
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

define('READYSMS_VERSION', '1.3.0'); // نسخه به‌روز شده
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
            'country_code_mode'=> get_option('ready_sms_country_code_mode', 'iran_only'), // ارسال حالت کد کشور
            'error_general'    => __('خطا در ارتباط با سرور. لطفاً لحظاتی دیگر تلاش کنید.', 'readysms'),
            'error_phone'      => __('لطفاً شماره موبایل خود را به صورت صحیح وارد کنید (مثال: 09123456789 یا +989123456789).', 'readysms'), // به‌روزرسانی پیام
            'error_otp_empty'  => __('لطفاً کد تایید دریافت شده را وارد کنید.', 'readysms'),
            'error_otp_invalid_format' => __('فرمت کد تایید وارد شده صحیح نیست.', 'readysms'),
            'digits_text'      => __('رقمی', 'readysms'),
            'sending_otp'      => __('در حال ارسال کد...', 'readysms'),
            'verifying_otp'    => __('در حال بررسی کد...', 'readysms'),
            'send_otp_text'    => __('دریافت کد تایید', 'readysms'),
            'verify_otp_text'  => __('ورود / ثبت نام', 'readysms'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'readysms_enqueue_front_scripts');

/**
 * Plugin activation hook.
 */
function readysms_activate() {
    // Google Login Options
    if (get_option('ready_google_client_id') === false) add_option('ready_google_client_id', '');
    if (get_option('ready_google_client_secret') === false) add_option('ready_google_client_secret', '');

    // SMS Login Options
    if (get_option('ready_sms_api_key') === false) add_option('ready_sms_api_key', '');
    if (get_option('ready_sms_number') === false) add_option('ready_sms_number', '');
    if (get_option('ready_sms_pattern_code') === false) add_option('ready_sms_pattern_code', '');
    if (get_option('ready_sms_otp_length') === false) add_option('ready_sms_otp_length', 6);
    if (get_option('ready_sms_resend_timer') === false) add_option('ready_sms_resend_timer', 120); // گزینه جدید تایمر ارسال مجدد
    if (get_option('ready_sms_country_code_mode') === false) add_option('ready_sms_country_code_mode', 'iran_only'); // گزینه جدید حالت کد کشور

    // Form Settings Options
    if (get_option('ready_form_logo_url') === false) add_option('ready_form_logo_url', '');

    // Redirect Options
    if (get_option('ready_redirect_after_login') === false) add_option('ready_redirect_after_login', '');
    if (get_option('ready_redirect_after_register') === false) add_option('ready_redirect_after_register', '');
    if (get_option('ready_redirect_after_logout') === false) add_option('ready_redirect_after_logout', '');
    if (get_option('ready_redirect_my_account_link') === false) add_option('ready_redirect_my_account_link', '');
}
register_activation_hook(__FILE__, 'readysms_activate');

/**
 * Plugin deactivation hook.
 */
function readysms_deactivate() {
    // Optional: delete options on deactivation
    // delete_option('ready_sms_resend_timer');
    // delete_option('ready_sms_country_code_mode');
    // delete_option('ready_form_logo_url');
    // delete_option('ready_redirect_after_login');
    // ... and so on for other options
}
register_deactivation_hook(__FILE__, 'readysms_deactivate');

/**
 * Add settings link on plugin page.
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
        // Validate that it's a local URL or an allowed one to prevent open redirect vulnerabilities
        $parsed_url = wp_parse_url($custom_logout_url_from_settings);
        if (!empty($parsed_url['host']) && strtolower($parsed_url['host']) !== strtolower(wp_parse_url(home_url(), PHP_URL_HOST))) {
            // If it's an external URL and you don't want to allow it, return default or home_url()
            // For now, we assume admin enters valid URLs. esc_url_raw was used for saving.
            return esc_url_raw($custom_logout_url_from_settings);
        }
        return esc_url_raw(trim($custom_logout_url_from_settings));
    }
    // If $redirect_to_calculated_by_wp is set (e.g., from a link ?redirect_to=...), WP uses that.
    // If not, $logout_url might point to wp-login.php?loggedout=true.
    // If you want to override WP's default when your setting is empty, you can return home_url() here.
    return $logout_url; 
}
add_filter('logout_redirect', 'readysms_custom_logout_redirect', 20, 3); // Priority 20 to run after most defaults

?>
