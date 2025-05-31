<?php
// File: includes/sms-login.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال کد تایید (OTP) به شماره تلفن با استفاده از API SEND سامانه راه پیام
 */
function ready_sms_send_otp() {
    check_ajax_referer('readysms-nonce', 'nonce');

    if (!isset($_POST['phone_number'])) {
        wp_send_json_error(__('شماره تلفن وارد نشده است.', 'readysms'));
    }

    $phone = sanitize_text_field(wp_unslash($_POST['phone_number']));
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        wp_send_json_error(__('شماره تلفن نامعتبر است. باید با 09 شروع شده و 11 رقم باشد.', 'readysms'));
    }

    $international_phone = '+98' . substr($phone, 1);
    $otp = (string)wp_rand(100000, 999999);

    $api_key = get_option('ready_sms_api_key');
    $template_id = get_option('ready_sms_pattern_code');
    $line_number = get_option('ready_sms_number');
    $timer_duration = (int)get_option('ready_sms_timer_duration', 120);

    if (empty($api_key) || empty($template_id)) {
        wp_send_json_error(__('تنظیمات API ناقص است. لطفا کلید API و کد پترن را در پیشخوان تنظیم کنید.', 'readysms'));
    }

    $payload = [
        "mobile"     => $international_phone,
        "method"     => "sms",
        "templateID" => (int) $template_id,
        "params"     => [$otp]
    ];
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
        // لاگ کردن خطای WP_Error
        $wp_error_message = $response->get_error_message();
        error_log('Readysms Front (Send OTP WP_Error): ' . $wp_error_message . ' | Payload: ' . wp_json_encode($payload));
        wp_send_json_error(sprintf(__('ارسال پیامک با خطای وردپرس مواجه شد: %s', 'readysms'), $wp_error_message));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body_retrieved = wp_remote_retrieve_body($response); // دریافت پاسخ خام
    $body_decoded_as_array = json_decode($raw_body_retrieved, true); // تلاش برای دیکود کردن پاسخ به آرایه
    $body_decoded_as_object = json_decode($raw_body_retrieved); // تلاش برای دیکود کردن پاسخ به آبجکت (برای بررسی دقیق‌تر ساختار)


    // شرط موفقیت مورد انتظار: کد HTTP مناسب و وجود OTPReferenceId در پاسخ JSON
    $is_considered_success = ($http_code === 200 || $http_code === 201) &&
                             is_array($body_decoded_as_array) && // اطمینان از اینکه پاسخ JSON آرایه‌ای است
                             isset($body_decoded_as_array['OTPReferenceId']);

    if ($is_considered_success) {
        set_transient('readysms_otp_' . $phone, $otp, 5 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'message'        => __('کد تایید با موفقیت ارسال شد.', 'readysms'),
            'remaining_time' => $timer_duration,
            // 'debug_api_ref_id' => $body_decoded_as_array['OTPReferenceId'] // برای اطمینان از مقدار در سمت کلاینت (اختیاری)
        ]);
    } else {
        // اگر شرط موفقیت بالا برقرار نباشد، به این معنی است که مشکلی در پاسخ وجود دارد
        // (علیرغم اینکه ممکن است پیامک ارسال شده باشد)
        $error_message_to_send_to_user = __('خطای ناشناس در ارسال OTP از سمت راه پیام.', 'readysms'); // پیام خطای پیش‌فرض

        // تلاش برای یافتن پیام خطای دقیق‌تر از پاسخ API اگر پاسخ JSON و دارای پیام خطا باشد
        if (is_array($body_decoded_as_array)) {
            if (isset($body_decoded_as_array['message'])) {
                $error_message_to_send_to_user = is_array($body_decoded_as_array['message']) ? implode(', ', $body_decoded_as_array['message']) : $body_decoded_as_array['message'];
            } elseif (isset($body_decoded_as_array['Message'])) {
                 $error_message_to_send_to_user = is_array($body_decoded_as_array['Message']) ? implode(', ', $body_decoded_as_array['Message']) : $body_decoded_as_array['Message'];
            }
        } elseif ($http_code >= 400) { // اگر کد HTTP نشان‌دهنده خطای کلاینت یا سرور باشد
            $error_message_to_send_to_user = sprintf(__('خطای API راه پیام (کد: %s)', 'readysms'), $http_code);
        }
        // اگر $http_code برابر 200/201 باشد ولی OTPReferenceId موجود نباشد یا پاسخ JSON نباشد،
        // همان پیام خطای پیش‌فرض ("خطای ناشناس...") استفاده می‌شود.

        // لاگ کردن اطلاعات بسیار دقیق برای خطایابی
        // شامل دلیل عدم موفقیت از دید افزونه
        $reason_for_failure_flag = "";
        if (!($http_code === 200 || $http_code === 201)) {
            $reason_for_failure_flag .= " [HTTP_CODE_NOT_200_OR_201]";
        }
        if (!is_array($body_decoded_as_array)) {
            $reason_for_failure_flag .= " [RESPONSE_NOT_VALID_JSON_ARRAY]";
        } elseif (!isset($body_decoded_as_array['OTPReferenceId'])) {
            $reason_for_failure_flag .= " [OTPReferenceId_KEY_MISSING_IN_JSON_ARRAY]";
        }


        $log_message_parts = [
            'UserFacingError: ' . $error_message_to_send_to_user,
            'PluginFailureReasonFlag(s):' . (empty($reason_for_failure_flag) ? " [UNKNOWN_CONDITION_FAILED_DESPITE_CHECKS]" : $reason_for_failure_flag),
            'HTTP_Code: ' . $http_code,
            'Raw_Response_From_Msgway: ' . $raw_body_retrieved,
            'Attempted_Decoded_To_Array (json_encode for log): ' . wp_json_encode($body_decoded_as_array),
            'Attempted_Decoded_To_Object (json_encode for log): ' . wp_json_encode($body_decoded_as_object) // برای بررسی ساختار دقیق‌تر
        ];
        error_log('Readysms Front (Send OTP Issue Details): ' . implode(' | ', $log_message_parts));

        wp_send_json_error($error_message_to_send_to_user); // ارسال پیام خطا به کاربر
    }
    wp_die();
}
// بقیه کدهای فایل includes/sms-login.php (تابع ready_sms_verify_otp و add_action ها) باید دست نخورده باقی بمانند.
// فقط محتوای تابع ready_sms_send_otp را با کد بالا جایگزین کنید.

// اطمینان حاصل کنید که این خطوط در انتهای فایل شما وجود دارند:
// add_action('wp_ajax_ready_sms_send_otp', 'ready_sms_send_otp');
// add_action('wp_ajax_nopriv_ready_sms_send_otp', 'ready_sms_send_otp');
// (اینها باید از قبل در فایل شما باشند، فقط محتوای خود تابع ready_sms_send_otp را تغییر دهید)
?>
