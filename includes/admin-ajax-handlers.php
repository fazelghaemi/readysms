<?php
// File: includes/admin-ajax-handlers.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to make requests to Msgway API.
 */
if (!function_exists('ready_msgway_api_request')) {
    function ready_msgway_api_request($endpoint_path, $api_key, $args = [], $method = 'POST', $payload_is_json = true) {
        $base_url = 'https://api.msgway.com/';
        $url = (strpos($endpoint_path, 'http') === 0) ? $endpoint_path : $base_url . ltrim($endpoint_path, '/');

        $default_headers = [
            'apiKey'       => $api_key,
        ];
        
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && $payload_is_json) {
            $default_headers['Content-Type'] = 'application/json';
        }
        
        $request_args = [
            'headers' => $default_headers,
            'timeout' => 30, 
            'method'  => strtoupper($method),
        ];

        if (isset($args['headers'])) {
            $request_args['headers'] = array_merge($default_headers, $args['headers']);
        }

        if (isset($args['body'])) {
            if ($payload_is_json && is_array($args['body'])) {
                $request_args['body'] = wp_json_encode($args['body']);
            } else {
                $request_args['body'] = $args['body'];
            }
        }

        $response = wp_remote_request($url, $request_args);

        if (is_wp_error($response)) {
            error_log("ReadySMS Msgway API Request (WP_Error) - Method: {$method}, URL: {$url}, Error: " . $response->get_error_message());
            return $response;
        }

        $body_raw = wp_remote_retrieve_body($response);
        $decoded_body_as_array = json_decode($body_raw, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code >= 400) {
            $error_message_from_api = '';
            if (is_array($decoded_body_as_array) && isset($decoded_body_as_array['message'])) {
                $error_message_from_api = is_array($decoded_body_as_array['message']) ? implode(', ', $decoded_body_as_array['message']) : $decoded_body_as_array['message'];
            } elseif (is_array($decoded_body_as_array) && isset($decoded_body_as_array['Message'])) {
                 $error_message_from_api = is_array($decoded_body_as_array['Message']) ? implode(', ', $decoded_body_as_array['Message']) : $decoded_body_as_array['Message'];
            } elseif(!empty($body_raw) && is_null($decoded_body_as_array)) {
                $error_message_from_api = substr(strip_tags($body_raw), 0, 250);
            }
            $final_error_message = sprintf(__('خطای API راه پیام: کد وضعیت %s', 'readysms'), $http_code) . (!empty($error_message_from_api) ? ' - ' . $error_message_from_api : '');
            error_log("ReadySMS Msgway API Request (HTTP Error) - Method: {$method}, URL: {$url}, Code: {$http_code}, RawBody: {$body_raw}, ErrorMessage: {$final_error_message}");
            return new WP_Error('msgway_api_http_error', $final_error_message, ['status' => $http_code, 'body_raw' => $body_raw, 'decoded_body' => $decoded_body_as_array]);
        }
        
        if ($http_code < 300 && is_null($decoded_body_as_array) && !empty($body_raw) && strpos(wp_remote_retrieve_header($response, 'content-type'), 'application/json') === false && strpos(wp_remote_retrieve_header($response, 'content-type'), 'text/plain') !== false) {
             error_log("ReadySMS Msgway API Request (Success with Plain Text Body) - Method: {$method}, URL: {$url}, Code: {$http_code}, RawBody: {$body_raw}");
        } elseif ($http_code < 300 && is_null($decoded_body_as_array) && !empty($body_raw)) {
            error_log("ReadySMS Msgway API Request (Success with Non-Decodable JSON Body) - Method: {$method}, URL: {$url}, Code: {$http_code}, RawBody: {$body_raw}");
            return new WP_Error('msgway_json_decode_error', __('پاسخ دریافت شده از API راه پیام فرمت JSON مورد انتظار را ندارد، اگرچه کد وضعیت HTTP موفقیت‌آمیز بود.', 'readysms'), ['status' => $http_code, 'body_raw' => $body_raw]);
        }
        
        if (is_array($decoded_body_as_array)) {
            $decoded_body_as_array['http_code_debug'] = $http_code;
        } elseif (is_null($decoded_body_as_array) && $http_code < 300 && empty($body_raw)) {
            return ['http_code_debug' => $http_code, '_empty_response' => true];
        }
        
        return $decoded_body_as_array;
    }
}

if (!function_exists('readysms_normalize_phone_number')) {
    function readysms_normalize_phone_number($phone_input) {
        $country_code_mode = get_option('ready_sms_country_code_mode', 'iran_only');
        $sanitized_phone = preg_replace('/[^0-9+]/', '', $phone_input);
        if ($country_code_mode === 'iran_only') {
            if (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) { return '+98' . substr($sanitized_phone, 1); }
            elseif (preg_match('/^\+989[0-9]{9}$/', $sanitized_phone)) { return $sanitized_phone; }
            elseif (preg_match('/^989[0-9]{9}$/', $sanitized_phone)) { return '+' . $sanitized_phone; }
            return false;
        } else {
            if (strpos($sanitized_phone, '+') === 0 && strlen($sanitized_phone) > 7 && strlen($sanitized_phone) < 16) { return $sanitized_phone; }
            elseif (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) { return '+98' . substr($sanitized_phone, 1); }
            elseif (strlen($sanitized_phone) === 10 && substr($sanitized_phone, 0, 1) === '9') { return '+98' . $sanitized_phone; }
            return false; 
        }
    }
}
if (!function_exists('readysms_get_storable_phone_format')) {
    function readysms_get_storable_phone_format($phone_input_original) {
        $sanitized_phone = preg_replace('/[^0-9+]/', '', $phone_input_original);
        if (preg_match('/^(\+|98)?(0)?(9[0-9]{9})$/', $sanitized_phone, $matches)) {
            return '0' . $matches[3];
        }
        if (get_option('ready_sms_country_code_mode', 'iran_only') === 'all_countries' && strpos($sanitized_phone, '+') === 0) {
            return $sanitized_phone;
        }
        return preg_replace('/[^0-9]/', '', $phone_input_original);
    }
}

add_action('wp_ajax_ready_admin_send_test_otp', function () {
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    if (!isset($_POST['phone_number'])) wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));

    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $international_phone_for_api = readysms_normalize_phone_number($phone_input);
    if (false === $international_phone_for_api) {
        wp_send_json_error(__('فرمت شماره موبایل وارد شده برای تست صحیح نیست یا با تنظیمات کد کشور مطابقت ندارد.', 'readysms'));
        return;
    }
    $phone_for_transient_key = readysms_get_storable_phone_format($phone_input);

    $api_key = get_option('ready_sms_api_key');
    $sms_pattern_code = get_option('ready_sms_pattern_code');
    $send_method_option = get_option('ready_sms_send_method', 'sms');
    $line_number = get_option('ready_sms_number');

    if (empty($api_key)) wp_send_json_error(__('کلید API پیامک در تنظیمات مشخص نشده است.', 'readysms'));

    $final_send_method = $send_method_option;
    if (strpos($international_phone_for_api, '+98') !== 0 && $final_send_method === 'ivr') {
        $final_send_method = 'sms';
    }
    if ($final_send_method === 'sms' && empty($sms_pattern_code)) {
        wp_send_json_error(__('کد پترن پیامک در تنظیمات مشخص نشده است (برای ارسال پیامک لازم است).', 'readysms'));
        return;
    }

    $otp_length = (int)get_option('ready_sms_otp_length', 6);
    if ($otp_length < 4 || $otp_length > 7) $otp_length = 6;
    $min_otp_val = pow(10, $otp_length - 1);
    $max_otp_val = pow(10, $otp_length) - 1;
    $otp = (string)wp_rand($min_otp_val, $max_otp_val);

    $payload = ["mobile" => $international_phone_for_api, "method" => $final_send_method];
    if ($final_send_method === 'ivr') {
        $payload["templateID"] = 2;
        $payload["code"] = $otp;
    } else {
        $payload["templateID"] = (int)$sms_pattern_code;
        $payload["params"]     = [$otp];
        if (!empty($line_number)) $payload["lineNumber"] = $line_number;
    }

    $response_from_api = ready_msgway_api_request('send', $api_key, ['body' => $payload], 'POST', true);

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در ارسال پیامک آزمایشی (%1$s): %2$s', 'readysms'), $final_send_method, $response_from_api->get_error_message()));
    }

    if (is_array($response_from_api) && isset($response_from_api['status']) && $response_from_api['status'] === 'success' && isset($response_from_api['referenceID'])) {
        set_transient('ready_admin_test_otp_' . $phone_for_transient_key, $otp, 5 * MINUTE_IN_SECONDS);
        $method_text = ($final_send_method === 'ivr') ? __('تماس صوتی', 'readysms') : __('پیامک', 'readysms');
        wp_send_json_success([
            'message' => sprintf(__('کد تایید آزمایشی با موفقیت از طریق %1$s ارسال شد. کد OTP (%2$d رقمی برای تست): %3$s. شناسه مرجع: %4$s', 'readysms'), $method_text, $otp_length, $otp, esc_html($response_from_api['referenceID'])),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('خطای ناشناخته از سمت API راه پیام هنگام ارسال پیامک آزمایشی.', 'readysms');
        if(is_array($response_from_api) && !empty($response_from_api['message'])) { $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message']; }
        elseif(is_array($response_from_api) && !empty($response_from_api['Message'])) { $error_message = is_array($response_from_api['Message']) ? implode('; ', $response_from_api['Message']) : $response_from_api['Message']; }
        elseif (is_array($response_from_api) && isset($response_from_api['error']) && !is_null($response_from_api['error'])) { $error_message = is_array($response_from_api['error']) ? wp_json_encode($response_from_api['error']) : (string) $response_from_api['error'];}
        error_log("ReadySMS Admin Send Test OTP (Method: {$final_send_method}) - Failed. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error(sprintf(__('خطا در پردازش پاسخ ارسال تست (%1$s): %2$s', 'readysms'), $final_send_method, $error_message));
    }
});

add_action('wp_ajax_ready_admin_verify_test_otp', function () {
    // ... (کد این تابع مشابه نسخه قبلی با تایید OTP از طریق Transient) ...
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    if (!isset($_POST['phone_number'], $_POST['otp_code'])) wp_send_json_error(__('شماره تلفن یا کد تایید وارد نشده است.', 'readysms'));

    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $phone_for_transient_key = readysms_get_storable_phone_format($phone_input);

    if (false === $phone_for_transient_key) {
        wp_send_json_error(__('فرمت شماره موبایل برای کلید ترنزینت نامعتبر است.', 'readysms'));
        return;
    }

    $stored_otp = get_transient('ready_admin_test_otp_' . $phone_for_transient_key);

    if (false === $stored_otp) {
        wp_send_json_error(__('کد تایید آزمایشی منقضی شده یا نامعتبر است. لطفاً مجدداً درخواست کد کنید.', 'readysms'));
        return;
    }

    if ($stored_otp === $otp_code) {
        delete_transient('ready_admin_test_otp_' . $phone_for_transient_key);
        wp_send_json_success([
            'message' => __('کد تایید آزمایشی صحیح است.', 'readysms'),
            'response_data' => ['status' => 1, 'message' => 'Verified Locally']
        ]);
    } else {
        error_log("ReadySMS Admin Verify Test OTP - Invalid OTP attempt. User entered: {$otp_code}, Stored OTP: {$stored_otp}, Phone for transient key: {$phone_for_transient_key}");
        wp_send_json_error(__('کد تایید آزمایشی وارد شده نامعتبر است.', 'readysms'));
    }
});

// تابع دریافت وضعیت پیامک حذف شد.
// add_action('wp_ajax_ready_admin_check_sms_status', ...); // این خط باید حذف یا کامنت شود

/**
 * AJAX handler for getting template information - Updated to use POST and correct endpoint
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

    $payload = [
        "templateID" => (int)$template_id_to_test,
    ];
    // Endpoint 'template/get' and Method 'POST'
    $response_from_api = ready_msgway_api_request('template/get', $api_key, ['body' => $payload], 'POST', true);
    
    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در دریافت اطلاعات قالب: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    // --- START OF CORRECTION based on your new log ---
    // بررسی پاسخ واقعی API:
    // {"status":"success", "data":{"template":"متن الگو"}}
    if (is_array($response_from_api) && 
        isset($response_from_api['status']) && $response_from_api['status'] === 'success' &&
        isset($response_from_api['data']) && is_array($response_from_api['data']) &&
        isset($response_from_api['data']['template']) && is_string($response_from_api['data']['template'])) {
        
        $template_text = $response_from_api['data']['template'];

         wp_send_json_success([
            'message' => sprintf(__('اطلاعات قالب با شناسه %s با موفقیت دریافت شد.', 'readysms'), esc_html($template_id_to_test)),
            'response_data' => [ // ساخت یک آرایه برای نمایش بهتر در بخش نتیجه
                'template_id_requested' => $template_id_to_test,
                'template_text' => $template_text,
                'full_api_response' => $response_from_api // پاسخ کامل API برای اطلاعات بیشتر
            ]
        ]);
    } else {
        // اگر ساختار پاسخ با چیزی که انتظار داریم مطابقت نداشته باشد
        $error_message = __('پاسخ دریافت شده برای اطلاعات قالب، ساختار مورد انتظار را ندارد یا حاوی خطا است.', 'readysms');
        
        if(is_array($response_from_api) && isset($response_from_api['error']) && is_array($response_from_api['error']) && !empty($response_from_api['error']['message'])) {
            $error_message = (string) $response_from_api['error']['message'];
        } elseif (is_array($response_from_api) && !empty($response_from_api['message'])) {
            $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message'];
        }

        error_log("ReadySMS Admin Get Template - Invalid or Incomplete API Response. Expected 'status'=='success' and 'data.template' as string. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error($error_message);
    }
    // --- END OF CORRECTION ---
});


add_action('wp_ajax_ready_admin_get_balance', function () {
    // ... (کد این تابع مشابه نسخه قبلی با اصلاحات اعمال شده برای balance/get با متد POST) ...
    check_ajax_referer('readysms-admin-nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(__('شما مجوز کافی ندارید.', 'readysms'), 403);
    $api_key = get_option('ready_sms_api_key');
    if (empty($api_key)) wp_send_json_error(__('کلید API پیامک تنظیم نشده است.', 'readysms'));
    
    $response_from_api = ready_msgway_api_request('https://api.msgway.com/balance/get', $api_key, [], 'POST', false);

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در دریافت موجودی: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    if (is_array($response_from_api) && isset($response_from_api['status']) && $response_from_api['status'] == 1 && isset($response_from_api['balance']) && isset($response_from_api['currencyName'])) {
         wp_send_json_success([
            'message' => sprintf(__('موجودی شما: %s %s', 'readysms'), number_format_i18n((float)$response_from_api['balance'], 0), esc_html($response_from_api['currencyName'])),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('پاسخ دریافت شده برای موجودی، معتبر یا کامل نیست.', 'readysms');
         if(is_array($response_from_api) && !empty($response_from_api['message'])) { $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message']; }
         elseif (is_array($response_from_api) && !empty($response_from_api['Message'])) { $error_message = is_array($response_from_api['Message']) ? implode('; ', $response_from_api['Message']) : $response_from_api['Message']; }
        error_log("ReadySMS Admin Get Balance - Invalid or Incomplete API Response. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error($error_message);
    }
});

add_action('wp_ajax_readysms_export_users', function () {
    // ... (کد این تابع مشابه نسخه قبلی برای خروجی CSV) ...
    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'readysms-admin-nonce')) {
        wp_die(__('عملیات غیرمجاز یا nonce نامعتبر.', 'readysms'), __('خطا', 'readysms'), ['response' => 403]);
    }
    if (!current_user_can('manage_options')) {
        wp_die(__('شما مجوز کافی برای انجام این کار ندارید.', 'readysms'), __('خطا', 'readysms'), ['response' => 403]);
    }

    $filename = "readysms_users_export_" . date("Y-m-d_H-i-s") . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) wp_die(__('خطا در ایجاد فایل خروجی.', 'readysms'), __('خطا', 'readysms'), ['response' => 500]);
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fputcsv($output, [__('ردیف', 'readysms'), __('نام کاربری', 'readysms'), __('ایمیل', 'readysms'), __('نام نمایشی', 'readysms'), __('شماره موبایل (از نام کاربری)', 'readysms'), __('تاریخ عضویت', 'readysms'), __('نقش کاربری', 'readysms')]);

    $args = ['fields'  => ['ID', 'user_login', 'user_email', 'display_name', 'user_registered'], 'orderby' => 'ID', 'order'   => 'ASC'];
    $users = get_users($args);
    $row_number = 1;

    foreach ($users as $user_obj) {
        $user_data = get_userdata($user_obj->ID);
        $roles = !empty($user_data->roles) ? implode(', ', array_map('translate_user_role', $user_data->roles)) : '';
        $phone_from_username = (preg_match('/^(09\d{9})$/', $user_obj->user_login) || strpos($user_obj->user_login, '+') === 0) ? $user_obj->user_login : '';
        fputcsv($output, [readysms_number_to_persian($row_number++), $user_obj->user_login, $user_obj->user_email, $user_obj->display_name, $phone_from_username, get_date_from_gmt($user_obj->user_registered, 'Y-m-d H:i:s'), $roles]);
    }
    fclose($output);
    wp_die();
});

if (!function_exists('readysms_number_to_persian')) {
    function readysms_number_to_persian($number) {
        $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($english_digits, $persian_digits, (string) $number);
    }
}
?>
