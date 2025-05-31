<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس با استفاده از سامانه راه پیام.
 * Version: 1.1.0
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

define('READYSMS_VERSION', '1.1.0');
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
require_once READYSMS_DIR . 'includes/admin-ajax-handlers.php'; // For admin panel API tests

/**
 * Enqueue front-end CSS and JS for custom login functionality.
 */
function readysms_enqueue_front_scripts() {
    if (!is_admin()) { // Only enqueue on the front-end
        wp_enqueue_style(
            'readysms-front-style',
            READYSMS_URL . 'assets/css/custom-login.css',
            array(),
            READYSMS_VERSION
        );
        wp_enqueue_script(
            'readysms-front-ajax',
            READYSMS_URL . 'assets/js/custom-login.js',
            array('jquery'),
            READYSMS_VERSION,
            true // Load in footer
        );
        wp_localize_script('readysms-front-ajax', 'readyLoginAjax', array(
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('readysms-nonce'), // For front-end OTP actions
            'timer_duration' => (int) get_option('ready_sms_timer_duration', 120),
            'error_general'  => __('خطا در ارتباط با سرور.', 'readysms'),
            'error_phone'    => __('لطفاً شماره تلفن را وارد کنید.', 'readysms'),
            'error_otp'      => __('لطفاً کد تایید را وارد کنید.', 'readysms'),
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
    add_option('ready_sms_number', ''); // This will be used as lineNumber
    add_option('ready_sms_pattern_code', ''); // This is the templateID
    add_option('ready_sms_timer_duration', 120); // Default OTP resend timer
    // Options for future use (max attempts, block duration) - require client/server logic
    // add_option('ready_sms_max_attempts', 4);
    // add_option('ready_sms_block_duration', 3600);
}
register_activation_hook(__FILE__, 'readysms_activate');

/**
 * Plugin deactivation hook.
 * Clean up options if desired.
 */
function readysms_deactivate() {
    // Example: To remove options upon deactivation, uncomment below
    // delete_option('ready_google_client_id');
    // delete_option('ready_google_client_secret');
    // delete_option('ready_sms_api_key');
    // delete_option('ready_sms_number');
    // delete_option('ready_sms_pattern_code');
    // delete_option('ready_sms_timer_duration');
}
register_deactivation_hook(__FILE__, 'readysms_deactivate');
?>
