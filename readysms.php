<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس با استفاده از سامانه راه پیام.
 * Version: 1.2.1 
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

define('READYSMS_VERSION', '1.2.1'); // نسخه به‌روز شده برای تغییرات اخیر
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
    // اسکریپت‌ها و استایل‌ها فقط در صورتی که کاربر لاگین نکرده باشد یا در صفحه‌ای که شورت‌کد وجود دارد، بارگذاری شوند (بهینه‌سازی)
    // این بهینه‌سازی نیاز به بررسی وجود شورت‌کد در محتوای صفحه دارد.
    // برای سادگی، فعلاً در تمام صفحات فرانت‌اند بارگذاری می‌کنیم، مگر اینکه کاربر ادمین باشد.
    if (!is_admin()) {
        wp_enqueue_style(
            'readysms-front-style',
            READYSMS_URL . 'assets/css/custom-login.css',
            array(),
            READYSMS_VERSION
        );
        wp_enqueue_script(
            'readysms-front-js', // نام handle برای جاوااسکریپت فرانت‌اند
            READYSMS_URL . 'assets/js/custom-login.js',
            array('jquery'), // jquery به عنوان وابستگی
            READYSMS_VERSION,
            true // بارگذاری در فوتر
        );

        // ارسال داده‌ها و رشته‌های ترجمه به جاوااسکریپت
        wp_localize_script('readysms-front-js', 'readyLoginAjax', array(
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('readysms-nonce'),
            'timer_duration' => (int) get_option('ready_sms_timer_duration', 120),
            'otp_length'     => (int) get_option('ready_sms_otp_length', 6), // ارسال طول کد OTP به جاوااسکریپت
            'error_general'  => __('خطا در ارتباط با سرور. لطفاً لحظاتی دیگر تلاش کنید.', 'readysms'),
            'error_phone'    => __('لطفاً شماره موبایل خود را به صورت صحیح وارد کنید (مثال: 09123456789).', 'readysms'),
            'error_otp_empty'=> __('لطفاً کد تایید دریافت شده را وارد کنید.', 'readysms'),
            'error_otp_invalid_format' => __('فرمت کد تایید وارد شده صحیح نیست.', 'readysms'), // برای پیام خطای کلی فرمت
            'digits_text'    => __('رقمی', 'readysms'), // برای ساخت پیام پویا مانند "کد X رقمی"
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
 * Adds necessary options to the database.
 */
function readysms_activate() {
    // Google Login Options
    if (get_option('ready_google_client_id') === false) {
        add_option('ready_google_client_id', '');
    }
    if (get_option('ready_google_client_secret') === false) {
        add_option('ready_google_client_secret', '');
    }

    // SMS Login Options
    if (get_option('ready_sms_api_key') === false) {
        add_option('ready_sms_api_key', '');
    }
    if (get_option('ready_sms_number') === false) {
        add_option('ready_sms_number', '');
    }
    if (get_option('ready_sms_pattern_code') === false) {
        add_option('ready_sms_pattern_code', '');
    }
    if (get_option('ready_sms_timer_duration') === false) {
        add_option('ready_sms_timer_duration', 120);
    }
    if (get_option('ready_sms_otp_length') === false) {
        add_option('ready_sms_otp_length', 6); // مقدار پیش‌فرض برای طول کد OTP
    }
}
register_activation_hook(__FILE__, 'readysms_activate');

/**
 * Plugin deactivation hook.
 * Clean up options if desired by uncommenting.
 */
function readysms_deactivate() {
    // delete_option('ready_google_client_id');
    // delete_option('ready_google_client_secret');
    // delete_option('ready_sms_api_key');
    // delete_option('ready_sms_number');
    // delete_option('ready_sms_pattern_code');
    // delete_option('ready_sms_timer_duration');
    // delete_option('ready_sms_otp_length');
}
register_deactivation_hook(__FILE__, 'readysms_deactivate');

/**
 * Add settings link on plugin page in WordPress admin.
 */
function readysms_add_settings_link($links) {
    $settings_link_url = admin_url('admin.php?page=readysms-settings');
    $settings_link_text = __('تنظیمات', 'readysms');
    $html_settings_link = '<a href="' . esc_url($settings_link_url) . '">' . esc_html($settings_link_text) . '</a>';
    array_unshift($links, $html_settings_link); // Add to the beginning of the links array
    return $links;
}
// Ensure plugin_basename is correctly defined before using it in the filter hook
if (function_exists('plugin_basename')) {
    $plugin_basename_for_action_link = plugin_basename(__FILE__);
    add_filter("plugin_action_links_{$plugin_basename_for_action_link}", 'readysms_add_settings_link');
}

?>
