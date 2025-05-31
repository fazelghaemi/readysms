<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس.
 * Version: 1.1.0 // Suggested version bump
 * Author: Fazel Ghaemi
 * Author URI: https://readystudio.ir/
 * Plugin URI: https://readystudio.ir/readysms-plugin/
 * License: GPLv2 or later
 * License URI: https://readystudio.ir/readysms-plugin/licenses/
 */

if (!defined('ABSPATH')) {
    exit;
}

define('READYSMS_DIR', plugin_dir_path(__FILE__));
define('READYSMS_URL', plugin_dir_url(__FILE__));
define('READYSMS_VERSION', '1.1.0'); // Suggested version bump

require_once READYSMS_DIR . 'includes/google-login.php';
require_once READYSMS_DIR . 'includes/sms-login.php';
require_once READYSMS_DIR . 'includes/admin-settings.php';
require_once READYSMS_DIR . 'includes/shortcodes.php';

/**
 * Enqueue front-end CSS and JS for custom login functionality.
 */
function ready_login_enqueue_scripts() {
    wp_enqueue_style(
        'readysms-style',
        READYSMS_URL . 'assets/css/custom-login.css',
        array(),
        READYSMS_VERSION
    );
    wp_enqueue_script(
        'readysms-ajax',
        READYSMS_URL . 'assets/js/custom-login.js',
        array('jquery'),
        READYSMS_VERSION,
        true
    );
    wp_localize_script('readysms-ajax', 'readyLoginAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('readysms-nonce'),
        'timer_duration' => get_option('ready_sms_timer_duration', 120) // Pass timer duration to JS
    ));
}
add_action('wp_enqueue_scripts', 'ready_login_enqueue_scripts');

/**
 * Plugin activation hook.
 * Adds necessary options to the database.
 */
function ready_login_activate() {
    add_option('ready_google_client_id', '');
    add_option('ready_google_client_secret', '');
    add_option('ready_sms_api_key', '');
    add_option('ready_sms_number', ''); // This will be used as lineNumber
    add_option('ready_sms_pattern_code', ''); // This is the templateID
    add_option('ready_sms_timer_duration', 120); // Default OTP resend timer
    add_option('ready_sms_max_attempts', 4); // For client-side attempt limiting (JS implementation needed)
    add_option('ready_sms_block_duration', 3600); // For client-side blocking (JS implementation needed)
}
register_activation_hook(__FILE__, 'ready_login_activate');

/**
 * Plugin deactivation hook.
 * Clean up options if needed.
 */
function ready_login_deactivate() {
    // Example:
    // delete_option('ready_google_client_id');
    // delete_option('ready_google_client_secret');
    // delete_option('ready_sms_api_key');
    // delete_option('ready_sms_number');
    // delete_option('ready_sms_pattern_code');
    // delete_option('ready_sms_timer_duration');
    // delete_option('ready_sms_max_attempts');
    // delete_option('ready_sms_block_duration');
}
register_deactivation_hook(__FILE__, 'ready_login_deactivate');

// Action to handle AJAX requests for Msgway API tests from admin panel
require_once READYSMS_DIR . 'includes/admin-ajax-handlers.php'; // We will create this new file

?>
