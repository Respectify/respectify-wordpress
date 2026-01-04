<?php

namespace Respectify;
use RespectifyScoper\Respectify\RespectifyClientAsync;

if (!function_exists('wp_salt')) {
    require_once(ABSPATH . 'wp-includes/pluggable.php');
}

function respectify_create_client() {
    $email = get_option(\Respectify\OPTION_EMAIL, '');
    $api_key = \Respectify\respectify_get_decrypted_api_key();
    $base_url = get_option(\Respectify\OPTION_BASE_URL, '');
    $api_version = get_option(\Respectify\OPTION_API_VERSION, '');
    
    // Get the website URL from WordPress
    $website = parse_url(get_site_url(), PHP_URL_HOST);

    $params = [
        'email' => $email,
        'apiKey' => $api_key,
        'baseUrl' => !empty($base_url) ? $base_url : null,
        'version' => !empty($api_version) ? floatval($api_version) : null,
        'website' => $website
    ];

    \Respectify\respectify_log('Respectify client constructed with base URL: ' . ($params['baseUrl'] ?? 'default') . ' and website: ' . ($website ?? 'none'));
    return new \RespectifyScoper\Respectify\RespectifyClientAsync(...array_values($params));
}

function respectify_create_test_client($email, $api_key, $base_url, $api_version, $website = null) {
    // If no website provided, try to get it from WordPress
    if ($website === null) {
        $website = parse_url(get_site_url(), PHP_URL_HOST);
    }
    
    $params = [
        'email' => $email,
        'apiKey' => $api_key,
        'baseUrl' => !empty($base_url) ? $base_url : null,
        'version' => !empty($api_version) ? floatval($api_version) : null,
        'website' => $website
    ];

    respectify_log('Test client constructed with base URL: ' . ($params['baseUrl'] ?? 'default') . ' and website: ' . ($website ?? 'none'));
    return new \RespectifyScoper\Respectify\RespectifyClientAsync(...array_values($params));
}

function get_friendly_message_which_client($base_url, $api_version) {
    if (!empty($base_url)) {
        return 'Connecting to URL ' . $base_url . (!empty($api_version) ? ' and API version ' . $api_version : ' (using default API version)');
    } else if (!empty($api_version)) {
        return 'Using default URL with API version ' . $api_version;
    }
    return '';
}

// Encryption function
function respectify_encrypt($data) {
    $encryption_key = wp_salt('auth');
    // This prepends random bytes to the encrypted data to create the IV (initialization vector)
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    if (function_exists('random_bytes')) {
        $iv = random_bytes($iv_length);
    } else {
        // Fallback for older PHP versions with strong parameter
        $iv = openssl_random_pseudo_bytes($iv_length, $crypto_strong);
        if (!$crypto_strong) {
            wp_die('Unable to generate cryptographically strong random bytes for encryption.');
        }
    }
    $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $encryption_key, 0, $iv);
    return base64_encode($iv . $encrypted_data);
}

// Decryption function
function respectify_decrypt($data) {
    $encryption_key = wp_salt('auth');
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $iv_length);
    // Undo the random bytes that were prepended to the encrypted data
    $encrypted_data = substr($data, $iv_length);
    return openssl_decrypt($encrypted_data, 'AES-256-CBC', $encryption_key, 0, $iv);
}

function respectify_get_decrypted_api_key() {
    $encrypted_api_key = get_option(\Respectify\OPTION_API_KEY_ENCRYPTED, '');
    if ($encrypted_api_key) {
        return respectify_decrypt($encrypted_api_key);
    }
    return '';
}

function respectify_log($message) {
    //if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($message);
    //}
}

/**
 * Find a contact page URL on the site.
 * Looks for pages with 'contact' in the slug or title.
 *
 * @return string|null The contact page URL, or null if not found.
 */
function respectify_get_contact_page_url() {
    // First, try to find a page with 'contact' in the slug
    $contact_page = get_page_by_path('contact');
    if ($contact_page) {
        return get_permalink($contact_page);
    }

    // Try common contact page slugs
    $common_slugs = ['contact', 'contact-us', 'contact-me', 'get-in-touch', 'reach-out'];
    foreach ($common_slugs as $slug) {
        $page = get_page_by_path($slug);
        if ($page) {
            return get_permalink($page);
        }
    }

    // Search for pages with 'contact' in the title
    $pages = get_pages([
        'post_status' => 'publish',
        'number' => 10
    ]);
    foreach ($pages as $page) {
        if (stripos($page->post_title, 'contact') !== false) {
            return get_permalink($page);
        }
    }

    return null;
}

/**
 * Build a user-friendly error message for commenters when API fails.
 *
 * @return string The error message HTML.
 */
function respectify_get_commenter_error_message() {
    $message = __('Respectify couldn\'t process your comment due to a configuration issue.', 'respectify');

    $contact_url = respectify_get_contact_page_url();
    if ($contact_url) {
        $message .= ' ' . sprintf(
            __('Please <a href="%s">contact the site administrator</a> if this problem persists.', 'respectify'),
            esc_url($contact_url)
        );
    } else {
        $message .= ' ' . __('Please contact the site administrator if this problem persists.', 'respectify');
    }

    return $message;
}

/**
 * Store an API error for admin notification.
 *
 * @param string $error_message The error message from the API.
 */
function respectify_store_api_error($error_message) {
    $error_data = [
        'message' => $error_message,
        'timestamp' => current_time('timestamp'),
        'formatted_time' => current_time('mysql')
    ];
    set_transient('respectify_api_error', $error_data, DAY_IN_SECONDS);
}

/**
 * Get the stored API error, if any.
 *
 * @return array|false The error data, or false if none.
 */
function respectify_get_api_error() {
    return get_transient('respectify_api_error');
}

/**
 * Clear the stored API error.
 */
function respectify_clear_api_error() {
    delete_transient('respectify_api_error');
}

/**
 * Send a rate-limited email to the admin about an API error.
 * Only sends one email per day to avoid spamming.
 *
 * @param string $error_message The error message from the API.
 * @return bool Whether the email was sent.
 */
function respectify_notify_admin_of_error($error_message) {
    // Check if we've already sent an email today
    $last_email_time = get_transient('respectify_last_error_email');
    if ($last_email_time !== false) {
        respectify_log('Skipping admin notification email - already sent within last 24 hours');
        return false;
    }

    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $settings_url = admin_url('options-general.php?page=respectify');

    $subject = sprintf(__('[%s] Respectify: Comments may not be posting correctly', 'respectify'), $site_name);

    $body = sprintf(__('Hello,

Respectify encountered an error while processing a comment on your site "%s".

Error details:
%s

This may mean:
- Your Respectify subscription has expired
- You\'re using features not available on your current plan
- There\'s a temporary API issue

What to do:
1. Check your Respectify settings: %s
2. Verify your subscription status at https://respectify.org
3. If the problem persists, contact Respectify support

Note: Comments that couldn\'t be processed are being held for manual moderation.

This is an automated message. You will only receive one notification per day.

--
Respectify WordPress Plugin', 'respectify'),
        $site_name,
        $error_message,
        $settings_url
    );

    $sent = wp_mail($admin_email, $subject, $body);

    if ($sent) {
        // Set transient to prevent sending another email for 24 hours
        set_transient('respectify_last_error_email', current_time('timestamp'), DAY_IN_SECONDS);
        respectify_log('Sent admin notification email about API error');
    } else {
        respectify_log('Failed to send admin notification email');
    }

    return $sent;
}

/**
 * Handle an API error: store it, notify admin, and return appropriate response.
 *
 * @param string $error_message The error message from the API.
 * @param array $commentdata The comment data.
 * @return array The modified comment data (held for moderation).
 */
function respectify_handle_api_error($error_message, $commentdata) {
    // Store error for admin notice
    respectify_store_api_error($error_message);

    // Send rate-limited email notification
    respectify_notify_admin_of_error($error_message);

    // Hold the comment for moderation instead of rejecting
    $commentdata['comment_approved'] = 0;
    $commentdata['comment_type'] = '';
    $commentdata['comment_meta'] = [
        'respectify_error' => $error_message,
        'respectify_held_reason' => 'api_error',
        'respectify_held_at' => current_time('mysql')
    ];

    return $commentdata;
}

/**
 * Get the Respectify branding footer HTML for feedback messages.
 * Includes a small logo and link to respectify.ai
 *
 * @return string The footer HTML.
 */
function respectify_get_branding_footer() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $logo_url = $plugin_url . 'images/icon-128x128.png';

    return '<div class="respectify-branding">' .
           '<a href="https://respectify.ai" target="_blank" rel="noopener noreferrer">' .
           '<img src="' . esc_url($logo_url) . '" alt="Respectify" class="respectify-logo" />' .
           '<span>Powered by Respectify</span>' .
           '</a></div>';
}

