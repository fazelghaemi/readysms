<?php
// File: includes/admin-ajax-handlers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to make requests to Msgway API.
 * @param string $endpoint The API endpoint (e.g., 'send', 'credit').
 * @param array $args Arguments for wp_remote_post or wp_remote_get.
 * @param string $method 'POST' or 'GET'.
 * @return array|WP_Error The response body decoded or WP_Error.
 */
function ready_msgway_api_request($endpoint_path, $api_key, $args = [], $method = 'POST') {
    $base_url = 'https://api.msgway.com/';
    $url = $base_url . ltrim($endpoint_path, '/');

    $default_headers = [
        'Content-Type' => 'application/json',
        'apiKey'       => $api_key,
    ];

    if (isset($args['headers'])) {
        $headers = array_merge($default_headers, $args['headers']);
    } else {
        $headers = $default_headers;
    }
    
    $request_args = [
        'headers' => $headers,
        'timeout' => 30,
    ];

    if ($method === 'POST') {
        $request_args['body'] = isset($args['body']) ? json_encode($args['body']) : null;
        $response = wp_remote_post($url, $request_args);
    } else { // GET
        // For GET, parameters are usually in the URL, but if body is used for GET (uncommon)
        // $url = add_query_arg($args['params'], $url); // If params are query args
        $response = wp_remote_get($url, $request_args);
    }

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $decoded_body = json_decode($body, true);

    $http_code = wp_remote_retrieve_response_code($response);

    if ($http_code >= 400) {
        $error_message = 'Msgway API Error: HTTP ' . $http_code;
        if (isset($decoded_body['message'])) {
            $error_message .= ' - ' . (is_array($decoded_body['message']) ? implode(', ', $decoded_body['message']) : $decoded_body['message']);
        } elseif (isset($decoded_body['Message'])) {
             $error_message .= ' - ' . (is_array($decoded_body['Message']) ? implode(', ', $decoded_body['Message']) : $decoded_body['Message']);
        }
        return new WP_Error('msgway_api_error', $error_message, ['status' => $http_code, 'body' => $decoded_body]);
    }
    
    // Add http_code to the decoded body for easier checking in calling functions
    if (is_array($decoded_body)) {
        $decoded_body['http_code'] = $http_code;
    } else { // If body is not JSON, but request was successful (e.g. plain text)
        return ['data' => $body, 'http_code' => $http_code];
    }

    return $decoded_body;
}


/**
 * AJAX handler for sending a test OTP.
 * Uses the main ready_sms_send_otp logic but adapted for admin test.
 */
add_action('wp_ajax_ready_admin_send_test_otp', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('شما مجوز کافی ندارید.', 403);
    }

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error('شماره تلفن وارد نشده است.');
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.');
    }
    $international_phone = '+98' . substr($phone, 1);

    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');
    $line_number = get_option('ready_sms_number'); // Get line number

    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error('کلید API یا کد پترن پیامک تنظیم نشده است.');
    }

    $otp = (string)wp_rand(100000, 999999); // Test OTP

    $params = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int)$template_id,
        "params"     => [$otp] 
    ];
    if (!empty($line_number)) {
        $params["lineNumber"] = $line_number;
    }

    $response = ready_msgway_api_request('send', $api_key, ['body' => $params], 'POST');

    if (is_wp_error($response)) {
        wp_send_json_error('خطا در ارسال پیامک آزمایشی: ' . $response->get_error_message());
    }

    // According to Msgway SimpleSendSMS.php, successful send (HTTP 200/201)
    // The body contains OTPReferenceId etc.
    // For example: {"message":"Sent","OTPReferenceId":"MSG-xxxx","mobile":"+98xxxx"}
    if (isset($response['http_code']) && ($response['http_code'] === 200 || $response['http_code'] === 201)) {
        // Store the test OTP temporarily if you want to use the verify test function
        set_transient('ready_admin_test_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        set_transient('ready_admin_test_otp_ref_' . $phone, isset($response['OTPReferenceId']) ? $response['OTPReferenceId'] : null, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message' => 'پیامک آزمایشی با موفقیت ارسال شد. کد OTP (برای تست): ' . $otp,
            'response' => $response
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : 'خطای ناشناخته از سمت API راه پیام.';
        if (isset($response['Message']) && is_array($response['Message'])) { // Capture array messages
            $error_message = implode(', ', $response['Message']);
        } else if (isset($response['Message'])) {
             $error_message = $response['Message'];
        }
        wp_send_json_error('خطا در ارسال: ' . $error_message);
    }
});

/**
 * AJAX handler for verifying a test OTP.
 */
add_action('wp_ajax_ready_admin_verify_test_otp', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('شما مجوز کافی ندارید.', 403);
    }

    if (!isset($_POST['phone_number'], $_POST['otp_code'])) {
        wp_send_json_error('شماره تلفن یا کد تایید وارد نشده است.');
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error('شماره تلفن نامعتبر است.');
    }
    $international_phone = '+98' . substr($phone, 1);

    $api_key = get_option('ready_sms_api_key');
    if (empty($api_key)) {
        wp_send_json_error('کلید API پیامک تنظیم نشده است.');
    }
    
    // For admin test, we can first check against the transient if we stored it,
    // or directly call Msgway's verify if their system is meant to verify the OTP sent via "send" with template.
    // The current plugin setup for front-end calls Msgway's verify. So we do the same here.

    $params = [
        "OTP"    => $otp_code,
        "mobile" => $international_phone,
    ];

    $response = ready_msgway_api_request('otp/verify', $api_key, ['body' => $params], 'POST');

    if (is_wp_error($response)) {
        wp_send_json_error('خطا در بررسی کد تایید: ' . $response->get_error_message());
    }
    
    // Msgway SimpleVerifyOTP.php example suggests:
    // status: 1 and message: "Verified" for success.
    // status: 0 for failure.
    if (isset($response['status']) && $response['status'] == 1 && isset($response['message']) && $response['message'] == 'Verified') {
        delete_transient('ready_admin_test_otp_' . $phone); // Clean up test transient
        delete_transient('ready_admin_test_otp_ref_' . $phone);
        wp_send_json_success([
            'message' => 'کد تایید صحیح است.',
            'response' => $response
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : 'کد تایید نامعتبر یا خطای دیگر.';
        wp_send_json_error($error_message);
    }
});


/**
 * AJAX handler for checking SMS status.
 */
add_action('wp_ajax_ready_admin_check_sms_status', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('شما مجوز کافی ندارید.', 403);
    }

    if (!isset($_POST['reference_id'])) {
        wp_send_json_error('شناسه مرجع (OTPReferenceID) وارد نشده است.');
    }
    $reference_id = sanitize_text_field(wp_unslash($_POST['reference_id']));
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error('کلید API پیامک تنظیم نشده است.');
    }

    $response = ready_msgway_api_request('status/' . $reference_id, $api_key, [], 'GET');

    if (is_wp_error($response)) {
        wp_send_json_error('خطا در دریافت وضعیت: ' . $response->get_error_message());
    }
    
    // Msgway SimpleGetMessageStatus.php example implies the response body is an array of status objects.
    // Or a single status object if not batch.
    // A successful GET (HTTP 200) would contain the status info.
    if (isset($response['http_code']) && $response['http_code'] === 200) {
         wp_send_json_success([
            'message' => 'وضعیت پیامک دریافت شد.',
            'response' => $response // This will be the array/object from Msgway
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : (isset($response['Message']) ? $response['Message'] : 'خطای ناشناخته.');
        wp_send_json_error('خطا در دریافت وضعیت: ' . $error_message);
    }
});

/**
 * AJAX handler for getting template information.
 */
add_action('wp_ajax_ready_admin_get_template_info', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('شما مجوز کافی ندارید.', 403);
    }

    if (!isset($_POST['template_id'])) {
        wp_send_json_error('شناسه قالب (Template ID) وارد نشده است.');
    }
    $template_id = sanitize_text_field(wp_unslash($_POST['template_id']));
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error('کلید API پیامک تنظیم نشده است.');
    }

    $response = ready_msgway_api_request('template/' . $template_id, $api_key, [], 'GET');
    
    if (is_wp_error($response)) {
        wp_send_json_error('خطا در دریافت اطلاعات قالب: ' . $response->get_error_message());
    }

    if (isset($response['http_code']) && $response['http_code'] === 200 && isset($response['id'])) {
         wp_send_json_success([
            'message' => 'اطلاعات قالب دریافت شد.',
            'response' => $response
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : (isset($response['Message']) ? $response['Message'] : 'قالب یافت نشد یا خطای دیگر.');
        wp_send_json_error($error_message);
    }
});


/**
 * AJAX handler for getting credit balance.
 */
add_action('wp_ajax_ready_admin_get_balance', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('شما مجوز کافی ندارید.', 403);
    }
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error('کلید API پیامک تنظیم نشده است.');
    }
    
    $response = ready_msgway_api_request('credit', $api_key, [], 'GET');

    if (is_wp_error($response)) {
        wp_send_json_error('خطا در دریافت موجودی: ' . $response->get_error_message());
    }

    // Msgway SimpleGetCredit.php example expects a body like: {"credit":1000.0,"currency":"ریال"}
    if (isset($response['http_code']) && $response['http_code'] === 200 && isset($response['credit'])) {
         wp_send_json_success([
            'message' => 'موجودی دریافت شد.',
            'credit' => $response['credit'],
            'currency' => isset($response['currency']) ? $response['currency'] : 'ریال'
        ]);
    } else {
        $error_message = isset($response['message']) ? $response['message'] : (isset($response['Message']) ? $response['Message'] : 'خطای ناشناخته.');
        wp_send_json_error('خطا در دریافت موجودی: ' . $error_message);
    }
});

?>
