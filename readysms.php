<?php
/**
 * Plugin Name: ReadySMS | ردی اس‌ام‌اس
 * Description: افزونه ورود و ثبت نام با پیامک و قابلیت جذاب ورود با گوگل در وردپرس.
 * Version: 1.0
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
define('READYSMS_VERSION', '1.0.0');

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
    ));
}
add_action('wp_enqueue_scripts', 'ready_login_enqueue_scripts');

/**
 * Plugin activation hook.
 * Adds necessary options to the database.
 */
function ready_login_activate() {
    // حذف گزینه‌های قدیمی (در صورت نیاز) و افزودن گزینه‌های مورد نیاز
    add_option('ready_google_client_id', '');
    add_option('ready_google_client_secret', '');
    add_option('ready_sms_api_key', '');
    add_option('ready_sms_number', '');
    add_option('ready_sms_pattern_code', ''); // کد پترن ثبت‌شده در سامانه راه‌پیام
    add_option('ready_sms_timer_duration', 120);
    add_option('ready_sms_max_attempts', 4);
    add_option('ready_sms_block_duration', 3600);
}
register_activation_hook(__FILE__, 'ready_login_activate');

/**
 * Plugin deactivation hook.
 * Clean up options if needed.
 */
function ready_login_deactivate() {
    // در صورت نیاز می‌توانید گزینه‌های افزونه را حذف کنید:
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
?>