<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس با استفاده از سامانه راه پیام.
 * Version: 1.2.0
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

define('READYSMS_VERSION', '1.2.0'); // نسخه به‌روز شده
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
    // این بهینه‌سازی نیاز به بررسی وجود شورت‌کد در محتوای صفحه دارد که کمی پیچیده‌تر است.
    // برای سادگی، فعلاً در تمام صفحات فرانت‌اند بارگذاری می‌کنیم.
    if (!is_admin()) {
        wp_enqueue_style(
            'readysms-front-style',
            READYSMS_URL . 'assets/css/custom-login.css',
            array(),
            READYSMS_VERSION
        );
        wp_enqueue_script(
            'readysms-front-js', // تغییر نام handle برای وضوح بیشتر
            READYSMS_URL . 'assets/js/custom-login.js',
            array('jquery'), // jquery به عنوان وابستگی
            READYSMS_VERSION,
            true // بارگذاری در فوتر
        );
        wp_localize_script('readysms-front-js', 'readyLoginAjax', array(
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('readysms-nonce'),
            'timer_duration' => (int) get_option('ready_sms_timer_duration', 120),
            'otp_length'     => (int) get_option('ready_sms_otp_length', 6), // ارسال طول کد OTP به جاوااسکریپت
            'error_general'  => __('خطا در ارتباط با سرور. لطفاً لحظاتی دیگر تلاش کنید.', 'readysms'),
            'error_phone'    => __('لطفاً شماره موبایل خود را به صورت صحیح وارد کنید.', 'readysms'),
            'error_otp_empty'=> __('لطفاً کد تایید دریافت شده را وارد کنید.', 'readysms'),
            'error_otp_invalid' => __('فرمت کد تایید وارد شده صحیح نیست.', 'readysms'),
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
    add_option('ready_google_client_id', '');
    add_option('ready_google_client_secret', '');
    add_option('ready_sms_api_key', '');
    add_option('ready_sms_number', '');
    add_option('ready_sms_pattern_code', '');
    add_option('ready_sms_timer_duration', 120);
    add_option('ready_sms_otp_length', 6); // افزودن گزینه طول کد OTP با مقدار پیش‌فرض
}
register_activation_hook(__FILE__, 'readysms_activate');

/**
 * Plugin deactivation hook.
 * Clean up options if desired.
 */
function readysms_deactivate() {
    // مثال: برای حذف گزینه‌ها هنگام غیرفعال‌سازی، از کامنت خارج کنید
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
 * Add settings link on plugin page.
 */
function readysms_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=readysms-settings') . '">' . __('تنظیمات', 'readysms') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin_basename_for_link = plugin_basename(__FILE__); // اطمینان از تعریف صحیح
add_filter("plugin_action_links_$plugin_basename_for_link", 'readysms_add_settings_link');

?>
