<?php
// File: includes/sms-login.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.', 'readysms'));
    }

    $international_phone = '+98' . substr($phone, 1);
    $otp = (string)wp_rand(100000, 999999);

    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');
    $line_number = get_option('ready_sms_number');
    $timer_duration = (int)get_option('ready_sms_timer_duration', 120);

    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error(__('تنظیمات API ناقص است. لطفا کلید API و کد پترن را در پیشخوان تنظیم کنید.', 'readysms'));
    }

    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp]
    ];
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

    // Re-use the helper function if it's loaded, otherwise define a local version or include it.
    // For simplicity here, we assume ready_msgway_api_request is available (e.g. loaded if admin, or duplicate if needed)
    // However, for front-end, it's better to have the direct wp_remote_post call here to avoid admin-only helper dependencies
     $args = [
        'headers'     => [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ],
        'body'        => json_encode($payload),
        'timeout'     => 30,
    ];
    $response = wp_remote_post('https://api.msgway.com/send', $args);


    if (is_wp_error($response)) {
        error_log('Readysms Front: WP Error on sending OTP: ' . $response->get_error_message());
        wp_send_json_error(__('ارسال پیامک با خطا مواجه شد. (WP Error)', 'readysms'));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (($http_code === 200 || $http_code === 201) && isset($body['OTPReferenceId'])) {
        set_transient('readysms_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        // OTPReferenceId is returned by Msgway, might be useful for logging/status checks but not for verification flow here.
        wp_send_json_success([
            'message'        => __('کد تایید با موفقیت ارسال شد.', 'readysms'),
            'remaining_time' => $timer_duration,
            // 'otp_ref_id'  => $body['OTPReferenceId'] // Optional: if JS needs it
        ]);
    } else {
        $error_message = __('خطای ناشناس در ارسال OTP از سمت راه پیام.', 'readysms');
        if (isset($body['message'])) {
            $error_message = is_array($body['message']) ? implode(', ', $body['message']) : $body['message'];
        } elseif (isset($body['Message'])) {
             $error_message = is_array($body['Message']) ? implode(', ', $body['Message']) : $body['Message'];
        } else if ($http_code >= 400) {
            $error_message = sprintf(__('خطای API راه پیام (کد: %s)', 'readysms'), $http_code);
        }
        error_log('Readysms Front: Error sending OTP - ' . $error_message . ' | HTTP Code: ' . $http_code . ' | Response: ' . wp_json_encode($body));
        wp_send_json_error($error_message);
    }
    wp_die();
}
add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');

/**
 * بررسی کد تایید دریافت شده (OTP) با استفاده از API Verify سامانه راه پیام
 */
function ready_sms_verify_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'], $_POST['otp_code'])) { // redirect_link is expected by JS
        wp_send_json_error(__('اطلاعات مورد نیاز (شماره تلفن، کد تایید) وارد نشده است.', 'readysms'));
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است.', 'readysms'));
    }

    $international_phone = '+98' . substr($phone, 1);
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $redirect_link = isset($_POST['redirect_link']) ? esc_url_raw(wp_unslash($_POST['redirect_link'])) : home_url();


    $api_key = get_option('ready_sms_api_key');
    if (empty($api_key)) {
        wp_send_json_error(__('تنظیمات API پیامک یافت نشد.', 'readysms'));
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
        'body'    => json_encode($payload),
        'timeout' => 30,
    ];
    $response = wp_remote_post('https://api.msgway.com/otp/verify', $args);

    if (is_wp_error($response)) {
        error_log('Readysms Front: WP Error on verifying OTP: ' . $response->get_error_message());
        wp_send_json_error(__('تایید OTP با خطا مواجه شد. (WP Error)', 'readysms'));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code === 200 && isset($body['status']) && $body['status'] == 1 && isset($body['message']) && $body['message'] == "Verified") {
        delete_transient('readysms_otp_' . $phone);

        $user = get_user_by('login', $phone);
        if (!$user) {
            $email_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (!$email_host) $email_host = 'example.com'; // Fallback host
            $email = $phone . '@' . $email_host;
            if (email_exists($email)) {
                $email = $phone . '_' . wp_rand(100,999) . '@' . $email_host;
            }

            $user_id = wp_create_user($phone, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(sprintf(__('خطا در ایجاد کاربر: %s', 'readysms'), $user_id->get_error_message()));
            }
            $role = class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber');
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }

        if ($user && !is_wp_error($user)) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
            wp_send_json_success(['redirect_url' => $redirect_link]); // JS expects redirect_url
        } else {
            wp_send_json_error(__('خطا در ورود کاربر پس از تایید.', 'readysms'));
        }
        
    } else {
        $error_message = __('کد تایید نامعتبر است یا خطای API.', 'readysms');
        if (isset($body['message'])) {
            $error_message = is_array($body['message']) ? implode(', ', $body['message']) : $body['message'];
        } elseif (isset($body['Message'])) {
             $error_message = is_array($body['Message']) ? implode(', ', $body['Message']) : $body['Message'];
        } else if ($http_code >= 400) {
             $error_message = sprintf(__('خطای API راه پیام در تایید کد (کد: %s)', 'readysms'), $http_code);
        }
        error_log('Readysms Front: Error verifying OTP - ' . $error_message . ' | HTTP Code: ' . $http_code . ' | Response: ' . wp_json_encode($body));
        wp_send_json_error($error_message);
    }
    wp_die();
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');
?>
