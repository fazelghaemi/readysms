<?php
// File: includes/google-login.php

if (!defined('ABSPATH')) {
    exit;
}

// اطمینان از شروع session برای ذخیره URL بازگشت
if (!function_exists('readysms_start_session_if_needed_for_google')) { // نام تابع تغییر کرد تا با تابع قبلی تداخل نداشته باشد
    function readysms_start_session_if_needed_for_google() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start([
                'read_and_close' => true, // بستن session پس از خواندن برای جلوگیری از قفل شدن
            ]);
        }
    }
}
add_action('init', 'readysms_start_session_if_needed_for_google', 1);


function ready_generate_google_login_url($redirect_after_login_from_shortcode = '') {
    $client_id    = get_option('ready_google_client_id');
    $redirect_uri  = esc_url(home_url('/index.php')); // باید با آنچه در Google Console ثبت شده یکسان باشد

    if (empty($client_id)) {
        return '#google_not_configured'; 
    }
    
    readysms_start_session_if_needed_for_google(); // اطمینان از شروع session
    
    // برای نوشتن در session، ممکن است نیاز به باز کردن مجدد آن باشد اگر read_and_close استفاده شده
    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
        if (session_id() !== '' && !headers_sent()) { // بررسی اضافه برای اطمینان
            session_write_close(); 
            session_start();
        }
    } elseif (session_status() === PHP_SESSION_NONE && !headers_sent()) { // اگر اصلا شروع نشده بود
        session_start();
    }


    // تعیین لینک بازگشت نهایی با اولویت: پارامتر شورت‌کد > تنظیمات ادمین > صفحه اصلی
    $final_redirect_url = home_url('/');
    $admin_redirect_after_login = get_option('ready_redirect_after_login');

    if (!empty($redirect_after_login_from_shortcode) && esc_url_raw($redirect_after_login_from_shortcode) !== home_url() && esc_url_raw($redirect_after_login_from_shortcode) !== home_url('/')) {
        $final_redirect_url = esc_url_raw($redirect_after_login_from_shortcode);
    } elseif (!empty($admin_redirect_after_login)) {
        $final_redirect_url = esc_url_raw($admin_redirect_after_login);
    }
    
    // ذخیره لینک بازگشت نهایی (پس از اعمال اولویت‌ها) در session
    $_SESSION['readysms_google_final_redirect_url'] = $final_redirect_url;


    $scope = urlencode('email profile openid'); // openid برای سازگاری بیشتر
    $state = wp_create_nonce('readysms-google-login-state-' . (isset($_SESSION['session_token']) ? $_SESSION['session_token'] : '')); // state یکتا برای هر session
    $_SESSION['readysms_google_oauth_state'] = $state;

    $auth_url = "https://accounts.google.com/o/oauth2/v2/auth";
    $params = [
        'response_type' => 'code',
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'scope'         => $scope,
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    return esc_url($auth_url . '?' . http_build_query($params));
}

function ready_handle_google_login() {
    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        return;
    }

    readysms_start_session_if_needed_for_google();
    // برای خواندن از session، ممکن است نیاز به باز کردن مجدد آن باشد
    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
        if (session_id() !== '' && !headers_sent()) {
             // session_write_close(); // برای خواندن معمولا لازم نیست
             // session_start(); // اگر بسته شده بود
        }
    } elseif (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }


    $session_state = isset($_SESSION['readysms_google_oauth_state']) ? $_SESSION['readysms_google_oauth_state'] : null;
    $request_state = sanitize_text_field(wp_unslash($_GET['state']));

    if (empty($session_state) || !hash_equals($session_state, $request_state)) {
        wp_die(esc_html__('خطای وضعیت (state) نامعتبر. احتمال حمله CSRF یا انقضای جلسه.', 'readysms'), esc_html__('خطای ورود با گوگل', 'readysms'), ['response' => 403]);
    }
    unset($_SESSION['readysms_google_oauth_state']);


    $code          = sanitize_text_field(wp_unslash($_GET['code']));
    $client_id     = get_option('ready_google_client_id');
    $client_secret = get_option('ready_google_client_secret');
    $redirect_uri  = esc_url(home_url('/index.php'));

    if (empty($client_id) || empty($client_secret)) {
        wp_die(esc_html__('شناسه‌های API گوگل در تنظیمات افزونه پیکربندی نشده‌اند.', 'readysms'), esc_html__('خطای ورود با گوگل', 'readysms'));
    }

    $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'method'  => 'POST', 'timeout' => 45,
        'body'    => [
            'code' => $code, 'client_id' => $client_id, 'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri, 'grant_type' => 'authorization_code',
        ],
    ]);

    if (is_wp_error($token_response)) {
        wp_die(esc_html__('خطا در ارتباط با گوگل برای دریافت توکن دسترسی: ', 'readysms') . esc_html($token_response->get_error_message()), esc_html__('خطای ورود با گوگل', 'readysms'));
    }

    $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
    if (!isset($token_body['access_token'])) {
        $error_desc = isset($token_body['error_description']) ? esc_html($token_body['error_description']) : (isset($token_body['error']) ? esc_html($token_body['error']) : __('خطای ناشناخته.', 'readysms'));
        wp_die(esc_html__('خطا در دریافت توکن دسترسی از گوگل: ', 'readysms') . $error_desc, esc_html__('خطای ورود با گوگل', 'readysms'));
    }
    $access_token = sanitize_text_field($token_body['access_token']);

    $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
        'headers' => ['Authorization' => 'Bearer ' . $access_token], 'timeout' => 45,
    ]);

    if (is_wp_error($user_info_response)) {
        wp_die(esc_html__('خطا در دریافت اطلاعات کاربر از گوگل: ', 'readysms') . esc_html($user_info_response->get_error_message()), esc_html__('خطای ورود با گوگل', 'readysms'));
    }

    $user_info = json_decode(wp_remote_retrieve_body($user_info_response), true);
    if (empty($user_info) || !isset($user_info['email']) || !isset($user_info['sub'])) {
        wp_die(esc_html__('اطلاعات کاربر دریافت شده از گوگل معتبر یا کامل نیست.', 'readysms'), esc_html__('خطای ورود با گوگل', 'readysms'));
    }

    $email = sanitize_email($user_info['email']);
    $google_user_id = sanitize_text_field($user_info['sub']);

    if (!isset($user_info['email_verified']) || $user_info['email_verified'] !== true) {
        wp_die(esc_html__('ایمیل گوگل شما تایید نشده است. لطفاً ابتدا ایمیل خود را در گوگل تایید کنید.', 'readysms'), esc_html__('خطای ورود با گوگل', 'readysms'));
    }

    $user = get_user_by('email', $email);
    $is_new_user = false;

    if (!$user) {
        $is_new_user = true;
        $username = 'google_' . $google_user_id;
        if (username_exists($username)) {
            $username = $username . '_' . wp_rand(100, 999);
        }
        
        $first_name = isset($user_info['given_name']) ? sanitize_text_field($user_info['given_name']) : '';
        $last_name = isset($user_info['family_name']) ? sanitize_text_field($user_info['family_name']) : '';
        $display_name = !empty(trim($first_name . $last_name)) ? trim($first_name . ' ' . $last_name) : (isset($user_info['name']) ? sanitize_text_field($user_info['name']) : '');
        if (empty($display_name)) $display_name = explode('@', $email)[0];


        $user_data = [
            'user_login'   => $username, 'user_email'   => $email,
            'user_pass'    => wp_generate_password(16, true, true),
            'display_name' => $display_name, 'first_name'   => $first_name, 'last_name'    => $last_name,
            'role'         => class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber'),
        ];
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            wp_die(esc_html__('خطا در ایجاد کاربر جدید از طریق گوگل: ', 'readysms') . esc_html($user_id->get_error_message()), esc_html__('خطای ورود با گوگل', 'readysms'));
        }
        update_user_meta($user_id, '_readysms_google_id', $google_user_id);
        $user = get_user_by('id', $user_id);
    } else {
        $user_id = $user->ID;
        if (!get_user_meta($user_id, '_readysms_google_id', true)) {
             update_user_meta($user_id, '_readysms_google_id', $google_user_id);
        }
    }

    if ($user && !is_wp_error($user)) {
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        // --- START: Redirect Logic ---
        $final_redirect_url_for_user = home_url('/'); // پیش‌فرض نهایی

        // خواندن لینک بازگشت از session (که با اولویت‌بندی صحیح در ready_generate_google_login_url ذخیره شده)
        $session_redirect_url = isset($_SESSION['readysms_google_final_redirect_url']) ? $_SESSION['readysms_google_final_redirect_url'] : null;
        
        // خواندن تنظیمات تغییر مسیر از دیتابیس
        $admin_redirect_after_login = get_option('ready_redirect_after_login');
        $admin_redirect_after_register = get_option('ready_redirect_after_register');

        if (!empty($session_redirect_url)) { // اگر لینکی در session بود (که از شورت‌کد یا تنظیمات ادمین آمده)
            $final_redirect_url_for_user = esc_url($session_redirect_url);
        }
        // اگر session خالی بود، و کاربر جدید است و تنظیم ادمین برای ثبت‌نام وجود دارد
        elseif ($is_new_user && !empty($admin_redirect_after_register)) {
            $final_redirect_url_for_user = esc_url($admin_redirect_after_register);
        } 
        // اگر session خالی بود، و کاربر قدیمی است و تنظیم ادمین برای ورود وجود دارد
        elseif (!$is_new_user && !empty($admin_redirect_after_login)) {
            $final_redirect_url_for_user = esc_url($admin_redirect_after_login);
        }
        // اگر session خالی بود و تنظیمات ادمین هم برای این حالت خاص (جدید/قدیمی) خالی بود، از تنظیم عمومی‌تر لاگین یا هوم استفاده کن
        elseif (!empty($admin_redirect_after_login)) {
             $final_redirect_url_for_user = esc_url($admin_redirect_after_login);
        }
        
        unset($_SESSION['readysms_google_final_redirect_url']); // پاک کردن session پس از استفاده
        // --- END: Redirect Logic ---

        wp_safe_redirect($final_redirect_url_for_user);
        exit;
    } else {
        wp_die(esc_html__('امکان ورود شما پس از احراز هویت با گوگل فراهم نشد.', 'readysms'), esc_html__('خطای ورود با گوگل', 'readysms'));
    }
}
add_action('init', 'ready_handle_google_login', 5); // اجرای زودتر برای اطمینان از پردازش قبل از هرگونه ریدایرکت دیگر

?>
