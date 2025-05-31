<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    // بررسی nonce برای امنیت
    check_ajax_referer('readysms-nonce', 'nonce');

    if ( ! isset($_POST['phone_number']) ) {
        wp_send_json_error('شماره تلفن وارد نشده است.');
    }

    // دریافت شماره تلفن و پاکسازی آن
    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    // حذف تمامی کاراکترهای غیر عددی
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // در صورتی که شماره تلفن 11 رقمی نباشد (مثلاً 09123456789)، ارسال خطا
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error('شماره تلفن نامعتبر است.');
    }

    // در اینجا شماره تلفن را به فرمت بین‌المللی (+98) تبدیل می‌کنیم
    $international_phone = '+98' . substr($phone, 1);

    // تولید کد OTP تصادفی (6 رقم)
    $otp = wp_rand(100000, 999999);

    // دریافت تنظیمات API از پایگاه داده
    $api_key    = get_option('ready_sms_api_key');
    $templateID = get_option('ready_sms_pattern_code'); // در اینجا از این فیلد به عنوان templateID استفاده می‌شود

    if (empty($api_key) || empty($templateID)) {
        wp_send_json_error('تنظیمات API ناقص است.');
    }

    // آماده‌سازی پارامترهای ارسال پیامک طبق نمونه API SEND
    $params = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int) $templateID,
        // در اینجا پارامترهای دلخواه پیام (در این نمونه فقط کد OTP ارسال می‌شود)
        "params"     => [ (string)$otp ]
    ];

    $args = [
        'headers'     => [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ],
        'body'        => json_encode($params),
        'timeout'     => 30,
    ];

    // استفاده از endpoint API SEND
    $response = wp_remote_post('https://api.msgway.com/send', $args);

    if (is_wp_error($response)) {
        error_log('Readysms: خطای ارسال پیامک: ' . $response->get_error_message());
        wp_send_json_error('ارسال پیامک با خطا مواجه شد.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    // بررسی موفقیت آمیز بودن ارسال پیامک بر اساس پاسخ API (به فرض کلید "status" در پاسخ)
    if (isset($body['status']) && $body['status'] == "success") {
        // ذخیره OTP به مدت 5 دقیقه در transient (برای استفاده در تایید)
        set_transient('ready_sms_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        // در پاسخ زمان تایمر (مثلاً 120 ثانیه برای ارسال مجدد) برگردانده می‌شود؛ در اینجا به صورت ثابت قرار داده شده
        wp_send_json_success(['message' => 'کد تایید ارسال شد.', 'remaining_time' => 120]);
    } else {
        $error_message = isset($body['message']) ? $body['message'] : 'خطای ناشناس';
        error_log('Readysms: خطا در ارسال OTP - ' . $error_message);
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
    // بررسی nonce جهت امنیت
    check_ajax_referer('readysms-nonce', 'nonce');

    if ( ! isset($_POST['phone_number'], $_POST['otp_code'], $_POST['redirect_link']) ) {
        wp_send_json_error('اطلاعات مورد نیاز وارد نشده است.');
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
        wp_send_json_error('تنظیمات API یافت نشد.');
    }

    // آماده‌سازی پارامترهای ارسال برای API Verify
    $params = [
        "OTP"    => $otp_code,
        "mobile" => $international_phone,
    ];

    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ],
        'body'    => json_encode($params),
        'timeout' => 30,
    ];

    // استفاده از endpoint API Verify
    $response = wp_remote_post('https://api.msgway.com/otp/verify', $args);

    if (is_wp_error($response)) {
        error_log('Readysms: خطای تایید OTP: ' . $response->get_error_message());
        wp_send_json_error('تایید OTP با خطا مواجه شد.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    // بررسی موفقیت آمیز بودن تایید OTP بر اساس پاسخ دریافت شده
    if (isset($body['status']) && $body['status'] == "success") {
        // حذف transient کد OTP
        delete_transient('ready_sms_otp_' . $phone);

        // ایجاد یا دریافت کاربر بر اساس شماره تلفن، سپس ورود به سیستم (می‌توانید منطق دلخواه خود را اعمال کنید)
        $user = get_user_by('login', $phone);
        if (!$user) {
            $email = $phone . '@example.com';
            $user_id = wp_create_user($phone, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error('خطا در ایجاد کاربر: ' . $user_id->get_error_message());
            }
            $role = class_exists('WooCommerce') ? 'customer' : 'subscriber';
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        wp_send_json_success($redirect_link);
    } else {
        $error_message = isset($body['message']) ? $body['message'] : 'کد تایید نامعتبر است.';
        wp_send_json_error($error_message);
    }
    wp_die();
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');
?>