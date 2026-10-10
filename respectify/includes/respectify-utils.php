<?php

namespace Respectify;
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\Exceptions\ArticleContextNotFoundException;
use RespectifyScoper\Respectify\Exceptions\BadRequestException;
use RespectifyScoper\Respectify\Exceptions\JsonDecodingException;
use RespectifyScoper\Respectify\Exceptions\PaymentRequiredException;
use RespectifyScoper\Respectify\Exceptions\RespectifyException;
use RespectifyScoper\Respectify\Exceptions\ServerException;
use RespectifyScoper\Respectify\Exceptions\UnauthorizedException;
use RespectifyScoper\Respectify\Exceptions\UnsupportedMediaTypeException;
use RespectifyScoper\Respectify\Moderation\ModerationCategory;
use RespectifyScoper\Respectify\Moderation\ModerationTolerances;
use RespectifyScoper\Respectify\Moderation\ToleranceLevel;

if (!function_exists('wp_salt')) {
    require_once(ABSPATH . 'wp-includes/pluggable.php');
}

/**
 * Determine whether a caught exception represents the server's "article context not found"
 * condition. The article context ID was well-formed but could not be resolved for the
 * current account/API key - typically because it was created under a previous key - and
 * should be regenerated before retrying.
 *
 * Prefers the typed exception from the (scoped) Respectify PHP library, but also matches the
 * stable server message, so detection still works even if the bundled library predates the
 * typed ArticleContextNotFoundException class.
 *
 * @param \Throwable $e The caught exception.
 * @return bool True if it indicates an unresolvable article context.
 */
function respectify_is_article_context_not_found(\Throwable $e) {
    $scoped_class = '\\RespectifyScoper\\Respectify\\Exceptions\\ArticleContextNotFoundException';
    if (class_exists($scoped_class) && $e instanceof $scoped_class) {
        return true;
    }
    // Stable cross-SDK marker (see backend ARTICLE_CONTEXT_NOT_FOUND_MESSAGE).
    return stripos($e->getMessage(), 'Article context not found') !== false;
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

/**
 * Write a message to the PHP error log, only when WP_DEBUG is true.
 *
 * This follows the WordPress convention for debug output: nothing is written on a production site, where
 * WP_DEBUG is off. With WP_DEBUG_LOG also on, WordPress points the PHP error log at wp-content/debug.log (or
 * the path WP_DEBUG_LOG names); otherwise messages go to the server's PHP error log. API failures reach the
 * site admin regardless, through the admin notice and email in respectify_handle_api_error().
 *
 * Plugin code logs only through this function; tests/Unit/SourceLogCallPrivacyTest.php enforces that.
 *
 * PRIVACY: never pass comment text, post content, commenter details (name, email, URL, IP, user agent),
 * request input, or whole comment arrays or evaluation results. Log metadata only - IDs, lengths, booleans,
 * action names, counts - using respectify_comment_log_summary() and respectify_describe_exception().
 * tests/Unit/SourceLogCallPrivacyTest.php enforces this.
 *
 * @param string $message The message to log.
 */
function respectify_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($message);
    }
}

/**
 * Describe a comment for the log without any of its private contents: IDs, lengths and flags only.
 *
 * @param array $commentdata The WordPress comment data array.
 * @return string JSON summary that is safe to log.
 */
function respectify_comment_log_summary($commentdata) {
    $summary = array(
        'post_id' => isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : null,
        'parent_id' => isset($commentdata['comment_parent']) ? (int) $commentdata['comment_parent'] : null,
        'content_length' => isset($commentdata['comment_content']) ? strlen((string) $commentdata['comment_content']) : 0,
        'has_author' => !empty($commentdata['comment_author']),
        'has_email' => !empty($commentdata['comment_author_email']),
        'has_url' => !empty($commentdata['comment_author_url']),
        'approved' => $commentdata['comment_approved'] ?? null,
        'type' => $commentdata['comment_type'] ?? null,
    );
    return (string) wp_json_encode($summary);
}

/**
 * Describe an exception for the log by its type and code only.
 *
 * The message is deliberately left out: Respectify library exceptions carry server error details and, for
 * some failures, the raw response body, either of which can echo the submitted comment or post text. The site
 * admin is told what went wrong through respectify_handle_api_error(), which also never uses the message.
 *
 * @param \Throwable $e The caught exception.
 * @return string The exception's short class name and code.
 */
function respectify_describe_exception(\Throwable $e) {
    $class_name = get_class($e);
    $short_class_name = substr($class_name, (int) strrpos('\\' . $class_name, '\\'));
    return $short_class_name . ' (code ' . $e->getCode() . ')';
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
 * Explain an API failure to the site admin without repeating the exception's message.
 *
 * PRIVACY: Respectify library exception messages carry server error details and, for some failures, the raw
 * response body, either of which can echo the submitted comment, the commenter's details or the post content.
 * Error reports are stored (transient, comment meta) and emailed, so they are built only from the exception's
 * class and code, which never carry user content. tests/Unit/ApiErrorReportingPrivacyTest.php enforces this.
 *
 * @param \Throwable $e The caught exception.
 * @return array{message: string, error_type: string, status_code: int|null} A translated, actionable
 *     explanation that already names the error type and status; the short exception class name; and the HTTP
 *     status, when the failure came from an HTTP response.
 */
function respectify_describe_api_error(\Throwable $e) {
    $class_name = get_class($e);
    $error_type = substr($class_name, (int) strrpos('\\' . $class_name, '\\'));

    // Library exceptions carry the HTTP status as their code (0 when there was no response). Other
    // exceptions' codes are unrelated values, eg a socket errno, so they are never presented as a status.
    $status_code = ($e instanceof RespectifyException && $e->getCode() >= 100) ? (int) $e->getCode() : null;

    // Most specific first: ArticleContextNotFoundException is a BadRequestException, and every library
    // exception is a RespectifyException.
    if ($e instanceof ArticleContextNotFoundException) {
        $explanation = __('Respectify could not find the article context for this post, even after creating a new one. Check that the email address and API key in Respectify settings are correct; if they are, this is usually temporary.', 'respectify');
    } elseif ($e instanceof UnauthorizedException) {
        $explanation = __('Respectify rejected the email address or API key. Check them in Respectify settings and use Test to confirm they work.', 'respectify');
    } elseif ($e instanceof PaymentRequiredException) {
        $explanation = __('Your Respectify account has no credit left or no active plan. Add credit or check your plan in the Respectify dashboard.', 'respectify');
    } elseif ($e instanceof BadRequestException) {
        $explanation = __('Respectify rejected the request as invalid. Check the topics and tolerance settings in Respectify settings; if they look right, update the plugin or contact Respectify support.', 'respectify');
    } elseif ($e instanceof UnsupportedMediaTypeException) {
        $explanation = __('Respectify did not accept the request format. Update the plugin, and check the API version under Advanced settings.', 'respectify');
    } elseif ($e instanceof ServerException) {
        $explanation = __('The Respectify service had an internal error. This is usually temporary; if it continues, contact Respectify support.', 'respectify');
    } elseif ($e instanceof JsonDecodingException) {
        $explanation = __('Respectify sent a response the plugin could not read. If you set a custom server URL or API version under Advanced settings, check them; otherwise this is usually temporary.', 'respectify');
    } elseif ($e instanceof RespectifyException) {
        $explanation = __('Respectify returned an unexpected error. If it continues, contact Respectify support.', 'respectify');
    } elseif ($e instanceof \UnexpectedValueException) {
        $explanation = __('Respectify returned an incomplete or unexpected result. This is usually temporary; if it continues, update the plugin or contact Respectify support.', 'respectify');
    } else {
        $explanation = __('The plugin could not get a response from Respectify: a network problem, a timeout, or an unexpected error. Check that your server can reach the Respectify service, and any custom server URL under Advanced settings.', 'respectify');
    }

    $identification = $status_code !== null
        /* translators: 1: exception class name, eg ServerException; 2: HTTP status code, eg 500 */
        ? sprintf(__('%1$s, HTTP %2$d', 'respectify'), $error_type, $status_code)
        : $error_type;

    return array(
        'message' => $explanation . ' (' . $identification . ')',
        'error_type' => $error_type,
        'status_code' => $status_code,
    );
}

/**
 * Store an API error for the admin notice.
 *
 * @param array{message: string, error_type: string, status_code: int|null} $error_description From
 *     respectify_describe_api_error(); never a raw exception message, which can carry comment text.
 */
function respectify_store_api_error($error_description) {
    $error_data = [
        'message' => $error_description['message'],
        'error_type' => $error_description['error_type'],
        'status_code' => $error_description['status_code'],
        'timestamp' => current_time('timestamp'),
        'formatted_time' => current_time('mysql')
    ];
    set_transient('respectify_api_error', $error_data, DAY_IN_SECONDS);
}

/**
 * Get the stored API error, if any.
 *
 * An error stored by an earlier plugin version holds the raw exception message, which can carry comment
 * text. Its message is replaced, and the replacement stored, so it is neither shown nor kept.
 *
 * @return array|false The error data, or false if none.
 */
function respectify_get_api_error() {
    $error_data = get_transient('respectify_api_error');
    if (is_array($error_data) && !array_key_exists('error_type', $error_data)) {
        $error_data['message'] = __('Respectify could not process a comment. Check Respectify settings and your account; details of this error were not kept.', 'respectify');
        $error_data['error_type'] = '';
        $error_data['status_code'] = null;
        set_transient('respectify_api_error', $error_data, DAY_IN_SECONDS);
    }
    return $error_data;
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
 * @param array{message: string, error_type: string, status_code: int|null} $error_description From
 *     respectify_describe_api_error(); never a raw exception message, which can carry comment text.
 * @return bool Whether the email was sent.
 */
function respectify_notify_admin_of_error($error_description) {
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

Respectify encountered an error while processing a comment on your site "%1$s".

What went wrong:
%2$s

What to do:
1. Check your Respectify settings: %3$s
2. Check your account and credit in the Respectify dashboard: https://respectify.ai/dashboard
3. If the problem persists, contact Respectify support and quote the error type above

Note: Comments that couldn\'t be processed are being held for manual moderation.

This is an automated message. You will only receive one notification per day.

--
Respectify WordPress Plugin', 'respectify'),
        $site_name,
        $error_description['message'],
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
 * Takes the exception itself rather than its message, so no caller can hand a raw message - which can echo the
 * comment - to the stored or emailed error report. See respectify_describe_api_error().
 *
 * @param \Throwable $api_error The exception the API call failed with.
 * @param array $commentdata The comment data.
 * @return array The modified comment data (held for moderation).
 */
function respectify_handle_api_error(\Throwable $api_error, $commentdata) {
    $error_description = respectify_describe_api_error($api_error);

    // Store error for admin notice
    respectify_store_api_error($error_description);

    // Send rate-limited email notification
    respectify_notify_admin_of_error($error_description);

    // Hold the comment for moderation instead of rejecting
    $commentdata['comment_approved'] = 0;
    $commentdata['comment_type'] = '';
    $commentdata['comment_meta'] = [
        'respectify_error' => $error_description['message'],
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
    $logo_url = $plugin_url . 'images/respectify.svg';

    return '<div class="respectify-branding">' .
           '<a href="https://respectify.ai" target="_blank" rel="noopener noreferrer">' .
           '<img src="' . esc_url($logo_url) . '" alt="Respectify" class="respectify-logo" />' .
           '<span>Powered by Respectify</span>' .
           '</a></div>';
}


/**
 * The default moderation tolerance settings: every category at 'standard', which is Respectify's
 * normal behaviour and what the plugin did before tolerances existed.
 *
 * @return array<string, string> Map of every tolerance category to 'standard'.
 */
function respectify_default_tolerance_settings() {
    $defaults = array();
    foreach (ModerationCategory::ALL as $category) {
        $defaults[$category] = ToleranceLevel::STANDARD;
    }
    return $defaults;
}

/**
 * Whether a tolerance level may be saved for a category. Mirrors the API's rules: the level must be a
 * known one, and the floored categories (threat, identity_attack, sexual_content, harassment) can never
 * be 'relaxed'.
 *
 * @param string $category The tolerance category, eg 'insult'.
 * @param mixed $level The candidate level (untrusted input).
 * @return bool True if the level is allowed for that category.
 */
function respectify_is_allowed_tolerance_level($category, $level) {
    if (!is_string($category) || !is_string($level)) {
        return false;
    }
    if (!ModerationCategory::isValid($category) || !ToleranceLevel::isValid($level)) {
        return false;
    }
    return !($level === ToleranceLevel::RELAXED && ModerationCategory::isFloored($category));
}

/**
 * The saved moderation tolerance settings, with every category present. A missing or invalid saved
 * entry (including a floored category somehow saved as 'relaxed') reads as 'standard'.
 *
 * @return array<string, string> Map of every tolerance category to its level.
 */
function respectify_get_tolerance_settings() {
    $saved = get_option(\Respectify\OPTION_TOLERANCES, array());
    if (!is_array($saved)) {
        $saved = array();
    }

    $settings = respectify_default_tolerance_settings();
    foreach (array_keys($settings) as $category) {
        if (isset($saved[$category]) && respectify_is_allowed_tolerance_level($category, $saved[$category])) {
            $settings[$category] = $saved[$category];
        }
    }
    return $settings;
}

/**
 * Build the per-call tolerance override to send with each comment evaluation.
 *
 * Only categories set to something other than 'standard' are sent. A category left at 'standard' keeps
 * whatever level the API key has saved in the Respectify dashboard (which is 'standard' unless changed
 * there), so the plugin never silently overrides dashboard settings with its defaults.
 *
 * @return ModerationTolerances|null The override, or null when every category is 'standard'.
 */
function respectify_build_tolerances_override() {
    $overrides = array();
    foreach (respectify_get_tolerance_settings() as $category => $level) {
        if ($level !== ToleranceLevel::STANDARD) {
            $overrides[$category] = $level;
        }
    }

    if (empty($overrides)) {
        return null;
    }

    try {
        return new ModerationTolerances($overrides);
    } catch (BadRequestException $e) {
        // Saved values are validated on save and again on read, so this indicates a mismatch between the
        // plugin and the bundled library. Evaluate under the API key's saved tolerances rather than fail.
        respectify_log('Respectify: ignoring saved moderation tolerances the library rejected: ' . $e->getMessage());
        return null;
    }
}
