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
            'timeout' => 30,
        ];

        if (strtoupper($method) === 'POST') {
            $request_args['body'] = isset($args['body']) ? wp_json_encode($args['body']) : null; // Use wp_json_encode
            $response = wp_remote_post($url, $request_args);
        } else { // GET
            if (!empty($args['params'])) {
                $url = add_query_arg($args['params'], $url);
            }
            $response = wp_remote_get($url, $request_args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body_raw = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body_raw, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code >= 400) {
            $error_message = sprintf(__('Msgway API Error: HTTP %s', 'readysms'), $http_code);
            $api_error_message = '';
            if (is_array($decoded_body) && isset($decoded_body['message'])) {
                $api_error_message = is_array($decoded_body['message']) ? implode(', ', $decoded_body['message']) : $decoded_body['message'];
            } elseif (is_array($decoded_body) && isset($decoded_body['Message'])) {
                 $api_error_message = is_array($decoded_body['Message']) ? implode(', ', $decoded_body['Message']) : $decoded_body['Message'];
            }
            if (!empty($api_error_message)) {
                $error_message .= ' - ' . $api_error_message;
            }
            // Log the raw body for severe errors too
            error_log("ReadySMS Msgway API Request Helper - HTTP Error {$http_code}. Raw Body: {$body_raw}");
            return new WP_Error('msgway_api_error', $error_message, ['status' => $http_code, 'body_raw' => $body_raw, 'decoded_body' => $decoded_body]);
        }
        
        if (is_array($decoded_body)) {
            $decoded_body['http_code_debug'] = $http_code; // For debugging success cases
        } else {
            if ($http_code < 300 && !is_null(json_decode($body_raw))) { // Check if it was valid JSON that didn't decode to array, or if it was just text
                // It was valid JSON but not an array (e.g. "true", "null", a number, or a string)
                // Or it was plain text. For API tests, we expect JSON.
                // If an endpoint is known to return plain text on success, this needs specific handling.
                // For now, if we expected JSON array and didn't get it, it's an issue.
                error_log("ReadySMS Msgway API Request Helper - Expected JSON array, got different valid JSON or plain text. HTTP: {$http_code}. Raw Body: {$body_raw}");
                // Returning decoded_body which might be null or the scalar value.
                // Specific handlers must be robust.
            } else if ($http_code < 300 && is_null(json_decode($body_raw)) && !empty($body_raw)) {
                // Successful HTTP code, but body is not JSON and not empty (e.g. HTML success page from a proxy)
                 error_log("ReadySMS Msgway API Request Helper - Successful HTTP but non-JSON body. HTTP: {$http_code}. Raw Body: {$body_raw}");
                 return new WP_Error('msgway_unexpected_response', __('پاسخ غیرمنتظره از سرور API دریافت شد.', 'readysms'), ['status' => $http_code, 'body_raw' => $body_raw]);
            }
            // If $decoded_body is null (json_decode failed) and $body_raw was empty or also null, return null or error
            if (is_null($decoded_body) && $http_code < 300) {
                 error_log("ReadySMS Msgway API Request Helper - JSON decode failed for successful HTTP response. HTTP: {$http_code}. Raw Body: {$body_raw}");
                 // Fall through to return null, or new WP_Error if JSON is strictly required for success.
            }
        }
        return $decoded_body; // Can be array, null, or scalar if JSON was scalar
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

    // --- START OF OTP LENGTH CHANGE ---
    $otp_length = (int)get_option('ready_sms_otp_length', 6);
    if ($otp_length < 4 || $otp_length > 7) {
        $otp_length = 6;
    }
    $min_otp_val = pow(10, $otp_length - 1);
    $max_otp_val = pow(10, $otp_length) - 1;
    $otp = (string)wp_rand($min_otp_val, $max_otp_val); // Test OTP with configured length
    // --- END OF OTP LENGTH CHANGE ---


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

    // Based on your previous log, success includes status: "success" and "referenceID"
    if (is_array($response) && isset($response['status']) && $response['status'] === 'success' && isset($response['referenceID'])) {
        set_transient('ready_admin_test_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message' => sprintf(__('پیامک آزمایشی با موفقیت ارسال شد. کد OTP (%1$d رقمی برای تست): %2$s. شناسه مرجع: %3$s', 'readysms'), $otp_length, $otp, esc_html($response['referenceID'])),
            'response_data' => $response
        ]);
    } else {
        $error_message = __('خطای ناشناخته از سمت API راه پیام هنگام ارسال تست.', 'readysms');
        if(is_array($response) && !empty($response['message'])) {
            $error_message = is_array($response['message']) ? implode('; ', $response['message']) : $response['message'];
        } elseif(is_array($response) && !empty($response['Message'])) {
            $error_message = is_array($response['Message']) ? implode('; ', $response['Message']) : $response['Message'];
        } elseif (is_array($response) && isset($response['error']) && !is_null($response['error'])) {
             $error_message = (string) $response['error'];
        }
        // Log the full response for admin test failures as well
        error_log("ReadySMS Admin Send Test OTP - Failed. Response: " . wp_json_encode($response, JSON_UNESCAPED_UNICODE));
        wp_send_json_error(sprintf(__('خطا در ارسال تست: %s', 'readysms'), $error_message));
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
    
    if (is_array($response) && isset($response['status']) && $response['status'] == 1 && isset($response['message']) && strtolower($response['message']) == 'verified') {
        delete_transient('ready_admin_test_otp_' . $phone);
        wp_send_json_success([
            'message' => __('کد تایید صحیح و با موفقیت توسط راه پیام بررسی شد.', 'readysms'),
            'response_data' => $response
        ]);
    } else {
        $error_message = __('کد تایید نامعتبر یا خطای دیگر از سمت API راه پیام.', 'readysms');
        if(is_array($response) && !empty($response['message'])) {
            $error_message = is_array($response['message']) ? implode('; ', $response['message']) : $response['message'];
        }
        error_log("ReadySMS Admin Verify Test OTP - Failed. Response: " . wp_json_encode($response, JSON_UNESCAPED_UNICODE));
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
        wp_send_json_error(__('شناسه مرجع (referenceID) وارد نشده است.', 'readysms'));
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
    
    // Assuming a successful GET (HTTP 200) would contain the status info directly in the response array
    if (is_array($response) && isset($response['http_code_debug']) && $response['http_code_debug'] === 200) {
         // The structure of a successful status response needs to be known to provide a better message.
         // For now, just returning the whole response.
         wp_send_json_success([
            'message' => __('وضعیت پیامک با موفقیت از راه پیام دریافت شد.', 'readysms'),
            'response_data' => $response 
        ]);
    } else {
        $error_message = __('خطای ناشناخته هنگام دریافت وضعیت از API راه پیام.', 'readysms');
        if(is_array($response) && !empty($response['message'])) {
            $error_message = is_array($response['message']) ? implode('; ', $response['message']) : $response['message'];
        } elseif (is_array($response) && !empty($response['Message'])) {
            $error_message = is_array($response['Message']) ? implode('; ', $response['Message']) : $response['Message'];
        }
        error_log("ReadySMS Admin Check Status - Failed. Response: " . wp_json_encode($response, JSON_UNESCAPED_UNICODE));
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
    if (is_array($response) && isset($response['http_code_debug']) && $response['http_code_debug'] === 200 && isset($response['id'])) {
         wp_send_json_success([
            'message' => __('اطلاعات قالب با موفقیت از راه پیام دریافت شد.', 'readysms'),
            'response_data' => $response
        ]);
    } else {
        $error_message = __('قالب یافت نشد یا خطای دیگر از API راه پیام.', 'readysms');
        if(is_array($response) && !empty($response['message'])) {
            $error_message = is_array($response['message']) ? implode('; ', $response['message']) : $response['message'];
        } elseif(is_array($response) && !empty($response['Message'])) {
            $error_message = is_array($response['Message']) ? implode('; ', $response['Message']) : $response['Message'];
        } elseif (isset($response['http_code_debug']) && $response['http_code_debug'] === 404) {
            $error_message = __('قالب با این شناسه در سامانه راه پیام یافت نشد (404).', 'readysms');
        }
        error_log("ReadySMS Admin Get Template - Failed. Response: " . wp_json_encode($response, JSON_UNESCAPED_UNICODE));
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

    if (is_array($response) && isset($response['http_code_debug']) && $response['http_code_debug'] === 200 && isset($response['credit'])) {
         wp_send_json_success([
            'message' => sprintf(__('موجودی شما: %s %s', 'readysms'), number_format_i18n((float)$response['credit'], 2), esc_html(isset($response['currency']) ? $response['currency'] : __('ریال', 'readysms'))),
            'response_data' => $response
        ]);
    } else {
        $error_message = __('خطای ناشناخته هنگام دریافت موجودی از API راه پیام.', 'readysms');
         if(is_array($response) && !empty($response['message'])) {
            $error_message = is_array($response['message']) ? implode('; ', $response['message']) : $response['message'];
        } elseif (is_array($response) && !empty($response['Message'])) {
            $error_message = is_array($response['Message']) ? implode('; ', $response['Message']) : $response['Message'];
        }
        error_log("ReadySMS Admin Get Balance - Failed. Response: " . wp_json_encode($response, JSON_UNESCAPED_UNICODE));
        wp_send_json_error(sprintf(__('خطا در دریافت موجودی: %s', 'readysms'), $error_message));
    }
});

?>
