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
            // 'Content-Type' => 'application/json', // Removed for GET, added specifically for POST
            'apiKey'       => $api_key,
        ];
        
        $request_args = [
            'headers' => $default_headers,
            'timeout' => 30, // Seconds
        ];

        // Apply specific headers from $args if provided, merging with defaults
        if (isset($args['headers'])) {
            $request_args['headers'] = array_merge($default_headers, $args['headers']);
        }


        if (strtoupper($method) === 'POST') {
            // Ensure Content-Type is set for POST requests
            $request_args['headers']['Content-Type'] = isset($request_args['headers']['Content-Type']) ? $request_args['headers']['Content-Type'] : 'application/json';
            $request_args['body'] = isset($args['body']) ? wp_json_encode($args['body']) : null;
            $response = wp_remote_post($url, $request_args);
        } else { // GET
            if (!empty($args['params'])) { // For query string parameters in GET
                $url = add_query_arg($args['params'], $url);
            }
            $response = wp_remote_get($url, $request_args);
        }

        if (is_wp_error($response)) {
            error_log("ReadySMS Msgway API Request (WP_Error) - Method: {$method}, URL: {$url}, Error: " . $response->get_error_message());
            return $response; // Return WP_Error object
        }

        $body_raw = wp_remote_retrieve_body($response);
        $decoded_body_as_array = json_decode($body_raw, true); // Decode as associative array
        $http_code = wp_remote_retrieve_response_code($response);

        // Handle HTTP errors (4xx, 5xx)
        if ($http_code >= 400) {
            $error_message_from_api = '';
            if (is_array($decoded_body_as_array) && isset($decoded_body_as_array['message'])) {
                $error_message_from_api = is_array($decoded_body_as_array['message']) ? implode(', ', $decoded_body_as_array['message']) : $decoded_body_as_array['message'];
            } elseif (is_array($decoded_body_as_array) && isset($decoded_body_as_array['Message'])) {
                 $error_message_from_api = is_array($decoded_body_as_array['Message']) ? implode(', ', $decoded_body_as_array['Message']) : $decoded_body_as_array['Message'];
            } elseif(!empty($body_raw) && is_null($decoded_body_as_array)) { // If response is not JSON but has content
                $error_message_from_api = substr(strip_tags($body_raw), 0, 250); // Show part of the non-JSON response
            }
            $final_error_message = sprintf(__('خطای API راه پیام: کد وضعیت %s', 'readysms'), $http_code) . (!empty($error_message_from_api) ? ' - ' . $error_message_from_api : '');
            error_log("ReadySMS Msgway API Request (HTTP Error) - Method: {$method}, URL: {$url}, Code: {$http_code}, RawBody: {$body_raw}, ErrorMessage: {$final_error_message}");
            return new WP_Error('msgway_api_http_error', $final_error_message, ['status' => $http_code, 'body_raw' => $body_raw, 'decoded_body' => $decoded_body_as_array]);
        }
        
        // Handle successful HTTP codes (2xx) but unexpected response format
        if ($http_code < 300 && is_null($decoded_body_as_array) && !empty($body_raw)) {
            // Successful HTTP status, but the body was not valid JSON (e.g., HTML, plain text error not caught by HTTP status)
            error_log("ReadySMS Msgway API Request (Success with Non-JSON Body) - Method: {$method}, URL: {$url}, Code: {$http_code}, RawBody: {$body_raw}");
            return new WP_Error('msgway_unexpected_response_type', __('پاسخ دریافت شده از API راه پیام فرمت JSON مورد انتظار را ندارد، اگرچه کد وضعیت HTTP موفقیت‌آمیز بود.', 'readysms'), ['status' => $http_code, 'body_raw' => $body_raw]);
        }
        
        // If decoded_body_as_array is successfully created (even if it's an empty array from an empty JSON response like "[]" or "{}")
        // or if it's null because the raw body was empty or "null" (which json_decode turns to null)
        // We add http_code_debug for logging/debugging in the calling functions if needed.
        if (is_array($decoded_body_as_array)) {
            $decoded_body_as_array['http_code_debug'] = $http_code;
        } elseif (is_null($decoded_body_as_array) && $http_code < 300) {
            // If body was "null" or empty, and HTTP was success, decoded_body_as_array is null.
            // This is fine if the calling function expects null for some scenarios.
            // For our specific cases (credit, template), we expect arrays.
        }
        
        return $decoded_body_as_array; // Returns an associative array or null if JSON was "null" or decoding failed for other reasons.
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

    $otp_length = (int)get_option('ready_sms_otp_length', 6);
    if ($otp_length < 4 || $otp_length > 7) {
        $otp_length = 6;
    }
    $min_otp_val = pow(10, $otp_length - 1);
    $max_otp_val = pow(10, $otp_length) - 1;
    $otp = (string)wp_rand($min_otp_val, $max_otp_val);

    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int)$template_id,
        "params"     => [$otp] 
    ];
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

    $response_from_api = ready_msgway_api_request('send', $api_key, ['body' => $payload], 'POST');

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در ارسال پیامک آزمایشی: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    // Based on user's previous log for a successful send: response has status: "success" and "referenceID"
    if (is_array($response_from_api) && isset($response_from_api['status']) && $response_from_api['status'] === 'success' && isset($response_from_api['referenceID'])) {
        set_transient('ready_admin_test_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message' => sprintf(__('پیامک آزمایشی با موفقیت ارسال شد. کد OTP (%1$d رقمی برای تست): %2$s. شناسه مرجع: %3$s', 'readysms'), $otp_length, $otp, esc_html($response_from_api['referenceID'])),
            'response_data' => $response_from_api // Send full API response for admin to see
        ]);
    } else {
        $error_message = __('خطای ناشناخته از سمت API راه پیام هنگام ارسال پیامک آزمایشی.', 'readysms');
        if(is_array($response_from_api) && !empty($response_from_api['message'])) {
            $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message'];
        } elseif(is_array($response_from_api) && !empty($response_from_api['Message'])) {
            $error_message = is_array($response_from_api['Message']) ? implode('; ', $response_from_api['Message']) : $response_from_api['Message'];
        } elseif (is_array($response_from_api) && isset($response_from_api['error']) && !is_null($response_from_api['error'])) {
             $error_message = (string) $response_from_api['error'];
        }
        error_log("ReadySMS Admin Send Test OTP - API call not considered success or missing expected fields. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error(sprintf(__('خطا در پردازش پاسخ ارسال تست: %s', 'readysms'), $error_message));
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

    $response_from_api = ready_msgway_api_request('otp/verify', $api_key, ['body' => $payload], 'POST');

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در بررسی کد تایید: %s', 'readysms'), $response_from_api->get_error_message()));
    }
    
    // Expected success for verify: status: 1 and message: "Verified" (case-insensitive for message)
    if (is_array($response_from_api) && isset($response_from_api['status']) && $response_from_api['status'] == 1 && isset($response_from_api['message']) && strtolower($response_from_api['message']) == 'verified') {
        delete_transient('ready_admin_test_otp_' . $phone);
        wp_send_json_success([
            'message' => __('کد تایید صحیح و با موفقیت توسط راه پیام بررسی شد.', 'readysms'),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('کد تایید نامعتبر یا خطای دیگر از سمت API راه پیام.', 'readysms');
        if(is_array($response_from_api) && !empty($response_from_api['message'])) {
            $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message'];
        }
        error_log("ReadySMS Admin Verify Test OTP - API call not considered success or missing expected fields. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
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

    if (!isset($_POST['reference_id']) || empty(trim($_POST['reference_id']))) {
        wp_send_json_error(__('شناسه مرجع (referenceID) وارد نشده است.', 'readysms'));
    }
    $reference_id = sanitize_text_field(trim(wp_unslash($_POST['reference_id'])));
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    }

    $response_from_api = ready_msgway_api_request('status/' . $reference_id, $api_key, [], 'GET');

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در دریافت وضعیت پیامک: %s', 'readysms'), $response_from_api->get_error_message()));
    }
    
    // A successful GET for status (HTTP 200) is expected to return an array of status info, or a single status object.
    // The exact structure of a "successful" data response for status needs to be defined by Msgway docs or testing.
    // For now, if it's an array and not an error, consider it a valid response to display.
    if (is_array($response_from_api)) { 
         wp_send_json_success([
            'message' => __('اطلاعات وضعیت پیامک (یا گروه پیامک‌ها) از راه پیام دریافت شد.', 'readysms'),
            'response_data' => $response_from_api
        ]);
    } else {
        // This case would be hit if ready_msgway_api_request returned null (e.g. empty JSON response "null")
        // or a scalar value which is not expected for this endpoint.
        $error_message = __('پاسخ دریافت شده برای وضعیت پیامک، ساختار معتبری ندارد.', 'readysms');
        error_log("ReadySMS Admin Check Status - Invalid or non-array API Response. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error($error_message);
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

    if (!isset($_POST['template_id_to_test']) || empty(trim($_POST['template_id_to_test']))) {
        wp_send_json_error(__('شناسه قالب (Template ID) وارد نشده است.', 'readysms'));
    }
    $template_id_to_test = sanitize_text_field(trim(wp_unslash($_POST['template_id_to_test'])));
    $api_key = get_option('ready_sms_api_key');

    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    }

    $response_from_api = ready_msgway_api_request('template/' . $template_id_to_test, $api_key, [], 'GET');
    
    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در دریافت اطلاعات قالب: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    // Expected success: response is an array and contains 'id' and 'name' (or similar primary keys for a template)
    if (is_array($response_from_api) && isset($response_from_api['id']) && isset($response_from_api['name'])) {
         wp_send_json_success([
            'message' => sprintf(__('اطلاعات قالب "%s" (ID: %s) با موفقیت از راه پیام دریافت شد.', 'readysms'), esc_html($response_from_api['name']), esc_html($response_from_api['id'])),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('پاسخ دریافت شده برای اطلاعات قالب، معتبر یا کامل نیست.', 'readysms');
        if(is_array($response_from_api) && !empty($response_from_api['message'])) {
            $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message'];
        } elseif (is_array($response_from_api) && !empty($response_from_api['Message'])) {
            $error_message = is_array($response_from_api['Message']) ? implode('; ', $response_from_api['Message']) : $response_from_api['Message'];
        }
        // Note: HTTP 404 for template not found is handled by ready_msgway_api_request returning WP_Error.
        error_log("ReadySMS Admin Get Template - Invalid or Incomplete API Response (expected id and name). Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
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
    
    $response_from_api = ready_msgway_api_request('credit', $api_key, [], 'GET');

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در دریافت موجودی: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    // Expected success: response is an array and contains 'credit'
    if (is_array($response_from_api) && isset($response_from_api['credit'])) {
         wp_send_json_success([
            'message' => sprintf(__('موجودی شما: %s %s', 'readysms'), number_format_i18n((float)$response_from_api['credit'], 0), esc_html(isset($response_from_api['currency']) ? $response_from_api['currency'] : __('ریال', 'readysms'))),
            'response_data' => $response_from_api // Contains credit, currency, and http_code_debug
        ]);
    } else {
        $error_message = __('پاسخ دریافت شده برای موجودی، معتبر یا کامل نیست.', 'readysms');
         if(is_array($response_from_api) && !empty($response_from_api['message'])) {
            $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message'];
        } elseif (is_array($response_from_api) && !empty($response_from_api['Message'])) {
            $error_message = is_array($response_from_api['Message']) ? implode('; ', $response_from_api['Message']) : $response_from_api['Message'];
        }
        error_log("ReadySMS Admin Get Balance - Invalid or Incomplete API Response (expected credit key). Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error($error_message);
    }
});

?>
