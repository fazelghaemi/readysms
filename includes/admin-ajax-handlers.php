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
        
        if ($http_code < 300 && is_null($decoded_body_as_array) && !empty($body_raw) && strpos(wp_remote_retrieve_header($response, 'content-type'), 'application/json') === false) {
             // اگر کد موفقیت آمیز بود ولی پاسخ JSON نبود (و انتظار JSON نداشتیم، مثلا برای balance/get که ممکن است text برگرداند طبق مثال curl شما)
             // در این حالت، پاسخ خام را برمیگردانیم اگر لازم باشد، یا بر اساس نوع محتوا تصمیم میگیریم
             // اما چون در تابع فراخوان کننده (مثلا برای balance) انتظار JSON داریم، این بخش باید با دقت بررسی شود.
             // اگر مطمئنیم که balance/get حتما JSON برمیگرداند، این شرط باید دقیقتر شود یا حذف شود.
             // فعلا فرض میکنیم همه پاسخ های موفقیت آمیز باید JSON باشند.
            error_log("ReadySMS Msgway API Request (Success with Non-JSON Body, but expected JSON) - Method: {$method}, URL: {$url}, Code: {$http_code}, RawBody: {$body_raw}");
            return new WP_Error('msgway_unexpected_response_type', __('پاسخ دریافت شده از API راه پیام فرمت JSON مورد انتظار را ندارد، اگرچه کد وضعیت HTTP موفقیت‌آمیز بود.', 'readysms'), ['status' => $http_code, 'body_raw' => $body_raw]);
        }
        
        if (is_array($decoded_body_as_array)) {
            $decoded_body_as_array['http_code_debug'] = $http_code;
        } elseif (is_null($decoded_body_as_array) && $http_code < 300 && empty($body_raw)) {
            return ['http_code_debug' => $http_code, '_empty_response' => true];
        }
        
        return $decoded_body_as_array;
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

    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));
    
    // استفاده از تابع نرمال‌سازی شماره موبایل
    $international_phone_for_api = readysms_normalize_phone_number($phone_input);
    if (false === $international_phone_for_api) {
        wp_send_json_error(__('فرمت شماره موبایل وارد شده برای تست صحیح نیست یا با تنظیمات کد کشور مطابقت ندارد.', 'readysms'));
        return;
    }

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
        "mobile"     => $international_phone_for_api,
        "method"     => "sms",
        "templateID" => (int)$template_id,
        "params"     => [$otp] 
    ];
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

    $response_from_api = ready_msgway_api_request('send', $api_key, ['body' => $payload], 'POST', true);

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در ارسال پیامک آزمایشی: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    if (is_array($response_from_api) && isset($response_from_api['status']) && $response_from_api['status'] === 'success' && isset($response_from_api['referenceID'])) {
        // برای تست ادمین، خود شماره موبایل ورودی (نه نرمال شده برای ترنزینت) استفاده می‌شود چون کاربر آن را می‌بیند.
        $phone_for_transient_key = preg_replace('/[^0-9]/', '', $phone_input);
         if (strpos($phone_for_transient_key, '98') === 0 && strlen($phone_for_transient_key) > 10) {
            $phone_for_transient_key = '0' . substr($phone_for_transient_key, 2);
        } elseif (strpos($phone_for_transient_key, '0') !== 0 && strlen($phone_for_transient_key) === 10 && substr($phone_for_transient_key, 0, 1) === '9') {
            $phone_for_transient_key = '0' . $phone_for_transient_key;
        }

        set_transient('ready_admin_test_otp_' . $phone_for_transient_key, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message' => sprintf(__('پیامک آزمایشی با موفقیت ارسال شد. کد OTP (%1$d رقمی برای تست): %2$s. شناسه مرجع: %3$s', 'readysms'), $otp_length, $otp, esc_html($response_from_api['referenceID'])),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('خطای ناشناخته از سمت API راه پیام هنگام ارسال پیامک آزمایشی.', 'readysms');
        // ... (منطق استخراج پیام خطا از $response_from_api مشابه قبل) ...
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

    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    
    // نرمال‌سازی شماره موبایل برای کلید Transient
    $phone_for_transient_key = preg_replace('/[^0-9]/', '', $phone_input);
    if (strpos($phone_for_transient_key, '98') === 0 && strlen($phone_for_transient_key) > 10) {
        $phone_for_transient_key = '0' . substr($phone_for_transient_key, 2);
    } elseif (strpos($phone_for_transient_key, '0') !== 0 && strlen($phone_for_transient_key) === 10 && substr($phone_for_transient_key, 0, 1) === '9') {
        $phone_for_transient_key = '0' . $phone_for_transient_key;
    }
    // برای API، شماره بین‌المللی نیاز است اگر API تایید راه پیام را فراخوانی می‌کردیم.
    // اما چون تایید تست ادمین را هم با Transient انجام می‌دهیم، نیازی به API خارجی نیست.

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
    
    if (is_array($response_from_api)) { 
         wp_send_json_success([
            'message' => __('اطلاعات وضعیت پیامک از راه پیام دریافت شد.', 'readysms'),
            'response_data' => $response_from_api
        ]);
    } else {
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

    if (is_array($response_from_api) && isset($response_from_api['id']) && isset($response_from_api['name'])) {
         wp_send_json_success([
            'message' => sprintf(__('اطلاعات قالب "%s" (ID: %s) با موفقیت از راه پیام دریافت شد.', 'readysms'), esc_html($response_from_api['name']), esc_html($response_from_api['id'])),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('پاسخ دریافت شده برای اطلاعات قالب، معتبر یا کامل نیست.', 'readysms');
        // ... (منطق استخراج پیام خطا از $response_from_api مشابه قبل) ...
        error_log("ReadySMS Admin Get Template - Invalid or Incomplete API Response. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error($error_message);
    }
});


/**
 * AJAX handler for getting credit balance - Updated for POST and specific endpoint.
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
    
    // Endpoint: https://api.msgway.com/balance/get, Method: POST
    $response_from_api = ready_msgway_api_request('https://api.msgway.com/balance/get', $api_key, [], 'POST', false);

    if (is_wp_error($response_from_api)) {
        wp_send_json_error(sprintf(__('خطا در دریافت موجودی: %s', 'readysms'), $response_from_api->get_error_message()));
    }

    if (is_array($response_from_api) && 
        isset($response_from_api['status']) && $response_from_api['status'] === 'success' &&
        isset($response_from_api['data']) && is_array($response_from_api['data']) &&
        isset($response_from_api['data']['balance'])) {
        
        $balance = (float)$response_from_api['data']['balance'];
        $currency_name = __('ریال', 'readysms');

         wp_send_json_success([
            'message' => sprintf(__('موجودی شما: %s %s', 'readysms'), number_format_i18n($balance, 0), $currency_name),
            'response_data' => $response_from_api
        ]);
    } else {
        $error_message = __('پاسخ دریافت شده برای موجودی، ساختار مورد انتظار را ندارد یا حاوی خطا است.', 'readysms');
        
        // تلاش برای خواندن پیام خطای احتمالی از API
        if(is_array($response_from_api) && isset($response_from_api['error']) && is_array($response_from_api['error']) && !empty($response_from_api['error']['message'])) {
            $error_message = (string) $response_from_api['error']['message'];
        } elseif (is_array($response_from_api) && !empty($response_from_api['message'])) { 
            $error_message = is_array($response_from_api['message']) ? implode('; ', $response_from_api['message']) : $response_from_api['message'];
        }

        error_log("ReadySMS Admin Get Balance - Invalid or Incomplete API Response Structure. Expected 'status'=='success' and 'data.balance'. Response: " . wp_json_encode($response_from_api, JSON_UNESCAPED_UNICODE));
        wp_send_json_error($error_message);
    }
});

/**
 * AJAX handler for exporting users to CSV.
 */
add_action('wp_ajax_readysms_export_users', function () {
    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'readysms-admin-nonce')) {
        wp_die(__('عملیات غیرمجاز یا nonce نامعتبر.', 'readysms'), __('خطا', 'readysms'), ['response' => 403]);
    }
    if (!current_user_can('manage_options')) {
        wp_die(__('شما مجوز کافی برای انجام این کار ندارید.', 'readysms'), __('خطا', 'readysms'), ['response' => 403]);
    }

    $filename = "readysms_users_export_" . date("Y-m-d_H-i-s") . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    // Prevent caching
    header('Pragma: no-cache');
    header('Expires: 0');


    $output = fopen('php://output', 'w');
    if ($output === false) {
        wp_die(__('خطا در ایجاد فایل خروجی.', 'readysms'), __('خطا', 'readysms'), ['response' => 500]);
    }
    
    // Add UTF-8 BOM for Excel compatibility with Persian characters
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 

    // Add headers to CSV
    fputcsv($output, [
        __('ردیف', 'readysms'),
        __('نام کاربری', 'readysms'),
        __('ایمیل', 'readysms'),
        __('نام نمایشی', 'readysms'),
        __('شماره موبایل (از نام کاربری)', 'readysms'),
        __('تاریخ عضویت', 'readysms'),
        __('نقش کاربری', 'readysms')
    ]);

    $args = [
        'fields'  => ['ID', 'user_login', 'user_email', 'display_name', 'user_registered'],
        'orderby' => 'ID',
        'order'   => 'ASC',
        // 'number' => 10, // برای تست با تعداد کم
    ];
    $users = get_users($args);
    $row_number = 1;

    foreach ($users as $user_obj) {
        $user_data = get_userdata($user_obj->ID); // برای دریافت نقش‌ها
        $roles = !empty($user_data->roles) ? implode(', ', array_map('translate_user_role', $user_data->roles)) : '';

        $phone_from_username = '';
        if (preg_match('/^(09\d{9})$/', $user_obj->user_login)) {
            $phone_from_username = $user_obj->user_login;
        }
        // برای شماره موبایل از متا فیلد:
        // $phone_meta = get_user_meta($user_obj->ID, 'your_phone_meta_key', true);

        fputcsv($output, [
            $row_number++,
            $user_obj->user_login,
            $user_obj->user_email,
            $user_obj->display_name,
            $phone_from_username,
            get_date_from_gmt($user_obj->user_registered, 'Y-m-d H:i:s'), // تبدیل به تاریخ محلی
            $roles
        ]);
    }

    fclose($output);
    wp_die();
});

// Helper function for normalizing phone numbers, if not already in a shared file.
// It's better to have such helpers in a common include or as static methods if part of a class.
if (!function_exists('readysms_normalize_phone_number')) {
    function readysms_normalize_phone_number($phone_input) {
        $country_code_mode = get_option('ready_sms_country_code_mode', 'iran_only');
        $sanitized_phone = preg_replace('/[^0-9+]/', '', $phone_input);

        if ($country_code_mode === 'iran_only') {
            if (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) {
                return '+98' . substr($sanitized_phone, 1);
            } elseif (preg_match('/^\+989[0-9]{9}$/', $sanitized_phone)) {
                return $sanitized_phone;
            } elseif (preg_match('/^989[0-9]{9}$/', $sanitized_phone)) {
                return '+' . $sanitized_phone;
            }
            return false;
        } else { // all_countries mode
            if (strpos($sanitized_phone, '+') === 0 && strlen($sanitized_phone) > 7) {
                return $sanitized_phone;
            } elseif (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) {
                 return '+98' . substr($sanitized_phone, 1);
            } elseif (strlen($sanitized_phone) === 10 && substr($sanitized_phone, 0, 1) === '9') { // 912...
                 return '+98' . $sanitized_phone;
            }
            return false; 
        }
    }
}
?>
