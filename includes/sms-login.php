<?php
// File: includes/sms-login.php
// ... (کدهای دیگر فایل مانند include ها و تابع ready_sms_verify_otp باید باقی بمانند) ...

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    // 1. بررسی امنیتی Nonce
    check_ajax_referer('readysms-nonce', 'nonce');

    // 2. اعتبارسنجی شماره تلفن ورودی
    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
        return; // wp_send_json_error شامل wp_die() است.
    }
    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
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
    $otp = (string)wp_rand(100000, 999999);
    $international_phone = '+98' . substr($phone, 1);

    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp]
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
        'body'        => wp_json_encode($payload), // استفاده از wp_json_encode برای اطمینان از فرمت صحیح
        'timeout'     => 30, // ثانیه
    ];
    $response = wp_remote_post('https://api.msgway.com/send', $args);

    // 5. بررسی خطای اولیه در ارتباط (مثلاً خطای cURL وردپرس)
    if (is_wp_error($response)) {
        $wp_error_message = $response->get_error_message();
        // لاگ کردن خطای وردپرس برای بررسی بیشتر
        error_log("Readysms (Send OTP) - WP_Error during wp_remote_post: " . $wp_error_message . " - Payload: " . wp_json_encode($payload));
        wp_send_json_error(sprintf(__('ارسال پیامک با خطای سیستمی وردپرس مواجه شد: %s. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.', 'readysms'), $wp_error_message));
        return;
    }

    // 6. پردازش پاسخ دریافت شده از API راه پیام
    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_from_api = wp_remote_retrieve_body($response);
    $decoded_body_as_array = json_decode($raw_body_from_api, true); // تلاش برای تبدیل پاسخ به آرایه PHP

    // تعریف شرط موفقیت از دید افزونه: کد HTTP باید 200 یا 201 باشد، پاسخ باید آرایه JSON معتبر باشد و کلید OTPReferenceId موجود باشد.
    $is_plugin_considering_api_call_successful = ($http_code === 200 || $http_code === 201) &&
                                                 is_array($decoded_body_as_array) &&
                                                 isset($decoded_body_as_array['OTPReferenceId']);

    if ($is_plugin_considering_api_call_successful) {
        // عملیات موفقیت آمیز از دید افزونه
        set_transient('readysms_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message'        => __('کد تایید با موفقیت ارسال شد.', 'readysms'),
            'remaining_time' => $timer_duration,
        ]);
    } else {
        // عملیات از دید افزونه موفقیت آمیز نبوده (علیرغم اینکه پیامک ممکن است ارسال شده باشد)
        $user_facing_error_message = __('خطای ناشناس در ارسال OTP از سمت راه پیام. لطفاً بررسی کنید پیامک به دستتان رسیده است.', 'readysms'); // پیام پیش‌فرض برای کاربر

        // تلاش برای استخراج پیام خطای دقیق‌تر از پاسخ API (اگر پاسخ JSON و دارای پیام خطا باشد)
        if (is_array($decoded_body_as_array)) {
            if (!empty($decoded_body_as_array['message'])) {
                $user_facing_error_message = is_array($decoded_body_as_array['message']) ? implode('; ', $decoded_body_as_array['message']) : (string) $decoded_body_as_array['message'];
            } elseif (!empty($decoded_body_as_array['Message'])) { // برخی API ها از 'Message' استفاده می‌کنند
                $user_facing_error_message = is_array($decoded_body_as_array['Message']) ? implode('; ', $decoded_body_as_array['Message']) : (string) $decoded_body_as_array['Message'];
            }
        } elseif ($http_code >= 400) { // اگر پاسخ JSON نباشد ولی کد HTTP نشانگر خطا باشد
            $user_facing_error_message = sprintf(__('خطای API راه پیام (کد وضعیت: %s).', 'readysms'), $http_code);
        }

        // لاگ کردن اطلاعات بسیار دقیق برای خطایابی توسط شما
        $log_data_for_debugging = [
            'UserMessageSent' => $user_facing_error_message,
            'HTTPStatusCode' => $http_code,
            'RawResponseBody' => $raw_body_from_api, // پاسخ خام بسیار مهم است
            'DecodedBodyAsArray' => $decoded_body_as_array, // ممکن است null باشد اگر پاسخ JSON نباشد
            'PluginSuccessConditionMet' => $is_plugin_considering_api_call_successful // باید false باشد در این بلاک
        ];
        // استفاده از wp_json_encode برای لاگ کردن آرایه به صورت خوانا
        error_log("Readysms (Send OTP) - API Call Not Considered Successful By Plugin: " . wp_json_encode($log_data_for_debugging, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        wp_send_json_error($user_facing_error_message);
    }
    // wp_die(); // نیازی نیست، چون wp_send_json_success/error آن را فراخوانی می‌کنند.
}

// اطمینان حاصل کنید که بقیه کدهای فایل includes/sms-login.php، مانند تابع ready_sms_verify_otp و add_action ها،
// دست نخورده باقی مانده و فقط و فقط محتوای خود تابع ready_sms_send_otp با کد بالا جایگزین شده است.
?>
