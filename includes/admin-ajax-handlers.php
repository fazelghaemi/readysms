<?php
// File: includes/admin-ajax-handlers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to make requests to Msgway API.
 */
if (!function_exists('ready_msgway_api_request')) {
    function ready_msgway_api_request($endpoint_path, $api_key, $args = [], $method = 'POST') {
        $base_url = 'https://api.msgway.com/';
        $url = $base_url . ltrim($endpoint_path, '/');

        $default_headers = [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ];
        
        $headers = isset($args['headers']) ? array_merge($default_headers, $args['headers']) : $default_headers;
        
        $request_args = [
            'headers' => $headers,
            'timeout' => 30, // Seconds
        ];

        if (strtoupper($method) === 'POST') {
            $request_args['body'] = isset($args['body']) ? json_encode($args['body']) : null;
            $response = wp_remote_post($url, $request_args);
        } else { // GET
            if (!empty($args['params'])) { // If params are query args for GET
                $url = add_query_arg($args['params'], $url);
            }
            $response = wp_remote_get($url, $request_args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code >= 400) { // Error based on HTTP status code
            $error_message = sprintf(__('Msgway API Error: HTTP %s', 'readysms'), $http_code);
            $api_error_message = '';
            if (isset($decoded_body['message'])) {
                $api_error_message = is_array($decoded_body['message']) ? implode(', ', $decoded_body['message']) : $decoded_body['message'];
            } elseif (isset($decoded_body['Message'])) { // Some APIs use 'Message'
                 $api_error_message = is_array($decoded_body['Message']) ? implode(', ', $decoded_body['Message']) : $decoded_body['Message'];
            }
            if (!empty($api_error_message)) {
                $error_message .= ' - ' . $api_error_message;
            }
            return new WP_Error('msgway_api_error', $error_message, ['status' => $http_code, 'body' => $decoded_body]);
        }
        
        if (is_array($decoded_body)) {
            $decoded_body['http_code_debug'] = $http_code; // For easier debugging success cases too
        } else { // If body is not JSON, but request was successful (e.g. plain text or unexpected)
             // For some Msgway GET requests that might return plain text or non-JSON on success
            if ($http_code < 300 && !empty($body)) {
                return ['data' => $body, 'http_code_debug' => $http_code];
            }
            // If still not an array and expected one, it might be an issue.
            // However, for simplicity, if http_code is fine, pass it.
            // Or return an error if JSON was strictly expected:
            // return new WP_Error('msgway_json_error', __('Failed to decode JSON response from Msgway.', 'readysms'));
        }
        return $decoded_body;
    }
}


/**
 * AJAX handler for sending a test OTP.
 */
add_action('wp_ajax_ready_admin_send_test_otp', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    }

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.', 'readysms'));
    }
    $international_phone = '+98' . substr($phone, 1);

    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');
    $line_number = get_option('ready_sms_number');

    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error(__('کلید API یا کد پترن پیامک تنظیم نشده است.', 'readysms'));
    }

    $otp = (string)wp_rand(100000, 999999); // Test OTP

    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int)$template_id,
        "params"     => [$otp] 
    ];
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

    $response = ready_msgway_api_request('send', $api_key, ['body' => $payload], 'POST');

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('خطا در ارسال پیامک آزمایشی: %s', 'readysms'), $response->get_error_message()));
    }

    // Msgway 'send' endpoint usually returns HTTP 200 or 201 with OTPReferenceId
    if (isset($response['http_code_debug']) && ($response['http_code_debug'] === 200 || $response['http_code_debug'] === 201) && isset($response['OTPReferenceId'])) {
        set_transient('ready_admin_test_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message' => sprintf(__('پیامک آزمایشی با موفقیت ارسال شد. کد OTP (برای تست): %s. شناسه مرجع: %s', 'readysms'), $otp, esc_html($response['OTPReferenceId'])),
            'response_data' => $response // Full API response for debugging
        ]);
    } else {
        $error_message = isset($response['message']) ? (is_array($response['message']) ? implode(', ',$response['message']) : $response['message']) : (isset($response['Message']) ? (is_array($response['Message']) ? implode(', ',$response['Message']) : $response['Message']) : __('خطای ناشناخته از سمت API راه پیام هنگام ارسال.', 'readysms'));
        wp_send_json_error(sprintf(__('خطا در ارسال: %s', 'readysms'), $error_message));
    }
});

/**
 * AJAX handler for verifying a test OTP.
 */
add_action('wp_ajax_ready_admin_verify_test_otp', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    }

    if (!isset($_POST['phone_number'], $_POST['otp_code'])) {
        wp_send_json_error(__('شماره تلفن یا کد تایید وارد نشده است.', 'readysms'));
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است.', 'readysms'));
    }
    $international_phone = '+98' . substr($phone, 1);

    $api_key = get_option('ready_sms_api_key');
    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    }
    
    $payload = [
        "OTP"    => $otp_code,
        "mobile" => $international_phone,
    ];

    $response = ready_msgway_api_request('otp/verify', $api_key, ['body' => $payload], 'POST');

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('خطا در بررسی کد تایید: %s', 'readysms'), $response->get_error_message()));
    }
    
    // Msgway SimpleVerifyOTP.php example suggests: status: 1 and message: "Verified" for success.
    if (isset($response['status']) && $response['status'] == 1 && isset($response['message']) && $response['message'] == 'Verified') {
        delete_transient('ready_admin_test_otp_' . $phone);
        wp_send_json_success([
            'message' => __('کد تایید صحیح و با موفقیت توسط راه پیام بررسی شد.', 'readysms'),
            'response_data' => $response
        ]);
    } else {
        $error_message = isset($response['message']) ? (is_array($response['message']) ? implode(', ',$response['message']) : $response['message']) : __('کد تایید نامعتبر یا خطای دیگر از سمت API راه پیام.', 'readysms');
        wp_send_json_error($error_message);
    }
});


/**
 * AJAX handler for checking SMS status.
 */
add_action('wp_ajax_ready_admin_check_sms_status', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    }

    if (!isset($_POST['reference_id'])) {
        wp_send_json_error(__('شناسه مرجع (OTPReferenceID) وارد نشده است.', 'readysms'));
    }
    $reference_id = sanitize_text_field(wp_unslash($_POST['reference_id']));
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    }

    $response = ready_msgway_api_request('status/' . $reference_id, $api_key, [], 'GET');

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('خطا در دریافت وضعیت: %s', 'readysms'), $response->get_error_message()));
    }
    
    // A successful GET (HTTP 200) would contain the status info.
    // The structure of response can vary.
    if (isset($response['http_code_debug']) && $response['http_code_debug'] === 200) {
         wp_send_json_success([
            'message' => __('وضعیت پیامک با موفقیت از راه پیام دریافت شد.', 'readysms'),
            'response_data' => $response // This will be the array/object from Msgway
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : (isset($response['Message']) ? $response['Message'] : __('خطای ناشناخته هنگام دریافت وضعیت از API راه پیام.', 'readysms'));
        wp_send_json_error(sprintf(__('خطا در دریافت وضعیت: %s', 'readysms'), $error_message));
    }
});

/**
 * AJAX handler for getting template information.
 */
add_action('wp_ajax_ready_admin_get_template_info', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    }

    if (!isset($_POST['template_id_to_test'])) {
        wp_send_json_error(__('شناسه قالب (Template ID) وارد نشده است.', 'readysms'));
    }
    $template_id_to_test = sanitize_text_field(wp_unslash($_POST['template_id_to_test']));
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    }

    $response = ready_msgway_api_request('template/' . $template_id_to_test, $api_key, [], 'GET');
    
    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('خطا در دریافت اطلاعات قالب: %s', 'readysms'), $response->get_error_message()));
    }

    // Successful GET (HTTP 200) with template data (e.g., 'id' field present)
    if (isset($response['http_code_debug']) && $response['http_code_debug'] === 200 && isset($response['id'])) {
         wp_send_json_success([
            'message' => __('اطلاعات قالب با موفقیت از راه پیام دریافت شد.', 'readysms'),
            'response_data' => $response
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : (isset($response['Message']) ? $response['Message'] : __('قالب یافت نشد یا خطای دیگر از API راه پیام.', 'readysms'));
         if (isset($response['http_code_debug']) && $response['http_code_debug'] === 404) {
            $error_message = __('قالب با این شناسه در سامانه راه پیام یافت نشد (404).', 'readysms');
        }
        wp_send_json_error($error_message);
    }
});


/**
 * AJAX handler for getting credit balance.
 */
add_action('wp_ajax_ready_admin_get_balance', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    }
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    }
    
    $response = ready_msgway_api_request('credit', $api_key, [], 'GET');

    if (is_wp_error($response)) {
        wp_send_json_error(sprintf(__('خطا در دریافت موجودی: %s', 'readysms'), $response->get_error_message()));
    }

    // Msgway SimpleGetCredit.php example expects a body like: {"credit":1000.0,"currency":"ریال"}
    if (isset($response['http_code_debug']) && $response['http_code_debug'] === 200 && isset($response['credit'])) {
         wp_send_json_success([
            'message' => sprintf(__('موجودی شما: %s %s', 'readysms'), number_format_i18n($response['credit'], 2), esc_html(isset($response['currency']) ? $response['currency'] : __('ریال', 'readysms'))),
            'response_data' => $response
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : (isset($response['Message']) ? $response['Message'] : __('خطای ناشناخته هنگام دریافت موجودی از API راه پیام.', 'readysms'));
        wp_send_json_error(sprintf(__('خطا در دریافت موجودی: %s', 'readysms'), $error_message));
    }
});

?>
