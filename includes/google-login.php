<?php
// File: includes/google-login.php

if (!defined('ABSPATH')) {
    exit;
}

// Start session if not already started, for storing redirect URL
if (!function_exists('readysms_start_session_if_needed')) {
    function readysms_start_session_if_needed() {
        if (!session_id() && !headers_sent()) {
            session_start([
                'read_and_close' => true, // Close session early if only reading
            ]);
        }
    }
}
add_action('init', 'readysms_start_session_if_needed', 1);


function ready_generate_google_login_url($redirect_after_login = '') {
    $client_id    = esc_attr(get_option('ready_google_client_id'));
    // The redirect_uri for Google Console must be set to home_url('/') or wherever ready_handle_google_login is triggered.
    $redirect_uri = esc_url(home_url('/index.php')); // Google often needs a specific file like index.php
    $scope        = urlencode('email profile');
    
    if (empty($client_id)) {
        return '#google_not_configured'; // Or return an empty string or error message
    }
    
    readysms_start_session_if_needed(); // Ensure session is started before using $_SESSION
    // Re-open session for writing if it was closed by 'read_and_close'
    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
        session_write_close(); // Close read-only session
        session_start();       // Re-open for writing
    }


    if (!empty($redirect_after_login)) {
        $_SESSION['readysms_google_redirect_after_login'] = esc_url($redirect_after_login);
    } else {
        unset($_SESSION['readysms_google_redirect_after_login']); // Clear if no specific redirect
    }

    $state = wp_create_nonce('readysms-google-login-state');
    // Store state in session to verify on callback, as Google might not return it reliably in all setups.
    $_SESSION['readysms_google_oauth_state'] = $state;


    $auth_url = "https://accounts.google.com/o/oauth2/v2/auth";
    $params = [
        'response_type' => 'code',
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'scope'         => $scope,
        'state'         => $state,
        'access_type'   => 'online', // 'offline' for refresh token
        'prompt'        => 'select_account', // Optional: forces account selection
    ];
    return esc_url($auth_url . '?' . http_build_query($params));
}

function ready_handle_google_login() {
    // Check if this is a Google OAuth callback
    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        return; // Not a Google callback or missing parameters
    }

    readysms_start_session_if_needed();
    $session_state = isset($_SESSION['readysms_google_oauth_state']) ? $_SESSION['readysms_google_oauth_state'] : null;
    $request_state = sanitize_text_field(wp_unslash($_GET['state']));

    // Verify state to prevent CSRF
    if (empty($session_state) || !hash_equals($session_state, $request_state)) {
        // Consider logging this CSRF attempt
        wp_die(esc_html__('Invalid state parameter. CSRF attack detected?', 'readysms'), esc_html__('Google Login Error', 'readysms'), 403);
    }
    unset($_SESSION['readysms_google_oauth_state']); // State is used, clear it


    $code          = sanitize_text_field(wp_unslash($_GET['code']));
    $client_id     = get_option('ready_google_client_id');
    $client_secret = get_option('ready_google_client_secret');
    $redirect_uri  = esc_url(home_url('/index.php')); // Must match the one used in auth URL and Google Console

    if (empty($client_id) || empty($client_secret)) {
        wp_die(esc_html__('Google API credentials are not configured in plugin settings.', 'readysms'), esc_html__('Google Login Error', 'readysms'));
    }

    $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'method'  => 'POST',
        'timeout' => 45,
        'body'    => [
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ],
    ]);

    if (is_wp_error($token_response)) {
        wp_die(esc_html__('Error contacting Google for token: ', 'readysms') . esc_html($token_response->get_error_message()), esc_html__('Google Login Error', 'readysms'));
    }

    $token_body = json_decode(wp_remote_retrieve_body($token_response), true);

    if (!isset($token_body['access_token'])) {
        $error_desc = isset($token_body['error_description']) ? esc_html($token_body['error_description']) : esc_html__('Unknown error.', 'readysms');
        wp_die(esc_html__('Error retrieving access token from Google: ', 'readysms') . $error_desc, esc_html__('Google Login Error', 'readysms'));
    }
    $access_token = sanitize_text_field($token_body['access_token']);

    // Get user info from Google
    $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
        'headers' => ['Authorization' => 'Bearer ' . $access_token],
        'timeout' => 45,
    ]);

    if (is_wp_error($user_info_response)) {
        wp_die(esc_html__('Error fetching user information from Google: ', 'readysms') . esc_html($user_info_response->get_error_message()), esc_html__('Google Login Error', 'readysms'));
    }

    $user_info = json_decode(wp_remote_retrieve_body($user_info_response), true);

    if (empty($user_info) || !isset($user_info['email']) || !isset($user_info['sub'])) {
        wp_die(esc_html__('Could not retrieve valid user information from Google.', 'readysms'), esc_html__('Google Login Error', 'readysms'));
    }

    $email = sanitize_email($user_info['email']);
    $google_user_id = sanitize_text_field($user_info['sub']); // Google's unique user ID

    // Check if email is verified by Google
    if (!isset($user_info['email_verified']) || $user_info['email_verified'] !== true) {
        wp_die(esc_html__('Google email not verified. Please verify your email with Google first.', 'readysms'), esc_html__('Google Login Error', 'readysms'));
    }

    $user = get_user_by('email', $email);

    if (!$user) { // User doesn't exist, create them
        // Try to get name parts
        $first_name = isset($user_info['given_name']) ? sanitize_text_field($user_info['given_name']) : '';
        $last_name = isset($user_info['family_name']) ? sanitize_text_field($user_info['family_name']) : '';
        $display_name = isset($user_info['name']) ? sanitize_text_field($user_info['name']) : $first_name . ' ' . $last_name;
        if (empty(trim($display_name))) {
            $display_name = $email; // Fallback display name
        }

        // Create a username. Using Google ID is robust. Or a sanitized part of email.
        $username = 'google_' . $google_user_id;
        if (username_exists($username)) { // Highly unlikely but good to check
            $username = $username . '_' . wp_rand(100, 999);
        }
        
        $user_data = [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(), // Generate a random password
            'display_name' => $display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'role'         => class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber'),
        ];
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            wp_die(esc_html__('Error creating new user: ', 'readysms') . esc_html($user_id->get_error_message()), esc_html__('Google Login Error', 'readysms'));
        }
        $user = get_user_by('id', $user_id);
        // Optionally, mark that the user was created via Google
        update_user_meta($user_id, '_created_via_readysms_google', true);
        update_user_meta($user_id, '_readysms_google_id', $google_user_id);

    } else { // User exists, log them in
        $user_id = $user->ID;
        // Optionally, update Google ID if not present
        if (!get_user_meta($user_id, '_readysms_google_id', true)) {
             update_user_meta($user_id, '_readysms_google_id', $google_user_id);
        }
    }

    if ($user && !is_wp_error($user)) {
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true); // true for 'remember me'
        do_action('wp_login', $user->user_login, $user);

        // Redirect logic
        readysms_start_session_if_needed();
        $final_redirect_url = home_url('/'); // Default redirect
        if (isset($_SESSION['readysms_google_redirect_after_login'])) {
            $final_redirect_url = esc_url($_SESSION['readysms_google_redirect_after_login']);
            unset($_SESSION['readysms_google_redirect_after_login']);
        }
        wp_safe_redirect($final_redirect_url);
        exit;
    } else {
        wp_die(esc_html__('Could not log in user after Google authentication.', 'readysms'), esc_html__('Google Login Error', 'readysms'));
    }
}
// Hook into init or template_redirect to catch the Google callback
// 'init' is generally preferred for such background tasks.
add_action('init', 'ready_handle_google_login');

?>
