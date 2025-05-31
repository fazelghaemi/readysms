<?php
// File: includes/sms-login.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    // 1. بررسی امنیتی Nonce
    check_ajax_referer('readysms-nonce', 'nonce');

    // 2. اعتبارسنجی شماره تلفن ورودی
    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
        return;
    }
    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.', 'readysms'));
        return;
    }

    // 3. دریافت تنظیمات و آماده‌سازی پارامترها
    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');

    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error(__('تنظیمات API ناقص است. لطفا کلید API و کد پترن را در پیشخوان تنظیم کنید.', 'readysms'));
        return;
    }

    $line_number = get_option('ready_sms_number');
    $timer_duration = (int)get_option('ready_sms_timer_duration', 120);
    $otp = (string)wp_rand(100000, 999999);
    $international_phone = '+98' . substr($phone, 1);

    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp]
    ];
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

    // 4. ارسال درخواست به API راه پیام
    $args = [
        'headers'     => [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ],
        'body'        => wp_json_encode($payload),
        'timeout'     => 30,
    ];
    $response = wp_remote_post('https://api.msgway.com/send', $args);

    // 5. بررسی خطای اولیه در ارتباط
    if (is_wp_error($response)) {
        $wp_error_message = $response->get_error_message();
        error_log("Readysms (Send OTP) - WP_Error during wp_remote_post: " . $wp_error_message . " - Payload: " . wp_json_encode($payload));
        wp_send_json_error(sprintf(__('ارسال پیامک با خطای سیستمی وردپرس مواجه شد: %s. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.', 'readysms'), $wp_error_message));
        return;
    }

    // 6. پردازش پاسخ دریافت شده از API راه پیام
    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_from_api = wp_remote_retrieve_body($response);
    $decoded_body_as_array = json_decode($raw_body_from_api, true);

    // تعریف شرط موفقیت از دید افزونه: کد HTTP باید 200 یا 201 باشد، پاسخ باید آرایه JSON معتبر باشد,
    // کلید referenceID موجود باشد و status برابر 'success' باشد.
    $is_plugin_considering_api_call_successful = ($http_code === 200 || $http_code === 201) &&
                                                 is_array($decoded_body_as_array) &&
                                                 isset($decoded_body_as_array['referenceID']) && // کلید صحیح بر اساس لاگ شما
                                                 isset($decoded_body_as_array['status']) &&
                                                 $decoded_body_as_array['status'] === 'success';

    if ($is_plugin_considering_api_call_successful) {
        set_transient('readysms_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message'        => __('کد تایید با موفقیت ارسال شد.', 'readysms'),
            'remaining_time' => $timer_duration,
        ]);
    } else {
        $user_facing_error_message = __('خطای ناشناس در ارسال OTP از سمت راه پیام. لطفاً بررسی کنید پیامک به دستتان رسیده است.', 'readysms');

        if (is_array($decoded_body_as_array)) {
            if (!empty($decoded_body_as_array['message'])) {
                $user_facing_error_message = is_array($decoded_body_as_array['message']) ? implode('; ', $decoded_body_as_array['message']) : (string) $decoded_body_as_array['message'];
            } elseif (!empty($decoded_body_as_array['Message'])) {
                $user_facing_error_message = is_array($decoded_body_as_array['Message']) ? implode('; ', $decoded_body_as_array['Message']) : (string) $decoded_body_as_array['Message'];
            } elseif (isset($decoded_body_as_array['status']) && $decoded_body_as_array['status'] !== 'success' && !empty($decoded_body_as_array['error'])) {
                $user_facing_error_message = (string) $decoded_body_as_array['error'];
            }
        } elseif ($http_code >= 400) {
            $user_facing_error_message = sprintf(__('خطای API راه پیام (کد وضعیت: %s).', 'readysms'), $http_code);
        }

        $log_data_for_debugging = [
            'UserMessageSent' => $user_facing_error_message,
            'HTTPStatusCode' => $http_code,
            'RawResponseBody' => $raw_body_from_api,
            'DecodedBodyAsArray' => $decoded_body_as_array,
            'PluginSuccessConditionMet' => $is_plugin_considering_api_call_successful // This would be false here
        ];
        error_log("Readysms (Send OTP) - API Call Not Considered Successful By Plugin: " . wp_json_encode($log_data_for_debugging, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        wp_send_json_error($user_facing_error_message);
    }
}
add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');

/**
 * بررسی کد تایید دریافت شده (OTP) با استفاده از API Verify سامانه راه پیام
 */
function ready_sms_verify_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'], $_POST['otp_code'])) {
        wp_send_json_error(__('اطلاعات مورد نیاز (شماره تلفن، کد تایید) وارد نشده است.', 'readysms'));
        return;
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است.', 'readysms'));
        return;
    }

    $international_phone = '+98' . substr($phone, 1);
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $redirect_link = isset($_POST['redirect_link']) ? esc_url_raw(wp_unslash($_POST['redirect_link'])) : home_url();


    $api_key = get_option('ready_sms_api_key');
    if (empty($api_key)) {
        wp_send_json_error(__('تنظیمات API پیامک یافت نشد.', 'readysms'));
        return;
    }

    $payload = [
        "OTP"    => $otp_code,
        "mobile" => $international_phone,
    ];
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 30,
    ];
    $response = wp_remote_post('https://api.msgway.com/otp/verify', $args);

    if (is_wp_error($response)) {
        $wp_error_message = $response->get_error_message();
        error_log('Readysms Front (Verify OTP) - WP_Error: ' . $wp_error_message . ' - Payload: ' . wp_json_encode($payload));
        wp_send_json_error(sprintf(__('تایید OTP با خطای سیستمی وردپرس مواجه شد: %s.', 'readysms'), $wp_error_message));
        return;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_from_api_verify = wp_remote_retrieve_body($response);
    $decoded_body_as_array_verify = json_decode($raw_body_from_api_verify, true);

    // شرط موفقیت برای تایید OTP بر اساس مستندات راه پیام (status: 1 و message: "Verified")
    // برخی APIها ممکن است status را به صورت عددی (1) و برخی به صورت رشته ("1") برگردانند.
    // استفاده از == به جای === برای status انعطاف‌پذیری بیشتری دارد اگر نوع داده قطعی نباشد.
    $is_otp_verification_successful = ($http_code === 200 &&
                                       is_array($decoded_body_as_array_verify) &&
                                       isset($decoded_body_as_array_verify['status']) &&
                                       $decoded_body_as_array_verify['status'] == 1 && // '==' is more flexible for "1" vs 1
                                       isset($decoded_body_as_array_verify['message']) &&
                                       strtolower($decoded_body_as_array_verify['message']) === "verified"); // strtolower for case-insensitivity

    if ($is_otp_verification_successful) {
        delete_transient('readysms_otp_' . $phone);

        $user = get_user_by('login', $phone);
        if (!$user) {
            $email_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (empty($email_host)) $email_host = 'example.com';
            
            $base_email_user = preg_replace('/[^a-z0-9_]/i', '', $phone);
            $email = $base_email_user . '@' . $email_host;
            
            if (email_exists($email)) {
                $email = $base_email_user . '_' . wp_rand(100,999) . '@' . $email_host;
            }

            $user_id = wp_create_user($phone, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(sprintf(__('خطا در ایجاد کاربر جدید: %s', 'readysms'), $user_id->get_error_message()));
                return;
            }
            $role = class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber');
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }

        if ($user && !is_wp_error($user)) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
            wp_send_json_success(['redirect_url' => $redirect_link]);
        } else {
            wp_send_json_error(__('خطا در ورود یا یافتن اطلاعات کاربر پس از تایید OTP.', 'readysms'));
        }
        
    } else {
        $user_facing_error_message_verify = __('کد تایید نامعتبر است یا خطای API رخ داده است.', 'readysms');
        if (is_array($decoded_body_as_array_verify) && !empty($decoded_body_as_array_verify['message'])) {
            $user_facing_error_message_verify = is_array($decoded_body_as_array_verify['message']) ? implode('; ', $decoded_body_as_array_verify['message']) : (string) $decoded_body_as_array_verify['message'];
        } elseif (is_array($decoded_body_as_array_verify) && !empty($decoded_body_as_array_verify['Message'])) {
            $user_facing_error_message_verify = is_array($decoded_body_as_array_verify['Message']) ? implode('; ', $decoded_body_as_array_verify['Message']) : (string) $decoded_body_as_array_verify['Message'];
        } elseif ($http_code >= 400) {
             $user_facing_error_message_verify = sprintf(__('خطای API راه پیام در تایید کد (کد وضعیت: %s)', 'readysms'), $http_code);
        }

        $log_data_for_debugging_verify = [
            'UserMessageSent' => $user_facing_error_message_verify,
            'HTTPStatusCode' => $http_code,
            'RawResponseBody' => $raw_body_from_api_verify,
            'DecodedBodyAsArray' => $decoded_body_as_array_verify,
            'PluginSuccessConditionMet' => $is_otp_verification_successful
        ];
        error_log("Readysms (Verify OTP) - API Call Not Considered Successful By Plugin: " . wp_json_encode($log_data_for_debugging_verify, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        wp_send_json_error($user_facing_error_message_verify);
    }
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');

?>
