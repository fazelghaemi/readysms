<?php
// File: includes/sms-login.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to normalize phone numbers based on plugin settings.
 * Returns international format (e.g., +98...) or false if format is invalid for the selected mode.
 */
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
            if (strpos($sanitized_phone, '+') === 0 && strlen($sanitized_phone) > 7 && strlen($sanitized_phone) < 16) {
                return $sanitized_phone;
            } elseif (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) {
                 return '+98' . substr($sanitized_phone, 1);
            } elseif (strlen($sanitized_phone) === 10 && substr($sanitized_phone, 0, 1) === '9') {
                 return '+98' . $sanitized_phone;
            }
            return false; 
        }
    }
}

/**
 * Helper function to get a clean, standardized phone number for use as a WordPress username and transient key.
 */
if (!function_exists('readysms_get_storable_phone_format')) {
    function readysms_get_storable_phone_format($phone_input_original) {
        $country_code_mode = get_option('ready_sms_country_code_mode', 'iran_only');
        $sanitized_phone = preg_replace('/[^0-9+]/', '', $phone_input_original);

        // Normalize to 09... format for Iranian numbers for consistency
        if (preg_match('/^(\+|98)?(0)?9[0-9]{9}$/', $sanitized_phone, $matches)) {
            // Find the '9' that starts the main 9-digit part
            $main_number_part = '';
            if (isset($matches[3]) && $matches[3] === '9') $main_number_part = $matches[3]; // 9...
            elseif (isset($matches[2]) && $matches[2] === '0' && isset($matches[3]) && $matches[3] === '9') $main_number_part = $matches[2] . $matches[3]; // 09...
            
            // Reconstruct as 09... if it's an Iranian mobile number pattern
            if (!empty($main_number_part)) {
                 $national_part = substr($sanitized_phone, strpos($sanitized_phone, $main_number_part) + strlen($main_number_part) -10); // Get the last 10 digits starting with 9
                 if (strlen($national_part) === 10 && substr($national_part, 0, 1) === '9') {
                    return '0' . $national_part;
                 }
            }
        }
        
        // For 'all_countries' mode, if it's an international number starting with +, use it as is (or without + for username)
        if (strpos($sanitized_phone, '+') === 0) {
            return $sanitized_phone; // Or substr($sanitized_phone, 1) if you don't want '+' in username
        }
        
        // Fallback to a basic numeric sanitize if no specific format matches
        return preg_replace('/[^0-9]/', '', $phone_input_original);
    }
}


/**
 * ارسال کد تایید (OTP) به شماره تلفن
 */
function ready_sms_send_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
        return;
    }
    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));

    $international_phone_for_api = readysms_normalize_phone_number($phone_input);
    if (false === $international_phone_for_api) {
        wp_send_json_error(__('فرمت شماره موبایل وارد شده صحیح نیست یا با تنظیمات کد کشور مطابقت ندارد.', 'readysms'));
        return;
    }
    
    $phone_sanitized_for_transient = readysms_get_storable_phone_format($phone_input);

    // دریافت تنظیمات از دیتابیس
    $api_key = get_option('ready_sms_api_key');
    $sms_pattern_code = get_option('ready_sms_pattern_code'); // نام قبلی: $sms_template_id
    $send_method_option = get_option('ready_sms_send_method', 'sms');
    $line_number = get_option('ready_sms_number');
    $timer_duration = (int)get_option('ready_sms_resend_timer', 120);
    $otp_length = (int)get_option('ready_sms_otp_length', 6);

    // بررسی وجود کلید API
    if (empty($api_key)) {
        wp_send_json_error(__('کلید API پیامک در تنظیمات مشخص نشده است.', 'readysms'));
        return;
    }

    // بررسی وجود کد پترن فقط اگر روش ارسال پیامک است
    if ($send_method_option === 'sms' && empty($sms_pattern_code)) {
        wp_send_json_error(__('کد پترن پیامک در تنظیمات مشخص نشده است (برای ارسال پیامک لازم است).', 'readysms'));
        return;
    }
    
    // تعیین روش ارسال نهایی (اگر شماره غیر ایرانی است، اجبار به SMS)
    $final_send_method = $send_method_option;
    if (strpos($international_phone_for_api, '+98') !== 0 && $final_send_method === 'ivr') {
        $final_send_method = 'sms'; // اجبار به SMS برای شماره‌های غیر ایرانی
        // اگر با اجبار به SMS، کد پترن SMS هم تعریف نشده باشد، اینجا مجددا خطا می‌دهیم
        if (empty($sms_pattern_code)) {
            wp_send_json_error(__('ارسال تماس صوتی برای شماره‌های غیرایرانی پشتیبانی نمی‌شود و کد پترن پیامک نیز برای ارسال جایگزین، تنظیم نشده است.', 'readysms'));
            return;
        }
    }

    // تولید OTP
    if ($otp_length < 4 || $otp_length > 7) $otp_length = 6;
    $min_otp_val = pow(10, $otp_length - 1);
    $max_otp_val = pow(10, $otp_length) - 1;
    $otp_generated = (string)wp_rand($min_otp_val, $max_otp_val);
    
    // آماده‌سازی Payload برای API راه پیام
    $payload = [
        "mobile"     => $international_phone_for_api,
        "method"     => $final_send_method,
    ];

    if ($final_send_method === 'ivr') {
        $payload["templateID"] = 2; // templateID ثابت برای IVR
        $payload["code"] = $otp_generated; // کد OTP در پارامتر "code"
    } else { // sms
        $payload["templateID"] = (int) $sms_pattern_code;
        $payload["params"]     = [$otp_generated];
        if (!empty($line_number)) {
            $payload["lineNumber"] = $line_number;
        }
    }
    
    $args = [
        'headers'     => ['Content-Type' => 'application/json', 'apiKey' => $api_key],
        'body'        => wp_json_encode($payload),
        'timeout'     => 30,
    ];
    $response = wp_remote_post('https://api.msgway.com/send', $args);

    if (is_wp_error($response)) {
        $wp_error_message = $response->get_error_message();
        error_log("Readysms (Send OTP - Method: {$final_send_method}) - WP_Error: " . $wp_error_message . " - Payload: " . wp_json_encode($payload));
        wp_send_json_error(sprintf(__('ارسال کد با خطای سیستمی وردپرس مواجه شد: %s.', 'readysms'), $wp_error_message));
        return;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_from_api = wp_remote_retrieve_body($response);
    $decoded_body_as_array = json_decode($raw_body_from_api, true);

    $is_api_send_successful = ($http_code === 200 || $http_code === 201) &&
                              is_array($decoded_body_as_array) &&
                              isset($decoded_body_as_array['referenceID']) && // یا هر کلید مرجع دیگری که API شما برمی‌گرداند
                              isset($decoded_body_as_array['status']) &&
                              $decoded_body_as_array['status'] === 'success';

    if ($is_api_send_successful) {
        set_transient('readysms_otp_' . $phone_sanitized_for_transient, $otp_generated, 5 * MINUTE_IN_SECONDS);
        $success_message = ($final_send_method === 'ivr') ? __('کد تایید از طریق تماس صوتی به شماره شما ارسال شد.', 'readysms') : __('کد تایید به شماره شما پیامک شد.', 'readysms');
        wp_send_json_success([
            'message'        => $success_message,
            'remaining_time' => $timer_duration,
        ]);
    } else {
        $user_facing_error_message = ($final_send_method === 'ivr') ? __('خطا در برقراری تماس صوتی برای ارسال کد.', 'readysms') : __('خطای ناشناس در ارسال پیامک OTP از سمت راه پیام.', 'readysms');
        // ... (کد استخراج پیام خطا از $decoded_body_as_array مشابه قبل) ...
        if (is_array($decoded_body_as_array)) {
            if (!empty($decoded_body_as_array['message'])) { $user_facing_error_message = is_array($decoded_body_as_array['message']) ? implode('; ', $decoded_body_as_array['message']) : (string) $decoded_body_as_array['message']; }
            elseif (!empty($decoded_body_as_array['Message'])) { $user_facing_error_message = is_array($decoded_body_as_array['Message']) ? implode('; ', $decoded_body_as_array['Message']) : (string) $decoded_body_as_array['Message']; }
            elseif (isset($decoded_body_as_array['status']) && $decoded_body_as_array['status'] !== 'success' && !empty($decoded_body_as_array['error']) && is_array($decoded_body_as_array['error']) && !empty($decoded_body_as_array['error']['message'])) { $user_facing_error_message = (string) $decoded_body_as_array['error']['message']; }
            elseif (isset($decoded_body_as_array['status']) && $decoded_body_as_array['status'] !== 'success' && !empty($decoded_body_as_array['error']) && is_string($decoded_body_as_array['error'])) { $user_facing_error_message = (string) $decoded_body_as_array['error']; }
        } elseif ($http_code >= 400) { $user_facing_error_message = sprintf(__('خطای API راه پیام (کد وضعیت: %s).', 'readysms'), $http_code); }

        $log_data_for_debugging = [ /* ... (مشابه قبل) ... */ ];
        error_log("Readysms (Send OTP - Method: {$final_send_method}) - API Call Not Considered Successful: " . wp_json_encode($log_data_for_debugging, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        wp_send_json_error($user_facing_error_message);
    }
}
add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');

/**
 * بررسی کد تایید دریافت شده (OTP) با مقایسه کد ذخیره شده در Transient.
 */
function ready_sms_verify_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'], $_POST['otp_code'])) {
        wp_send_json_error(__('اطلاعات مورد نیاز (شماره تلفن، کد تایید) وارد نشده است.', 'readysms'));
        return;
    }

    $phone_input_from_js = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $otp_code_from_user = sanitize_text_field(wp_unslash($_POST['otp_code']));
    
    $phone_for_transient_and_login = readysms_get_storable_phone_format($phone_input_from_js);
    // اعتبارسنجی فرمت نهایی شماره برای نام کاربری/ترنزینت
    $is_iranian_format_for_username = preg_match('/^09[0-9]{9}$/', $phone_for_transient_and_login);
    $is_international_format_for_username = (get_option('ready_sms_country_code_mode', 'iran_only') === 'all_countries' && strpos($phone_for_transient_and_login, '+') === 0 && strlen($phone_for_transient_and_login) > 7);

    if (!$is_iranian_format_for_username && !$is_international_format_for_username) {
        error_log("ReadySMS (Verify OTP) - Invalid storable phone format for username/transient. Input: {$phone_input_from_js}, Storable Attempt: {$phone_for_transient_and_login}");
        wp_send_json_error(__('فرمت شماره موبایل شناسایی شده برای تایید نامعتبر است.', 'readysms'));
        return;
    }

    // تعیین لینک بازگشت
    $shortcode_redirect_param = isset($_POST['redirect_link']) ? esc_url_raw(wp_unslash($_POST['redirect_link'])) : '';
    $admin_redirect_after_login = get_option('ready_redirect_after_login');
    $admin_redirect_after_register = get_option('ready_redirect_after_register');
    
    $final_redirect_url = home_url('/');

    $transient_key = 'readysms_otp_' . $phone_for_transient_and_login;
    $stored_otp = get_transient($transient_key);

    if (false === $stored_otp) {
        wp_send_json_error(__('کد تایید منقضی شده یا نامعتبر است. لطفاً مجدداً درخواست کد کنید.', 'readysms'));
        return;
    }

    if ($stored_otp === $otp_code_from_user) {
        delete_transient($transient_key);

        $is_new_user = false;
        $user = get_user_by('login', $phone_for_transient_and_login);
        
        if (!$user) {
            $is_new_user = true;
            $email_host = wp_parse_url(home_url(), PHP_URL_HOST) ?: str_replace(['http://', 'https://', 'www.'], '', home_url()) ?: 'example.com';
            $base_email_user = preg_replace('/[^a-z0-9_]/i', '', $phone_for_transient_and_login);
            $email = $base_email_user . '@' . $email_host;
            
            $loop_count = 0;
            while (email_exists($email) && $loop_count < 10) {
                $email = $base_email_user . '_' . wp_rand(100,9999) . '@' . $email_host;
                $loop_count++;
            }
            if (email_exists($email)) {
                 wp_send_json_error(__('خطا در ایجاد ایمیل یکتا برای کاربر جدید.', 'readysms'));
                 return;
            }

            $user_id = wp_create_user($phone_for_transient_and_login, wp_generate_password(12, true, true), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(sprintf(__('خطا در ایجاد کاربر جدید: %s', 'readysms'), $user_id->get_error_message()));
                return;
            }
            $role = class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber');
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }

        // تعیین لینک بازگشت نهایی
        if (!empty($shortcode_redirect_param) && $shortcode_redirect_param !== home_url() && $shortcode_redirect_param !== home_url('/')) {
            $final_redirect_url = $shortcode_redirect_param;
        } elseif ($is_new_user && !empty($admin_redirect_after_register)) {
            $final_redirect_url = esc_url($admin_redirect_after_register);
        } elseif (!$is_new_user && !empty($admin_redirect_after_login)) {
            $final_redirect_url = esc_url($admin_redirect_after_login);
        } else {
             $final_redirect_url = !empty($admin_redirect_after_login) ? esc_url($admin_redirect_after_login) : home_url('/');
             if ($is_new_user && !empty($admin_redirect_after_register)) {
                $final_redirect_url = esc_url($admin_redirect_after_register);
             }
        }

        if ($user && !is_wp_error($user)) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
            wp_send_json_success(['redirect_url' => $final_redirect_url]);
        } else {
            wp_send_json_error(__('خطا در ورود یا یافتن اطلاعات کاربر پس از تایید OTP.', 'readysms'));
        }
    } else {
        error_log("Readysms (Verify OTP) - Invalid OTP attempt. User entered: [{$otp_code_from_user}], Stored OTP in transient: [{$stored_otp}], Phone for transient key: [{$phone_for_transient_and_login}]");
        wp_send_json_error(__('کد تایید وارد شده صحیح نیست.', 'readysms'));
    }
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');

?>
