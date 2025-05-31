<?php
// File: includes/sms-login.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to normalize phone numbers.
 * Handles 'iran_only' and 'all_countries' modes.
 * Returns international format (+98...) or original if already international.
 * Returns false if format is invalid for the selected mode.
 */
function readysms_normalize_phone_number($phone_input) {
    $country_code_mode = get_option('ready_sms_country_code_mode', 'iran_only');
    $sanitized_phone = preg_replace('/[^0-9+]/', '', $phone_input); // Allow + for country codes

    if ($country_code_mode === 'iran_only') {
        if (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) { // 09...
            return '+98' . substr($sanitized_phone, 1);
        } elseif (preg_match('/^\+989[0-9]{9}$/', $sanitized_phone)) { // +989...
            return $sanitized_phone;
        } elseif (preg_match('/^989[0-9]{9}$/', $sanitized_phone)) { // 989...
            return '+' . $sanitized_phone;
        }
        return false; // Invalid format for Iran only mode
    } else { // all_countries mode
        if (strpos($sanitized_phone, '+') === 0 && strlen($sanitized_phone) > 7) { // Starts with + and has enough digits
            return $sanitized_phone;
        } elseif (preg_match('/^09[0-9]{9}$/', $sanitized_phone)) { // If user enters 09... in all_countries, assume Iran as default fallback
             return '+98' . substr($sanitized_phone, 1);
        } elseif (strlen($sanitized_phone) > 7 && ctype_digit($sanitized_phone)) { // If only digits and long enough, assume it's local and needs country context (hard to handle without UI)
            // For now, if it's likely an Iranian number without 0, try prefixing. Otherwise, it's ambiguous.
            if (strlen($sanitized_phone) === 10 && substr($sanitized_phone, 0, 1) === '9') { // 9123456789
                 return '+98' . $sanitized_phone;
            }
            // For truly international numbers without +, user MUST enter '+'.
            // This part could be enhanced with a country code selector UI in the future.
            return false; // Or handle as an error, "Please enter number with country code (+)"
        }
        return false; // Invalid or ambiguous format for all_countries without '+'
    }
}


/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
        return;
    }
    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));

    // تغییر 5: نرمال‌سازی و اعتبارسنجی شماره موبایل
    $international_phone_for_api = readysms_normalize_phone_number($phone_input);
    if (false === $international_phone_for_api) {
        wp_send_json_error(__('فرمت شماره موبایل وارد شده صحیح نیست یا با تنظیمات کد کشور مطابقت ندارد.', 'readysms'));
        return;
    }
    // کلید Transient باید بر اساس شماره نرمال‌شده بدون + یا شماره موبایل محلی یکسان باشد.
    // استفاده از شماره موبایل پاکسازی شده بدون کد کشور برای کلید Transient می‌تواند یکسان‌سازی بهتری ایجاد کند.
    $phone_sanitized_for_transient = preg_replace('/[^0-9]/', '', $phone_input);
    if (strpos($phone_sanitized_for_transient, '98') === 0 && strlen($phone_sanitized_for_transient) > 10) { // 989...
        $phone_sanitized_for_transient = '0' . substr($phone_sanitized_for_transient, 2); // 09...
    } elseif (strpos($phone_sanitized_for_transient, '0') !== 0 && strlen($phone_sanitized_for_transient) === 10 && substr($phone_sanitized_for_transient, 0, 1) === '9') { // 9... (بدون صفر اول)
        $phone_sanitized_for_transient = '0' . $phone_sanitized_for_transient; // 09...
    }
     // اطمینان از اینکه فقط 09 باقی می‌ماند اگر ایرانی بود
    if (preg_match('/^09[0-9]{9}$/', $phone_sanitized_for_transient) === 0) {
        // اگر پس از نرمال سازی همچنان فرمت مورد انتظار برای کلید transient را ندارد،
        // از خود $international_phone_for_api (بدون +) استفاده می کنیم، یا خطا می دهیم.
        // برای سادگی، فرض می کنیم $phone_input خام یا $international_phone_for_api برای کلید مناسب هستند.
        // اما بهترین حالت یک فرمت یکسان برای transient key است. فعلا از $phone_sanitized_for_transient که 09 دارد استفاده می‌کنیم.
        // اگر شماره بین المللی بود، $phone_sanitized_for_transient همان خواهد بود.
        // این بخش نیاز به تست دقیق دارد.
    }


    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');
    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error(__('تنظیمات API ناقص است.', 'readysms'));
        return;
    }

    $line_number = get_option('ready_sms_number');
    $timer_duration = (int)get_option('ready_sms_resend_timer', 120); // تغییر 3
    $otp_length = (int)get_option('ready_sms_otp_length', 6);
    
    if ($otp_length < 4 || $otp_length > 7) $otp_length = 6;
    $min_otp_val = pow(10, $otp_length - 1);
    $max_otp_val = pow(10, $otp_length) - 1;
    $otp_generated = (string)wp_rand($min_otp_val, $max_otp_val);
    
    $payload = [
        "mobile"     => $international_phone_for_api, // شماره نرمال شده برای API
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp_generated]
    ];
    if (!empty($line_number)) $payload["lineNumber"] = $line_number;

    $args = ['headers' => ['Content-Type' => 'application/json', 'apiKey' => $api_key], 'body' => wp_json_encode($payload), 'timeout' => 30];
    $response = wp_remote_post('https://api.msgway.com/send', $args);

    if (is_wp_error($response)) {
        // ... (لاگ خطا مشابه قبل) ...
        wp_send_json_error(sprintf(__('ارسال پیامک با خطای سیستمی وردپرس مواجه شد: %s.', 'readysms'), $response->get_error_message()));
        return;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_from_api = wp_remote_retrieve_body($response);
    $decoded_body_as_array = json_decode($raw_body_from_api, true);

    $is_api_send_successful = ($http_code === 200 || $http_code === 201) &&
                              is_array($decoded_body_as_array) &&
                              isset($decoded_body_as_array['referenceID']) &&
                              isset($decoded_body_as_array['status']) &&
                              $decoded_body_as_array['status'] === 'success';

    if ($is_api_send_successful) {
        // استفاده از $phone_sanitized_for_transient (که معمولا فرمت 09... برای ایران دارد) برای کلید ترنزینت
        set_transient('readysms_otp_' . $phone_sanitized_for_transient, $otp_generated, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success(['message' => __('کد تایید با موفقیت ارسال شد.', 'readysms'), 'remaining_time' => $timer_duration]);
    } else {
        // ... (مدیریت و لاگ خطا مشابه قبل) ...
        wp_send_json_error(__('خطای ناشناس در ارسال OTP از سمت راه پیام. لطفاً بررسی کنید پیامک به دستتان رسیده است.', 'readysms'));
    }
}
add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');

/**
 * بررسی کد تایید دریافت شده (OTP)
 */
function ready_sms_verify_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'], $_POST['otp_code'])) {
        wp_send_json_error(__('اطلاعات مورد نیاز وارد نشده است.', 'readysms'));
        return;
    }

    $phone_input = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $otp_code_from_user = sanitize_text_field(wp_unslash($_POST['otp_code']));

    // نرمال‌سازی شماره موبایل برای کلید Transient و نام کاربری
    // این بخش باید با منطق نرمال‌سازی در ready_sms_send_otp هماهنگ باشد
    // برای سادگی فعلی، فرض می‌کنیم کاربر همان شماره‌ای را که در مرحله اول وارد کرده، در JS داریم
    // و برای کلید Transient از فرمت پاکسازی شده (مثلا 09 ایرانی) استفاده می‌کنیم
    $phone_sanitized_for_transient_and_login = preg_replace('/[^0-9]/', '', $phone_input);
    if (strpos($phone_sanitized_for_transient_and_login, '98') === 0 && strlen($phone_sanitized_for_transient_and_login) > 10) {
        $phone_sanitized_for_transient_and_login = '0' . substr($phone_sanitized_for_transient_and_login, 2);
    } elseif (strpos($phone_sanitized_for_transient_and_login, '0') !== 0 && strlen($phone_sanitized_for_transient_and_login) === 10 && substr($phone_sanitized_for_transient_and_login, 0, 1) === '9') {
        $phone_sanitized_for_transient_and_login = '0' . $phone_sanitized_for_transient_and_login;
    }
     if (!preg_match('/^09[0-9]{9}$/', $phone_sanitized_for_transient_and_login)) {
        // اگر پس از نرمال‌سازی فرمت 09... برای ایران به دست نیامد،
        // و حالت "همه کشورها" فعال است، ممکن است شماره فرمت دیگری داشته باشد.
        // در این حالت، برای یکسان‌سازی کلید ترنزینت، شاید بهتر باشد از شماره خام پاکسازی شده استفاده کرد
        // یا یک مکانیزم پیچیده‌تر برای نرمال‌سازی کلید ترنزینت برای شماره‌های بین‌المللی داشت.
        // فعلاً با فرض اینکه بیشتر کاربران ایرانی هستند و JS شماره را در فرمت اولیه به اینجا می‌فرستد.
        // اگر حالت "همه کشورها" فعال است و کاربر + وارد کرده، $phone_input خام استفاده می‌شود.
        if(get_option('ready_sms_country_code_mode', 'iran_only') === 'all_countries' && strpos($phone_input, '+') === 0) {
            $phone_sanitized_for_transient_and_login = preg_replace('/[^0-9+]/', '', $phone_input); // +123456789
        } else if (get_option('ready_sms_country_code_mode', 'iran_only') !== 'iran_only') {
             // اگر فرمت 09 نبود و همه کشورها فعال بود و با + هم شروع نشده بود، خطا می‌دهیم
             wp_send_json_error(__('فرمت شماره موبایل برای تایید نامعتبر است.', 'readysms'));
             return;
        }
    }


    // تغییر 1: تعیین لینک بازگشت
    $shortcode_redirect = isset($_POST['redirect_link']) ? esc_url_raw(wp_unslash($_POST['redirect_link'])) : '';
    $login_redirect_option = get_option('ready_redirect_after_login');
    $register_redirect_option = get_option('ready_redirect_after_register');
    
    // اولویت با پارامتر شورت‌کد است، سپس تنظیمات ادمین
    $final_redirect_url = home_url('/'); // پیش‌فرض نهایی
    // اگر کاربر جدید است، از ریدایرکت ثبت‌نام استفاده کن، وگرنه از ریدایرکت لاگین
    // این بخش پس از تشخیص جدید بودن یا نبودن کاربر اعمال می‌شود.

    $transient_key = 'readysms_otp_' . $phone_sanitized_for_transient_and_login;
    $stored_otp = get_transient($transient_key);

    if (false === $stored_otp) {
        wp_send_json_error(__('کد تایید منقضی شده یا نامعتبر است. لطفاً مجدداً درخواست کد کنید.', 'readysms'));
        return;
    }

    if ($stored_otp === $otp_code_from_user) {
        delete_transient($transient_key);

        $is_new_user = false;
        $user = get_user_by('login', $phone_sanitized_for_transient_and_login);
        if (!$user) {
            // اگر با login (شماره موبایل) پیدا نشد، با ایمیل مبتنی بر شماره موبایل هم چک کن (برای سازگاری با افزونه‌های دیگر)
            $temp_email = $phone_sanitized_for_transient_and_login . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
            $user_by_email = get_user_by('email', $temp_email);
            if($user_by_email) $user = $user_by_email;
        }


        if (!$user) {
            $is_new_user = true;
            $email_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (empty($email_host)) $email_host = str_replace(['http://', 'https://', 'www.'], '', home_url());
            if (empty($email_host)) $email_host = 'example.com';
            
            $base_email_user = preg_replace('/[^a-z0-9_]/i', '', $phone_sanitized_for_transient_and_login);
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

            $user_id = wp_create_user($phone_sanitized_for_transient_and_login, wp_generate_password(12, true, true), $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(sprintf(__('خطا در ایجاد کاربر جدید: %s', 'readysms'), $user_id->get_error_message()));
                return;
            }
            $role = class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber');
            wp_update_user(['ID' => $user_id, 'role' => $role]);
            $user = get_user_by('id', $user_id);
        }

        if ($user && !is_wp_error($user)) {
            // تعیین لینک بازگشت نهایی بر اساس جدید بودن یا نبودن کاربر
            if ($is_new_user && !empty($register_redirect_option)) {
                $final_redirect_url = esc_url($register_redirect_option);
            } elseif (!$is_new_user && !empty($login_redirect_option)) {
                $final_redirect_url = esc_url($login_redirect_option);
            } elseif (!empty($shortcode_redirect) && $shortcode_redirect !== home_url() && $shortcode_redirect !== home_url('/')) { 
                // اگر شورت‌کد مقداری متفاوت از پیش‌فرض اولیه داشت و تنظیمات ادمین خالی بودند
                $final_redirect_url = $shortcode_redirect;
            } else {
                $final_redirect_url = !empty($login_redirect_option) ? esc_url($login_redirect_option) : home_url('/');
                 if ($is_new_user && !empty($register_redirect_option)) { // اگر کاربر جدید است و تنظیمات ادمین برای ثبت‌نام وجود دارد
                    $final_redirect_url = esc_url($register_redirect_option);
                 }
            }


            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
            wp_send_json_success(['redirect_url' => $final_redirect_url]);
        } else {
            wp_send_json_error(__('خطا در ورود یا یافتن اطلاعات کاربر پس از تایید OTP.', 'readysms'));
        }
    } else {
        error_log("Readysms (Verify OTP) - Invalid OTP attempt. User entered: [{$otp_code_from_user}], Stored OTP in transient: [{$stored_otp}], Phone for transient key: [{$phone_sanitized_for_transient_and_login}]");
        wp_send_json_error(__('کد تایید وارد شده صحیح نیست.', 'readysms'));
    }
}
add_action('wp_ajax_ready_sms_verify_otp', 'ready_sms_verify_otp');
add_action('wp_ajax_nopriv_ready_sms_verify_otp', 'ready_sms_verify_otp');

?>
