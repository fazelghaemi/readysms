<?php
// File: includes/sms-login.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    // 1. بررسی امنیتی Nonce
    check_ajax_referer('readysms-nonce', 'nonce');

    // 2. اعتبارسنجی شماره تلفن ورودی
    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
        return;
    }
    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone_sanitized_for_transient = preg_replace('/[^0-9]/', '', $phone); // برای کلید transient
    if (!preg_match('/^09[0-9]{9}$/', $phone_sanitized_for_transient)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.', 'readysms'));
        return;
    }

    // 3. دریافت تنظیمات و آماده‌سازی پارامترها
    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');

    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error(__('تنظیمات API ناقص است. لطفا کلید API و کد پترن را در پیشخوان تنظیم کنید.', 'readysms'));
        return;
    }

    $line_number = get_option('ready_sms_number');
    $timer_duration = (int)get_option('ready_sms_timer_duration', 120);
    
    $otp_length = (int)get_option('ready_sms_otp_length', 6);
    if ($otp_length < 4 || $otp_length > 7) {
        $otp_length = 6;
    }
    $min_otp_val = pow(10, $otp_length - 1);
    $max_otp_val = pow(10, $otp_length) - 1;
    $otp_generated = (string)wp_rand($min_otp_val, $max_otp_val); // کد OTP تولید شده توسط افزونه
    
    $international_phone_for_api = '+98' . substr($phone_sanitized_for_transient, 1);

    $payload = [
        "mobile"     => $international_phone_for_api,
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp_generated] // ارسال کد OTP تولیدی خودمان به عنوان پارامتر
    ];
    if (!empty($line_number)) {
        $payload["lineNumber"] = $line_number;
    }

    // 4. ارسال درخواست به API راه پیام
    $args = [
        'headers'     => [
            'Content-Type' => 'application/json',
            'apiKey'       => $api_key,
        ],
        'body'        => wp_json_encode($payload),
        'timeout'     => 30,
    ];
    $response = wp_remote_post('https://api.msgway.com/send', $args);

    // 5. بررسی خطای اولیه در ارتباط
    if (is_wp_error($response)) {
        $wp_error_message = $response->get_error_message();
        error_log("Readysms (Send OTP) - WP_Error during wp_remote_post: " . $wp_error_message . " - Payload: " . wp_json_encode($payload));
        wp_send_json_error(sprintf(__('ارسال پیامک با خطای سیستمی وردپرس مواجه شد: %s. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.', 'readysms'), $wp_error_message));
        return;
    }

    // 6. پردازش پاسخ دریافت شده از API راه پیام
    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_from_api = wp_remote_retrieve_body($response);
    $decoded_body_as_array = json_decode($raw_body_from_api, true);

    $is_api_send_successful = ($http_code === 200 || $http_code === 201) &&
                              is_array($decoded_body_as_array) &&
                              isset($decoded_body_as_array['referenceID']) && // بر اساس لاگ شما این کلید وجود دارد
                              isset($decoded_body_as_array['status']) &&
                              $decoded_body_as_array['status'] === 'success';

    if ($is_api_send_successful) {
        // ذخیره کد OTP تولید شده در Transient برای تایید بعدی
        set_transient('readysms_otp_' . $phone_sanitized_for_transient, $otp_generated, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message'        => __('کد تایید با موفقیت ارسال شد.', 'readysms'),
            'remaining_time' => $timer_duration,
        ]);
    } else {
        $user_facing_error_message = __('خطای ناشناس در ارسال OTP از سمت راه پیام. لطفاً بررسی کنید پیامک به دستتان رسیده است.', 'readysms');

        if (is_array($decoded_body_as_array)) {
            if (!empty($decoded_body_as_array['message'])) {
                $user_facing_error_message = is_array($decoded_body_as_array['message']) ? implode('; ', $decoded_body_as_array['message']) : (string) $decoded_body_as_array['message'];
            } elseif (!empty($decoded_body_as_array['Message'])) {
                $user_facing_error_message = is_array($decoded_body_as_array['Message']) ? implode('; ', $decoded_body_as_array['Message']) : (string) $decoded_body_as_array['Message'];
            } elseif (isset($decoded_body_as_array['status']) && $decoded_body_as_array['status'] !== 'success' && !empty($decoded_body_as_array['error'])) {
                $user_facing_error_message = (string) $decoded_body_as_array['error'];
            }
        } elseif ($http_code >= 400) {
            $user_facing_error_message = sprintf(__('خطای API راه پیام (کد وضعیت: %s).', 'readysms'), $http_code);
        }

        $log_data_for_debugging = [
            'UserMessageSent' => $user_facing_error_message,
            'HTTPStatusCode' => $http_code,
            'RawResponseBody' => $raw_body_from_api,
            'DecodedBodyAsArray' => $decoded_body_as_array,
            'APISendSuccessfulFlag' => $is_api_send_successful // باید false باشد در این بلاک
        ];
        error_log("Readysms (Send OTP) - API Call Not Considered Successful By Plugin: " . wp_json_encode($log_data_for_debugging, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        wp_send_json_error($user_facing_error_message);
    }
}
add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');

/**
 * بررسی کد تایید دریافت شده (OTP) با مقایسه کد ذخیره شده در Transient.
 */
function ready_sms_verify_otp() {
    // 1. بررسی امنیتی Nonce
    check_ajax_referer('readysms-nonce', 'nonce');

    // 2. اعتبارسنجی ورودی‌ها
    if (!isset($_POST['phone_number'], $_POST['otp_code'])) {
        wp_send_json_error(__('اطلاعات مورد نیاز (شماره تلفن، کد تایید) وارد نشده است.', 'readysms'));
        return;
    }

    $phone_from_input = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone_sanitized = preg_replace('/[^0-9]/', '', $phone_from_input); // برای استفاده در نام transient و login کاربر
    
    if (!preg_match('/^09[0-9]{9}$/', $phone_sanitized)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است.', 'readysms'));
        return;
    }

    $otp_code_from_user = sanitize_text_field(wp_unslash($_POST['otp_code']));
    $redirect_link = isset($_POST['redirect_link']) ? esc_url_raw(wp_unslash($_POST['redirect_link'])) : home_url();

    // 3. بازیابی و مقایسه کد OTP از Transient
    $transient_key = 'readysms_otp_' . $phone_sanitized;
    $stored_otp = get_transient($transient_key);

    if (false === $stored_otp) {
        // کد در Transient وجود ندارد یا منقضی شده است
        wp_send_json_error(__('کد تایید منقضی شده یا نامعتبر است. لطفاً مجدداً درخواست کد کنید.', 'readysms'));
        return;
    }

    // مقایسه دقیق کد وارد شده توسط کاربر با کد ذخیره شده
    if ($stored_otp === $otp_code_from_user) {
        // کد تایید صحیح است
        delete_transient($transient_key); // پس از تایید موفق، Transient را پاک می‌کنیم

        // ادامه مراحل ورود یا ثبت نام کاربر
        $user = get_user_by('login', $phone_sanitized);
        if (!$user) {
            // ایجاد کاربر جدید
            $email_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (empty($email_host)) $email_host = str_replace(['http://', 'https://', 'www.'], '', home_url()); // تلاش برای گرفتن هاست
            if (empty($email_host)) $email_host = 'example.com'; // هاست پیش‌فرض نهایی
            
            $base_email_user = preg_replace('/[^a-z0-9_]/i', '', $phone_sanitized);
            $email = $base_email_user . '@' . $email_host;
            
            // اطمینان از عدم تکراری بودن ایمیل
            $loop_count = 0;
            while (email_exists($email) && $loop_count < 10) { // محدودیت برای جلوگیری از حلقه بی‌نهایت
                $email = $base_email_user . '_' . wp_rand(100,9999) . '@' . $email_host;
                $loop_count++;
            }
            if (email_exists($email)) { // اگر پس از 10 بار تلاش همچنان ایمیل تکراری بود
                 wp_send_json_error(__('خطا در ایجاد ایمیل یکتا برای کاربر جدید. لطفاً با پشتیبانی تماس بگیرید.', 'readysms'));
                 return;
            }


            $user_id = wp_create_user($phone_sanitized, wp_generate_password(12, true, true), $email); // استفاده از شماره موبایل به عنوان نام کاربری
            if (is_wp_error($user_id)) {
                wp_send_json_error(sprintf(__('خطا در ایجاد کاربر جدید: %s', 'readysms'), $user_id->get_error_message()));
                return;
            }
            $role = class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber');
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }

        if ($user && !is_wp_error($user)) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true); // true برای "مرا به خاطر بسپار"
            do_action('wp_login', $user->user_login, $user); // اجرای هوک ورود وردپرس
            wp_send_json_success(['redirect_url' => $redirect_link]);
        } else {
            wp_send_json_error(__('خطا در ورود یا یافتن اطلاعات کاربر پس از تایید OTP.', 'readysms'));
        }

    } else {
        // کد تایید وارد شده توسط کاربر با کد ذخیره شده مطابقت ندارد
        // لاگ کردن تلاش ناموفق (اختیاری اما مفید برای خطایابی)
        error_log("Readysms (Verify OTP) - Invalid OTP attempt. User entered: [{$otp_code_from_user}], Stored OTP in transient: [{$stored_otp}], Phone for transient key: [{$phone_sanitized}]");
        wp_send_json_error(__('کد تایید وارد شده صحیح نیست.', 'readysms')); // پیام خطای دقیق‌تر
    }
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');

?>
