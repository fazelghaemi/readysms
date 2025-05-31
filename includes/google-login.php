<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تولید لینک ورود با گوگل با امکان دریافت URL برای ریدایرکت
function ready_generate_google_login_url($redirect_after_login = '') {
    $client_id    = esc_attr(get_option('ready_google_client_id'));
    $redirect_uri = esc_url(home_url('/'));
    $scope        = urlencode('email profile');
    
    // ذخیره URL نهایی در session
    if (!empty($redirect_after_login)) {
        if (!session_id()) {
            session_start();
        }
        $_SESSION['ready_login_redirect'] = esc_url($redirect_after_login); // sanitize در زمان ذخیره
    }

    $state = wp_create_nonce('ready-google-login');

    $url = "https://accounts.google.com/o/oauth2/auth?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&scope={$scope}&state={$state}";

    return esc_url($url);
}

// پردازش بازگشت از گوگل
function ready_handle_google_login() {
    if (isset($_GET['code'], $_GET['state']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['state'])), 'ready-google-login')) {
        $code         = sanitize_text_field(wp_unslash($_GET['code']));
        $client_id    = esc_attr(get_option('ready_google_client_id'));
        $client_secret = esc_attr(get_option('ready_google_client_secret'));
        $redirect_uri = esc_url(home_url('/'));

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $access_token = sanitize_text_field($body['access_token']);

            $user_response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $access_token],
            ]);

            $user_info = json_decode(wp_remote_retrieve_body($user_response), true);

            if (is_wp_error($user_response)) {
                wp_die('خطا در اتصال به گوگل: ' . esc_html($user_response->get_error_message()));
            }

            if (empty($user_info) || isset($user_info['error'])) {
                wp_die(esc_html__('خطا در دریافت اطلاعات از گوگل.', 'readysms'));
            }

            if (isset($user_info['email'])) {
                $email = sanitize_email($user_info['email']);
                $name  = sanitize_text_field($user_info['name']);

                $user = get_user_by('email', $email);
                if (!$user) {
                    $role = class_exists('WooCommerce') ? 'customer' : 'subscriber';

                    $user_id = wp_create_user($email, wp_generate_password(), $email);
                    if (is_wp_error($user_id)) {
                        wp_die(esc_html__('خطا در ایجاد کاربر: ', 'readysms') . esc_html($user_id->get_error_message()));
                    }
                    wp_update_user([
                        'ID'           => $user_id,
                        'role'         => $role,
                        'display_name' => $name,
                    ]);
                    $user = get_user_by('id', $user_id);
                }

                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);

                // هدایت به URL مشخص شده در session
                if (!session_id()) {
                    session_start();
                }
                $session_redirect    = isset($_SESSION['ready_login_redirect']) ? sanitize_text_field($_SESSION['ready_login_redirect']) : '';
                $redirect_after_login = !empty($session_redirect) ? esc_url($session_redirect) : esc_url(home_url());

                unset($_SESSION['ready_login_redirect']); // پاک کردن session

                wp_redirect($redirect_after_login);
                exit;
            } else {
                wp_die(esc_html__('خطا در دریافت اطلاعات کاربر از گوگل.', 'readysms'));
            }
        } else {
            wp_die(esc_html__('خطا در دریافت توکن از گوگل.', 'readysms'));
        }
    }
}
add_action('init', 'ready_handle_google_login');