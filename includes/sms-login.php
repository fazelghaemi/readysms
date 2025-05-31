<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    // بررسی nonce برای امنیت
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error('شماره تلفن وارد نشده است.');
    }

    // دریافت شماره تلفن و پاکسازی آن
    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    // حذف تمامی کاراکترهای غیر عددی
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // در صورتی که شماره تلفن 11 رقمی نباشد (مثلاً 09123456789)، ارسال خطا
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.');
    }

    // در اینجا شماره تلفن را به فرمت بین‌المللی (+98) تبدیل می‌کنیم
    $international_phone = '+98' . substr($phone, 1);

    // تولید کد OTP تصادفی (6 رقم)
    $otp = (string)wp_rand(100000, 999999);

    // دریافت تنظیمات API از پایگاه داده
    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code'); // در اینجا از این فیلد به عنوان templateID استفاده می‌شود
    $line_number = get_option('ready_sms_number'); // شماره ارسال (Provider)
    $timer_duration = get_option('ready_sms_timer_duration', 120);


    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error('تنظیمات API ناقص است. لطفا کلید API و کد پترن را در پیشخوان تنظیم کنید.');
    }

    // آماده‌سازی پارامترهای ارسال پیامک طبق نمونه API SEND
    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp] // پارامترهای پترن، در اینجا فقط کد OTP
    ];
    // افزودن شماره ارسال اگر تنظیم شده باشد
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

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
        error_log('Readysms: WP Error on sending OTP: ' . $response->get_error_message());
        wp_send_json_error('ارسال پیامک با خطا مواجه شد. (WP Error)');
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // بررسی موفقیت آمیز بودن ارسال پیامک بر اساس پاسخ API
    // Msgway 'send' endpoint typically returns HTTP 200 or 201 on success,
    // and a body containing OTPReferenceId.
    if (($http_code === 200 || $http_code === 201) && isset($body['OTPReferenceId'])) {
        // ذخیره OTP به مدت مشخص (مثلاً 5 دقیقه) در transient (برای استفاده در تایید)
        set_transient('ready_sms_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        // Optionally, store OTPReferenceId if needed for status checks later, though not used in verify
        // set_transient('ready_sms_otpref_' . $phone, $body['OTPReferenceId'], 5 * MINUTE_IN_SECONDS);

        wp_send_json_success([
            'message' => 'کد تایید ارسال شد.', 
            'remaining_time' => (int)$timer_duration,
            // 'otp_reference_id' => $body['OTPReferenceId'] // Optionally send to client
        ]);
    } else {
        $error_message = 'خطای ناشناس در ارسال OTP.';
        if (isset($body['message'])) { // Msgway often uses 'message' for errors
            $error_message = is_array($body['message']) ? implode(', ', $body['message']) : $body['message'];
        } elseif (isset($body['Message'])) { // Some API responses might use 'Message'
             $error_message = is_array($body['Message']) ? implode(', ', $body['Message']) : $body['Message'];
        } else if ($http_code >= 400) {
            $error_message = 'خطای API راه پیام (کد: ' . $http_code . ')';
        }
        error_log('Readysms: Error sending OTP - ' . $error_message . ' | HTTP Code: ' . $http_code . ' | Response: ' . wp_json_encode($body));
        wp_send_json_error($error_message);
    }

    wp_die(); // این دستور برای پایان دادن به اجرای AJAX ضروری است
}
add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');

/**
 * بررسی کد تایید دریافت شده (OTP) با استفاده از API Verify سامانه راه پیام
 */
function ready_sms_verify_otp() {
    // بررسی nonce جهت امنیت
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'], $_POST['otp_code'], $_POST['redirect_link'])) {
        wp_send_json_error('اطلاعات مورد نیاز (شماره تلفن، کد تایید، لینک ریدایرکت) وارد نشده است.');
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error('شماره تلفن نامعتبر است.');
    }

    // تبدیل شماره تلفن به فرمت بین‌المللی
    $international_phone = '+98' . substr($phone, 1);
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $redirect_link = !empty($_POST['redirect_link']) ? esc_url_raw(wp_unslash($_POST['redirect_link'])) : home_url();

    // دریافت تنظیمات API
    $api_key = get_option('ready_sms_api_key');
    if (empty($api_key)) {
        wp_send_json_error('تنظیمات API پیامک یافت نشد.');
    }

    // آماده‌سازی پارامترهای ارسال برای API Verify
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

    // استفاده از endpoint API Verify
    $response = wp_remote_post('https://api.msgway.com/otp/verify', $args);

    if (is_wp_error($response)) {
        error_log('Readysms: WP Error on verifying OTP: ' . $response->get_error_message());
        wp_send_json_error('تایید OTP با خطا مواجه شد. (WP Error)');
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // بررسی موفقیت آمیز بودن تایید OTP بر اساس پاسخ دریافت شده
    // Msgway SimpleVerifyOTP.php example: status: 1 and message: "Verified" for success.
    if ($http_code === 200 && isset($body['status']) && $body['status'] == 1 && isset($body['message']) && $body['message'] == "Verified") {
        // حذف transient کد OTP
        delete_transient('ready_sms_otp_' . $phone);
        // delete_transient('ready_sms_otpref_' . $phone); // If you stored it

        // ایجاد یا دریافت کاربر بر اساس شماره تلفن، سپس ورود به سیستم
        $user = get_user_by('login', $phone); // Using phone number as login username
        if (!$user) {
            // If user does not exist, create one.
            // You might want to ensure the phone number isn't already tied to another email if using email for uniqueness.
            // Using phone as username, and a dummy email.
            $email = $phone . '@' . wp_parse_url(home_url(), PHP_URL_HOST); // More unique dummy email
            if (email_exists($email)) { // If dummy email exists, append random numbers
                $email = $phone . '_' . wp_rand(100,999) . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
            }

            $user_id = wp_create_user($phone, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error('خطا در ایجاد کاربر: ' . $user_id->get_error_message());
            }
            // Assign role
            $role = class_exists('WooCommerce') ? 'customer' : 'subscriber';
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }

        if ($user) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true); // true for 'remember me'
            do_action('wp_login', $user->user_login, $user); // Hook for other plugins
            wp_send_json_success(['redirect' => $redirect_link]);
        } else {
            wp_send_json_error('خطا در ورود کاربر پس از تایید.');
        }
        
    } else {
        $error_message = 'کد تایید نامعتبر است.';
        if (isset($body['message'])) {
            $error_message = is_array($body['message']) ? implode(', ', $body['message']) : $body['message'];
        } elseif (isset($body['Message'])) {
             $error_message = is_array($body['Message']) ? implode(', ', $body['Message']) : $body['Message'];
        } else if ($http_code >= 400) {
             $error_message = 'خطای API راه پیام در تایید کد (کد: ' . $http_code . ')';
        }
        error_log('Readysms: Error verifying OTP - ' . $error_message . ' | HTTP Code: ' . $http_code . ' | Response: ' . wp_json_encode($body));
        wp_send_json_error($error_message);
    }
    wp_die(); // این دستور برای پایان دادن به اجرای AJAX ضروری است
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');
?>
