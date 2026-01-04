<?php
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\Exceptions\RespectifyException;
use RespectifyScoper\Respectify\Exceptions\UnauthorizedException;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../includes/respectify-utils.php';

// Admin notice for API errors
add_action('admin_notices', 'respectify_admin_api_error_notice');
function respectify_admin_api_error_notice() {
    // Only show to users who can manage options
    if (!current_user_can('manage_options')) {
        return;
    }

    $error = \Respectify\respectify_get_api_error();
    if (!$error) {
        return;
    }

    // Check if user dismissed this notice
    if (get_transient('respectify_error_notice_dismissed')) {
        return;
    }

    $settings_url = admin_url('options-general.php?page=respectify');
    $dismiss_url = wp_nonce_url(add_query_arg('respectify_dismiss_notice', '1'), 'respectify_dismiss_notice');

    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('Respectify:', 'respectify'); ?></strong>
            <?php esc_html_e('Some visitors may be unable to post comments due to an API error.', 'respectify'); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Error:', 'respectify'); ?></strong>
            <?php echo esc_html($error['message']); ?>
        </p>
        <p>
            <strong><?php esc_html_e('When:', 'respectify'); ?></strong>
            <?php echo esc_html($error['formatted_time']); ?>
        </p>
        <p>
            <?php esc_html_e('Comments that couldn\'t be processed are being held for manual moderation.', 'respectify'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary"><?php esc_html_e('Check Settings', 'respectify'); ?></a>
            <a href="<?php echo esc_url($dismiss_url); ?>" class="button"><?php esc_html_e('Dismiss for 24 hours', 'respectify'); ?></a>
        </p>
    </div>
    <?php
}

// Handle dismissing the admin notice
add_action('admin_init', 'respectify_handle_notice_dismissal');
function respectify_handle_notice_dismissal() {
    if (isset($_GET['respectify_dismiss_notice']) && wp_verify_nonce($_GET['_wpnonce'], 'respectify_dismiss_notice')) {
        set_transient('respectify_error_notice_dismissed', true, DAY_IN_SECONDS);
        // Redirect to remove the query args
        wp_redirect(remove_query_arg(['respectify_dismiss_notice', '_wpnonce']));
        exit;
    }
}

// Add settings page to the admin menu
add_action('admin_menu', 'respectify_add_settings_page');
function respectify_add_settings_page() {
    add_options_page(
        esc_html__('Respectify Settings', 'respectify'),
        esc_html__('Respectify', 'respectify'),
        'manage_options',
        'respectify',
        'respectify_render_settings_page'
    );
}

// Register settings, sections, and fields
add_action('admin_init', 'respectify_register_settings');
function respectify_register_settings() {
    // Register settings
    register_setting('respectify_options_group', \Respectify\OPTION_EMAIL);
    register_setting('respectify_options_group', \Respectify\OPTION_API_KEY_ENCRYPTED);
    register_setting('respectify_options_group', \Respectify\OPTION_REVISE_SETTINGS);
    register_setting('respectify_options_group', \Respectify\OPTION_RELEVANCE_SETTINGS);
    register_setting('respectify_options_group', \Respectify\OPTION_BANNED_TOPICS);
    register_setting('respectify_options_group', \Respectify\OPTION_SPAM_HANDLING);
    register_setting('respectify_options_group', \Respectify\OPTION_ASSESSMENT_SETTINGS, 'respectify_sanitize_assessment_settings');
    register_setting('respectify_options_group', \Respectify\OPTION_DOGWHISTLE_SETTINGS, 'respectify_sanitize_dogwhistle_settings');
    register_setting('respectify_options_group', \Respectify\OPTION_SENSITIVE_TOPICS, 'respectify_sanitize_sensitive_topics');
    register_setting('respectify_options_group', \Respectify\OPTION_DOGWHISTLE_EXAMPLES, 'respectify_sanitize_dogwhistle_examples');
    register_setting('respectify_options_group', \Respectify\OPTION_BASE_URL, 'respectify_sanitize_base_url');
    register_setting('respectify_options_group', \Respectify\OPTION_API_VERSION, 'sanitize_text_field');

    // Add settings sections
    add_settings_section(
        'respectify_settings_section',
        esc_html__('API Credentials', 'respectify'),
        null,
        'respectify'
    );

    add_settings_section(
        'respectify_behavior_settings_section',
        esc_html__('Comment Handling', 'respectify'),
        'respectify_behavior_section_callback',
        'respectify'
    );

    add_settings_section(
        'respectify_advanced_settings_section',
        esc_html__('Rarely Used Settings', 'respectify'),
        'respectify_advanced_section_callback',
        'respectify'
    );

    // Add settings fields
    add_settings_field(
        \Respectify\OPTION_EMAIL,
        esc_html__('Email', 'respectify'),
        'respectify_email_callback',
        'respectify',
        'respectify_settings_section'
    );

    add_settings_field(
        'respectify_api_key',
        esc_html__('API Key', 'respectify'),
        'respectify_api_key_callback',
        'respectify',
        'respectify_settings_section'
    );

    // Add assessment checkboxes in their own rows
    add_settings_field(
        'respectify_assess_health',
        '',
        'respectify_assess_health_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_REVISE_SETTINGS,
        esc_html__('Healthy Comments', 'respectify'),
        'respectify_revise_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        'respectify_check_relevance',
        '',
        'respectify_check_relevance_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_RELEVANCE_SETTINGS,
        esc_html__('Off Topic Comments', 'respectify'),
        'respectify_relevance_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_BANNED_TOPICS,
        esc_html__('Undesired Topics', 'respectify'),
        'respectify_banned_topics_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        'respectify_check_dogwhistle',
        '',
        'respectify_check_dogwhistle_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_DOGWHISTLE_SETTINGS,
        esc_html__('Dogwhistle Settings', 'respectify'),
        'respectify_dogwhistle_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        'respectify_check_spam',
        '',
        'respectify_check_spam_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_SPAM_HANDLING,
        esc_html__('How to Handle Spam', 'respectify'),
        'respectify_spam_handling_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_BASE_URL,
        esc_html__('Base URL', 'respectify'),
        'respectify_base_url_callback',
        'respectify',
        'respectify_advanced_settings_section'
    );

    add_settings_field(
        \Respectify\OPTION_API_VERSION,
        esc_html__('API Version', 'respectify'),
        'respectify_api_version_callback',
        'respectify',
        'respectify_advanced_settings_section'
    );
}

// Helper function to create checkbox titles
function respectify_get_checkbox_title($setting, $title) {
    $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);
    return sprintf(
        '<label><input type="checkbox" name="respectify_assessment_settings[%s]" value="1" %s /> %s</label>',
        esc_attr($setting),
        checked($assessment_settings[$setting], true, false),
        esc_html__($title, 'respectify')
    );
}

// Custom sanitization function for base URL
// This uses normal sanitisation, but ensures that either http:// or https:// is added to the URL
// Local intranet sites may not have SSL, so we need to allow http://. Handle that by warning
// in the rendered settings page
function respectify_sanitize_base_url($url) {
    $url = trim($url);
    if (!empty($url) && !preg_match('/^https?:\/\//', $url)) { // http and https
        $url = 'https://' . $url;
    }
    return sanitize_text_field($url);
}

// Custom sanitization function for OPTION_REVISE_SETTINGS
function respectify_sanitize_revise_settings($input) {
    $sanitized_input = array();

    // Sanitize trackbar value (an integer between 1 and 5)
    if (isset($input['min_score']) && is_numeric($input['min_score'])) {
        $min_score_value = intval($input['min_score']);
        if ($min_score_value >= 1 && $min_score_value <= 5) {
            $sanitized_input['min_score'] = $min_score_value;
        } else {
            $sanitized_input['min_score'] = 3; // Default value
        }
    }

    // Sanitize checkboxes (assuming they are boolean values)
    $checkboxes = array('low_effort', 'logical_fallacies', 'objectionable_phrases', 'negative_tone');
    foreach ($checkboxes as $checkbox) {
        $sanitized_input[$checkbox] = isset($input[$checkbox]) && $input[$checkbox] === '1' ? '1' : '0';
    }

    return $sanitized_input;
}

// Callback to render behavior section
function respectify_behavior_section_callback() {
    echo '<p class="description description-match-font-size respectify-section-description">' . esc_html__('Configure how Respectify handles comments, especially the criteria for requesting a comment be revised before being posted.', 'respectify') . '</p>';
}

// Callback to render spam handling field
function respectify_spam_handling_callback() {
    $options = get_option(\Respectify\OPTION_SPAM_HANDLING, \Respectify\ACTION_DELETE);
    ?>
    <select name="respectify_spam_handling" id="respectify_spam_handling">
        <option value="<?php echo \Respectify\ACTION_DELETE; ?>" <?php selected($options, \Respectify\ACTION_DELETE); ?>><?php esc_html_e('Delete', 'respectify'); ?></option>
        <option value="<?php echo \Respectify\ACTION_REVISE; ?>" <?php selected($options, \Respectify\ACTION_REVISE); ?>><?php esc_html_e('Give Opportunity to Revise', 'respectify'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('By default spam is deleted, but you can treat them as normal comments and send them back for revision.', 'respectify'); ?></p>
    <?php
}

// Callback to render revise settings field
function respectify_revise_settings_callback() {
    $options = get_option(\Respectify\OPTION_REVISE_SETTINGS, \Respectify\REVISE_DEFAULT_SETTINGS);

    // Ensure $options is an array
    if (!is_array($options)) {
        error_log('Respectify: Invalid revise settings: ' . print_r($options, true));
        $options = \Respectify\REVISE_DEFAULT_SETTINGS;
    }
    ?>
    <div class="respectify-settings-column">
        <label for="respectify_revise_min_score"><?php esc_html_e('Minimum Score:', 'respectify'); ?></label>
        <div class="respectify-slider-row">
            <div class="slider-controls">
                <span class="emoji">😧</span>
                <input type="range" id="respectify_revise_min_score" name="respectify_revise_settings[min_score]" value="<?php echo esc_attr($options['min_score']); ?>" min="1" max="5" step="1">
                <span class="emoji">🤩</span>
                <span class="out-of description">
                    <span id="revise_min_score_value"><?php echo esc_html($options['min_score']); ?></span> 
                    <?php esc_html_e('out of 5.', 'respectify'); ?>
                </span>
            </div>
            <span class="description">
                <?php esc_html_e('A score of 3 out of 5 is a normal, good quality comment. 4 and 5 are outstanding and unusual.', 'respectify'); ?>
            </span>
        </div>
        <p class="description" style="margin-bottom: -0.5rem;">
            <?php esc_html_e('Revise comments which:', 'respectify'); ?>
        </p>
        <div class="respectify-checkbox-group">
            <label>
                <input type="checkbox" name="respectify_revise_settings[low_effort]" value="1" <?php checked($options['low_effort'], true); ?> />
                <?php esc_html_e('Seem Low Effort', 'respectify'); ?>
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[logical_fallacies]" value="1" <?php checked($options['logical_fallacies'], true); ?> />
                <?php esc_html_e('Contain Logical Fallacies', 'respectify'); ?>
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[objectionable_phrases]" value="1" <?php checked($options['objectionable_phrases'], true); ?> />
                <?php esc_html_e('Contain Objectionable Phrases', 'respectify'); ?>
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[negative_tone]" value="1" <?php checked($options['negative_tone'], true); ?> />
                <?php esc_html_e('Negative Tone Indications', 'respectify'); ?>
            </label>
        </div>
    </div>
    <?php
}

// Callback to render email input
function respectify_email_callback() {
    $email = get_option(\Respectify\OPTION_EMAIL, '');
    echo '<input type="email" name="respectify_email" value="' . esc_attr($email) . '" class="regular-text" required />';
    echo '<p class="description">' . esc_html__('Enter the email associated with your Respectify account.', 'respectify') . '</p>';
}

// Callback to render API key input
function respectify_api_key_callback() {
    $api_key = \Respectify\respectify_get_decrypted_api_key();
    ?>
    <div class="respectify-credentials-wrapper">
        <div class="respectify-credentials-left">
            <input type="password" name="respectify_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required />
            <p class="description"><?php esc_html_e('Enter the Respectify API key you wish to use for this Wordpress site.', 'respectify'); ?></p>

            <div class="respectify-test-container">
                <button type="button" id="respectify-test-button" class="button"><?php esc_html_e('Test', 'respectify'); ?></button>
                <span id="respectify-test-result"></span>
            </div>
            <p><?php esc_html_e('Click "Test" to verify the email and API key are working correctly.', 'respectify'); ?></p>
        </div>
        <div class="respectify-credentials-right">
            <div id="respectify-subscription-status" class="respectify-subscription-status" style="display: none;">
                <h4><?php esc_html_e('Subscription Status', 'respectify'); ?></h4>
                <p id="respectify-plan-name"></p>
                <div id="respectify-features-list" class="respectify-features-list"></div>
            </div>
        </div>
    </div>
    <?php
}

// Callback to render advanced section
function respectify_advanced_section_callback() {
    echo '<button type="button" class="respectify-accordion" id="respectify-advanced-settings-button">' . esc_html__('Display rarely used settings', 'respectify') . '</button>';
    echo '<div class="respectify-panel">';
    echo '<p class="description">' . esc_html__('Configure a custom Respectify server.', 'respectify') . '</p>';
}

// Callback to render base URL input
function respectify_base_url_callback() {
    $base_url = get_option(\Respectify\OPTION_BASE_URL, '');
    echo '<input type="text" name="respectify_base_url" value="' . esc_attr($base_url) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Enter the server URL for the Respectify API. An example is "app.respectify.org". You can include a port, eg, "localhost:8081". Leave blank for the normal Respectify service.', 'respectify') . '</p>';
}

// Callback to render API version input
function respectify_api_version_callback() {
    $api_version = get_option(\Respectify\OPTION_API_VERSION, '');
    echo '<input type="text" name="respectify_api_version" value="' . esc_attr($api_version) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Enter the API version for the Respectify API. An example is "1.0". Leave blank for the normal Respectify service.', 'respectify') . '</p>';
}

// Clear API error when email is updated
add_filter('pre_update_option_respectify_email', 'respectify_clear_error_on_email_update', 10, 2);
function respectify_clear_error_on_email_update($new_value, $old_value) {
    if ($new_value !== $old_value) {
        // Clear any stored API error when credentials are updated
        \Respectify\respectify_clear_api_error();
        delete_transient('respectify_error_notice_dismissed');
    }
    return $new_value;
}

// Encrypt API key before saving
add_filter('pre_update_option_respectify_api_key_encrypted', 'respectify_encrypt_api_key', 10, 2);
function respectify_encrypt_api_key($new_value, $old_value) {
    respectify_verify_nonce(); // wp_die-is if not valid

    if (!empty($_POST['respectify_api_key'])) {
        $api_key = sanitize_text_field(wp_unslash($_POST['respectify_api_key']));

        // Clear any stored API error when credentials are updated
        // (user is actively fixing the issue by saving new settings)
        \Respectify\respectify_clear_api_error();
        delete_transient('respectify_error_notice_dismissed');

        return \Respectify\respectify_encrypt($api_key);
    }
    return $old_value;
}

// Verify nonce before saving settings
add_action('admin_post_update', 'respectify_verify_nonce');
function respectify_verify_nonce() {
    // Check if the nonce is set
    if (isset($_POST['respectify_settings_nonce'])) {
        // Sanitize the nonce value
        $nonce = sanitize_text_field(wp_unslash($_POST['respectify_settings_nonce']));

        // Verify the nonce
        if (!wp_verify_nonce($nonce, 'respectify_save_settings')) {
            wp_die(
                esc_html__('Invalid nonce specified', 'respectify'),
                esc_html__('Error', 'respectify'),
                array(
                    'response'  => 403,
                    'back_link' => true,
                )
            );
        }
    } else {
        wp_die(
            esc_html__('No nonce specified', 'respectify'),
            esc_html__('Error', 'respectify'),
            array(
                'response'  => 403,
                'back_link' => true,
            )
        );
    }
}

// Render the settings page
function respectify_render_settings_page() {
    ?>
    <style>
        .respectify-indented th {
            padding-left: 2em !important;
        }
        .respectify-indented td {
            padding-left: 0 !important;
        }
    </style>
    <div class="wrap">
        <?php // Hidden h1 for WordPress to target with notices - keeps notices out of our header ?>
        <h1 class="wp-heading-inline" style="display: none;"></h1>
        <?php settings_errors(); ?>

        <div class="respectify-header">
            <img src="<?php echo esc_url(plugins_url('images/respectify.svg', dirname(__FILE__))); ?>" alt="<?php esc_attr_e('Respectify Logo', 'respectify'); ?>" class="respectify-logo">
            <span class="respectify-title"><?php esc_html_e('Respectify Settings', 'respectify'); ?></span>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields('respectify_options_group');
            wp_nonce_field('respectify_save_settings', 'respectify_settings_nonce');
            
            do_settings_sections('respectify');
            echo '</div>'; // end of the advanced settings div
            submit_button();
            ?>
        </form>
        <div id="respectify-test-result"></div>
    </div>
    <?php
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'respectify_enqueue_admin_scripts');
function respectify_enqueue_admin_scripts($hook_suffix) {
    if ($hook_suffix != 'settings_page_respectify') {
        return;
    }
    wp_enqueue_script('respectify-admin-js', plugin_dir_url(__FILE__) . '../js/respectify-admin.js', array('jquery'), '1.0.0', true);
    
    // Add localization data
    // Note: Use __() not esc_html__() because JS uses .text() which handles escaping
    wp_localize_script('respectify-admin-js', 'respectify_admin_i18n', array(
        'testing' => __('Testing...', 'respectify'),
        'success_no_message' => '✅ ' . __('Success, but no message provided. Try using Respectify but if you get errors, contact Support.', 'respectify'),
        'error_prefix' => '❌ ' . __('An error occurred: ', 'respectify'),
    ));

    wp_localize_script('respectify-admin-js', 'respectify_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('respectify_test_nonce'),
    ));
}

// Handle AJAX request for testing credentials
add_action('wp_ajax_respectify_test_credentials', 'respectify_test_credentials');
function respectify_test_credentials() {
    check_ajax_referer('respectify_test_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '❌ ' . __('Unauthorized. Please check your permissions and try again.', 'respectify')));
    }

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : get_option(\Respectify\OPTION_EMAIL, '');
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : \Respectify\respectify_get_decrypted_api_key();
    
    // advanced settings
    $base_url = isset($_POST['base_url']) ? sanitize_text_field(wp_unslash($_POST['base_url'])) : get_option(\Respectify\OPTION_BASE_URL, '');
    $api_version = isset($_POST['api_version']) ? sanitize_text_field(wp_unslash($_POST['api_version'])) : get_option(\Respectify\OPTION_API_VERSION, '');

    if (!class_exists('RespectifyScoper\Respectify\RespectifyClientAsync')) {
        wp_send_json_error(array('message' => __('Class not found.', 'respectify')));
    }
    $which_client = \Respectify\get_friendly_message_which_client($base_url, $api_version);
    error_log('Testing credentials with email ' . $email . ' - ' . $which_client);
    
    // Choose the test client creation method based on advanced settings values
    $client = \Respectify\respectify_create_test_client($email, $api_key, $base_url, $api_version);
    
    $promise = $client->checkUserCredentials();

    $promise->then(
        function ($userCheckResponse) use ($base_url, $api_version) {
            // UserCheckResponse object has: active, status, expires, planName, allowedEndpoints, error
            $which_client = \Respectify\get_friendly_message_which_client($base_url, $api_version);
            error_log('Base url ' . $base_url . ' and API version ' . (!empty($api_version) ? $api_version : 'default') . ' give: which client ' . $which_client);
            // Note: Use __() not esc_html__() because JS uses .text() which handles escaping
            if (!empty($which_client)) {
                $which_client = ' (' . $which_client . ')';
            }

            // Prepare subscription data for JS
            $subscription_data = array(
                'active' => $userCheckResponse->active,
                'plan_name' => $userCheckResponse->planName,
                'allowed_endpoints' => $userCheckResponse->allowedEndpoints,
            );

            // Check if there's an active subscription
            $has_active_subscription = $subscription_data['active'] && !empty($subscription_data['plan_name']);

            if ($has_active_subscription) {
                $message = '✅ ' . __('Authorization successful - click Save Changes, and then you\'re good to go!', 'respectify') . $which_client;
            } else {
                // Auth works but no subscription - show warning
                $billing_url = 'https://respectify.ai/dashboard/billing';
                $message = '⚠️ ' . sprintf(
                    /* translators: %s: URL to billing page */
                    __('Authorization successful, but no active plan found. Please visit <a href="%s" target="_blank">your billing page</a> to subscribe.<br>Click Save Changes to save this email and API key.', 'respectify'),
                    esc_url($billing_url)
                );
            }

            wp_send_json_success(array(
                'message' => $message,
                'subscription' => $subscription_data,
                'has_subscription' => $has_active_subscription,
            ));
        },
        function ($ex) {
            // Note: Use __() not esc_html__() because JS uses .text() which handles escaping
            $unauth_message = '⛔️ ' . __('Unauthorized. This means there was an error with the email and/or API key. Please check them and try again.', 'respectify');
            $errorMessage = $ex->getMessage();
            if ($ex->getCode() === 401) {
                $errorMessage = $unauth_message;
            }
            if (strpos($errorMessage, 'Connection to') !== false && strpos($errorMessage, 'failed:') !== false) {
                $base_url = get_option(\Respectify\OPTION_BASE_URL, '');
                $api_version = get_option(\Respectify\OPTION_API_VERSION, '');
                if (!empty($base_url) || !empty($api_version)) {
                    $errorMessage = sprintf(
                        /* translators: %1$s: Base URL, %2$s: API Version */
                        '⛔️ ' . __('Connection to %1$s version %2$s failed. Please check the URL and try again.', 'respectify'),
                        $base_url,
                        $api_version
                    ) . ' - ' . $errorMessage;
                }
            }
            wp_send_json_error(array('message' => $errorMessage));
        }
    );

    try {
        $client->run();
    } catch (\Exception $e) {
        wp_send_json_error(array('message' => '❌ ' . __('An unexpected error occurred: ', 'respectify') . $e->getMessage()));
    }
}

// Add the sanitization function for relevance settings
function respectify_sanitize_relevance_settings($input) {
    $sanitized_input = array();

    // Sanitize off-topic handling
    if (isset($input['off_topic_handling'])) {
        $sanitized_input['off_topic_handling'] = sanitize_text_field($input['off_topic_handling']);
    }

    // Sanitize banned topics handling
    if (isset($input['banned_topics_handling'])) {
        $sanitized_input['banned_topics_handling'] = sanitize_text_field($input['banned_topics_handling']);
    }

    // Sanitize banned topics threshold (float between 0 and 1)
    if (isset($input['banned_topics_threshold'])) {
        $threshold = floatval($input['banned_topics_threshold']);
        $sanitized_input['banned_topics_threshold'] = max(0, min(1, $threshold));
    }

    return $sanitized_input;
}

// Add the sanitization function for banned topics
function respectify_sanitize_banned_topics($input) {
    // Split input into lines and sanitize each line
    $topics = explode("\n", $input);
    $sanitized_topics = array();
    
    foreach ($topics as $topic) {
        $topic = trim($topic);
        if (!empty($topic)) {
            $sanitized_topics[] = sanitize_text_field($topic);
        }
    }
    
    return implode("\n", $sanitized_topics);
}

// Add the callback for relevance settings
function respectify_relevance_settings_callback() {
    $relevance_settings = get_option(\Respectify\OPTION_RELEVANCE_SETTINGS, \Respectify\RELEVANCE_DEFAULT_SETTINGS);
    ?>
    <div class="respectify-settings-column">
        <div class="respectify-settings-row">
            <div class="respectify-settings-control">
                <select name="respectify_relevance_settings[off_topic_handling]" id="respectify_off_topic_handling">
                    <option value="<?php echo \Respectify\ACTION_PUBLISH; ?>" <?php selected($relevance_settings['off_topic_handling'], \Respectify\ACTION_PUBLISH); ?>><?php esc_html_e('Are Okay', 'respectify'); ?></option>
                    <option value="<?php echo \Respectify\ACTION_DELETE; ?>" <?php selected($relevance_settings['off_topic_handling'], \Respectify\ACTION_DELETE); ?>><?php esc_html_e('Delete', 'respectify'); ?></option>
                    <option value="<?php echo \Respectify\ACTION_REVISE; ?>" <?php selected($relevance_settings['off_topic_handling'], \Respectify\ACTION_REVISE); ?>><?php esc_html_e('Give Opportunity to Revise', 'respectify'); ?></option>
                </select>
            </div>
            <div class="respectify-settings-label">
                <p class="description"><?php esc_html_e('By default off-topic comments are allowed, for freely flowing conversation, but you can require comments are related to the main topic.', 'respectify'); ?></p>
            </div>
            
        </div>
    </div>
    <?php
}

// Add a new callback for banned topics settings
function respectify_banned_topics_settings_callback() {
    $relevance_settings = get_option(\Respectify\OPTION_RELEVANCE_SETTINGS, \Respectify\RELEVANCE_DEFAULT_SETTINGS);
    $banned_topics = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
    ?>
    <div class="respectify-settings-column">
        <div class="respectify-settings-row">
            <div class="respectify-settings-label">
                <p class="description"><?php esc_html_e('What topics do you just not want to see in your comments? Enter one per line.', 'respectify'); ?></p>
                <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('Be descriptive: instead of "politics", try "US politics after 1970". This avoids over-blocking.', 'respectify'); ?></p>
            
            </div>
            <div class="respectify-settings-control">
                <textarea name="respectify_banned_topics" id="respectify_banned_topics" rows="5" class="large-text"><?php echo esc_textarea($banned_topics); ?></textarea>
                
                <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('When a comment mentions something you don\'t want discussed:', 'respectify'); ?></p>
                
                
                <div class="respectify-radio-group" style="display: flex; flex-direction: column; gap: 10px;">
                    <label>
                        <input type="radio" name="respectify_relevance_settings[banned_topics_mode]" 
                               value="any" <?php checked($relevance_settings['banned_topics_mode'], 'any'); ?> />
                        <?php esc_html_e('Any mention at all', 'respectify'); ?>
                    </label>
                    <label>
                        <input type="radio" name="respectify_relevance_settings[banned_topics_mode]" 
                               value="threshold" <?php checked($relevance_settings['banned_topics_mode'], 'threshold'); ?> />
                        <?php esc_html_e('The comment can mention something, but only only a little bit', 'respectify'); ?>
                    </label>
                </div>
                
                <div id="banned-topics-threshold-slider" class="respectify-slider-row" style="margin-top: 10px; <?php echo $relevance_settings['banned_topics_mode'] === 'threshold' ? '' : 'opacity: 0.5;'; ?>">
                    <div class="slider-controls">
                        <span class="respectify-slider-indicator">○</span>
                        <input type="range" id="respectify_banned_topics_threshold" 
                               name="respectify_relevance_settings[banned_topics_threshold]" 
                               value="<?php echo esc_attr($relevance_settings['banned_topics_threshold']); ?>" 
                               min="0.1" max="1" step="0.1" class="regular-text"
                               <?php echo $relevance_settings['banned_topics_mode'] === 'threshold' ? '' : 'disabled'; ?>>
                        <span class="respectify-slider-indicator">●</span>
                    </div>
                    <span class="description" style="margin-top: 10px; margin-left: 0.35rem;">
                        <?php 
                        printf(
                            esc_html__('It\'s ok for %d%% of the comment to be about an unwanted topic.', 'respectify'),
                            round($relevance_settings['banned_topics_threshold'] * 100)
                        );
                        ?>
                    </span>
                </div>

                <select name="respectify_relevance_settings[banned_topics_handling]" id="respectify_banned_topics_handling">
                    <option value="<?php echo \Respectify\ACTION_DELETE; ?>" <?php selected($relevance_settings['banned_topics_handling'], \Respectify\ACTION_DELETE); ?>><?php esc_html_e('Delete', 'respectify'); ?></option>
                    <option value="<?php echo \Respectify\ACTION_REVISE; ?>" <?php selected($relevance_settings['banned_topics_handling'], \Respectify\ACTION_REVISE); ?>><?php esc_html_e('Give Opportunity to Revise', 'respectify'); ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php
}

// Add sanitization function for assessment settings
function respectify_sanitize_assessment_settings($input) {
    $sanitized_input = array();

    $checkboxes = array('assess_health', 'check_relevance', 'check_spam', 'check_dogwhistle');
    foreach ($checkboxes as $checkbox) {
        $sanitized_input[$checkbox] = isset($input[$checkbox]) && $input[$checkbox] === '1' ? true : false;
    }

    return $sanitized_input;
}

// Add the callbacks for individual assessment checkboxes
function respectify_assess_health_callback() {
    $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

    // Safety check: if settings are not an array, use defaults
    if (!is_array($assessment_settings)) {
        $assessment_settings = \Respectify\ASSESSMENT_DEFAULT_SETTINGS;
        // Try to fix the stored settings
        update_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, $assessment_settings);
    }

    ?>
    <tr class="respectify-checkbox-row">
        <th scope="row">
            <label>
                <input type="checkbox" name="respectify_assessment_settings[assess_health]" value="1" <?php checked($assessment_settings['assess_health'], true); ?> />
                <?php esc_html_e('Assess Comment Health', 'respectify'); ?>
            </label>
        </th>
        <td></td>
    </tr>
    <?php
}

function respectify_check_relevance_callback() {
    $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

    // Safety check: if settings are not an array, use defaults
    if (!is_array($assessment_settings)) {
        $assessment_settings = \Respectify\ASSESSMENT_DEFAULT_SETTINGS;
        // Try to fix the stored settings
        update_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, $assessment_settings);
    }

    ?>
    <tr class="respectify-checkbox-row respectify-checkbox-row-with-spacing">
        <th scope="row">
            <label>
                <input type="checkbox" name="respectify_assessment_settings[check_relevance]" value="1" <?php checked($assessment_settings['check_relevance'], true); ?> />
                <?php esc_html_e('Check Topic Relevance', 'respectify'); ?>
            </label>
        </th>
        <td></td>
    </tr>
    <?php
}

function respectify_check_spam_callback() {
    $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);
    
    // Safety check: if settings are not an array, use defaults
    if (!is_array($assessment_settings)) {
        $assessment_settings = \Respectify\ASSESSMENT_DEFAULT_SETTINGS;
        // Try to fix the stored settings
        update_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, $assessment_settings);
    }
    
    ?>
    <tr class="respectify-checkbox-row respectify-checkbox-row-with-spacing">
        <th scope="row">
            <label>
                <input type="checkbox" name="respectify_assessment_settings[check_spam]" value="1" <?php checked($assessment_settings['check_spam'], true); ?> />
                <?php esc_html_e('Check for Spam', 'respectify'); ?>
            </label>
        </th>
        <td></td>
    </tr>
    <?php
}

function respectify_check_dogwhistle_callback() {
    $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

    // Safety check: if settings are not an array, use defaults
    if (!is_array($assessment_settings)) {
        $assessment_settings = \Respectify\ASSESSMENT_DEFAULT_SETTINGS;
        // Try to fix the stored settings
        update_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, $assessment_settings);
    }

    $checkbox_checked = isset($assessment_settings['check_dogwhistle']) ? $assessment_settings['check_dogwhistle'] : true;

    ?>
    <tr class="respectify-checkbox-row respectify-checkbox-row-with-spacing">
        <th scope="row">
            <label>
                <input type="checkbox" name="respectify_assessment_settings[check_dogwhistle]" value="1" <?php checked($checkbox_checked, true); ?> />
                <?php esc_html_e('Check for Dogwhistles', 'respectify'); ?>
            </label>
        </th>
        <td></td>
    </tr>
    <?php
}

// Callback for dogwhistle settings (handling, sensitive topics, examples)
function respectify_dogwhistle_settings_callback() {
    $dogwhistle_settings = get_option(\Respectify\OPTION_DOGWHISTLE_SETTINGS, array('handling' => \Respectify\DOGWHISTLE_DEFAULT_HANDLING));
    $sensitive_topics = get_option(\Respectify\OPTION_SENSITIVE_TOPICS, '');
    $dogwhistle_examples = get_option(\Respectify\OPTION_DOGWHISTLE_EXAMPLES, '');

    // Ensure settings array has the expected structure
    if (!is_array($dogwhistle_settings)) {
        $dogwhistle_settings = array('handling' => \Respectify\DOGWHISTLE_DEFAULT_HANDLING);
    }
    if (!isset($dogwhistle_settings['handling'])) {
        $dogwhistle_settings['handling'] = \Respectify\DOGWHISTLE_DEFAULT_HANDLING;
    }
    ?>
    <div class="respectify-settings-column">
        <p class="description"><?php esc_html_e('Dogwhistles are coded language that appears innocent but contains hidden meanings, often used for harmful purposes.', 'respectify'); ?></p>

        <div class="respectify-settings-row" style="margin-top: 15px;">
            <div class="respectify-settings-label">
                <label for="respectify_sensitive_topics"><strong><?php esc_html_e('Sensitive Topics', 'respectify'); ?></strong></label>
                <p class="description"><?php esc_html_e('What topics are sensitive in your community where dogwhistles might appear? Enter one per line.', 'respectify'); ?></p>
                <p class="description"><?php esc_html_e('For example: nationalism, racial issues, LGBTQ+ rights, conspiracy theories. This helps focus detection on discussions where coded language is more likely.', 'respectify'); ?></p>
            </div>
            <div class="respectify-settings-control">
                <textarea name="respectify_sensitive_topics" id="respectify_sensitive_topics" rows="4" class="large-text"><?php echo esc_textarea($sensitive_topics); ?></textarea>
            </div>
        </div>

        <div class="respectify-settings-row" style="margin-top: 15px;">
            <div class="respectify-settings-label">
                <label for="respectify_dogwhistle_examples"><strong><?php esc_html_e('Dogwhistle Examples', 'respectify'); ?></strong></label>
                <p class="description"><?php esc_html_e('Have you noticed specific coded language being used in your community\'s comments? Enter one per line.', 'respectify'); ?></p>
                <p class="description"><?php esc_html_e('Examples might include: inside jokes with hidden meanings, references that seem innocent but aren\'t, or terms you have seen used differently than their standard meaning. Respectify detects dogwhistles automatically, but this helps it understand your community\'s specific context.', 'respectify'); ?></p>
            </div>
            <div class="respectify-settings-control">
                <textarea name="respectify_dogwhistle_examples" id="respectify_dogwhistle_examples" rows="4" class="large-text"><?php echo esc_textarea($dogwhistle_examples); ?></textarea>
            </div>
        </div>

        <div class="respectify-settings-row" style="margin-top: 15px;">
            <div class="respectify-settings-label">
                <label for="respectify_dogwhistle_handling"><strong><?php esc_html_e('When Dogwhistles Detected', 'respectify'); ?></strong></label>
            </div>
            <div class="respectify-settings-control">
                <select name="respectify_dogwhistle_settings[handling]" id="respectify_dogwhistle_handling">
                    <option value="<?php echo esc_attr(\Respectify\ACTION_PUBLISH); ?>" <?php selected($dogwhistle_settings['handling'], \Respectify\ACTION_PUBLISH); ?>><?php esc_html_e('Allow (Publish)', 'respectify'); ?></option>
                    <option value="<?php echo esc_attr(\Respectify\ACTION_REVISE); ?>" <?php selected($dogwhistle_settings['handling'], \Respectify\ACTION_REVISE); ?>><?php esc_html_e('Give Opportunity to Revise', 'respectify'); ?></option>
                    <option value="<?php echo esc_attr(\Respectify\ACTION_DELETE); ?>" <?php selected($dogwhistle_settings['handling'], \Respectify\ACTION_DELETE); ?>><?php esc_html_e('Delete', 'respectify'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Choose how to handle comments that contain potential dogwhistles.', 'respectify'); ?></p>
            </div>
        </div>
    </div>
    <?php
}

// Sanitization function for dogwhistle settings
function respectify_sanitize_dogwhistle_settings($input) {
    $sanitized_input = array();

    if (isset($input['handling'])) {
        $valid_actions = array(\Respectify\ACTION_PUBLISH, \Respectify\ACTION_REVISE, \Respectify\ACTION_DELETE);
        if (in_array($input['handling'], $valid_actions)) {
            $sanitized_input['handling'] = $input['handling'];
        } else {
            $sanitized_input['handling'] = \Respectify\DOGWHISTLE_DEFAULT_HANDLING;
        }
    } else {
        $sanitized_input['handling'] = \Respectify\DOGWHISTLE_DEFAULT_HANDLING;
    }

    return $sanitized_input;
}

// Sanitization function for sensitive topics
function respectify_sanitize_sensitive_topics($input) {
    $topics = explode("\n", $input);
    $sanitized_topics = array();

    foreach ($topics as $topic) {
        $topic = trim($topic);
        if (!empty($topic)) {
            $sanitized_topics[] = sanitize_text_field($topic);
        }
    }

    return implode("\n", $sanitized_topics);
}

// Sanitization function for dogwhistle examples
function respectify_sanitize_dogwhistle_examples($input) {
    $examples = explode("\n", $input);
    $sanitized_examples = array();

    foreach ($examples as $example) {
        $example = trim($example);
        if (!empty($example)) {
            $sanitized_examples[] = sanitize_text_field($example);
        }
    }

    return implode("\n", $sanitized_examples);
}

// Add filter to modify the field titles
add_filter('gettext', 'respectify_modify_field_titles', 10, 3);
function respectify_modify_field_titles($translated_text, $text, $domain) {
    if ($domain !== 'respectify') {
        return $translated_text;
    }

    $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

    switch ($text) {
        case 'respectify_assess_health_title':
            return sprintf(
                '<label><input type="checkbox" name="respectify_assessment_settings[assess_health]" value="1" %s /> %s</label>',
                checked($assessment_settings['assess_health'], true, false),
                esc_html__('Assess Comment Health', 'respectify')
            );
        case 'respectify_check_relevance_title':
            return sprintf(
                '<label><input type="checkbox" name="respectify_assessment_settings[check_relevance]" value="1" %s /> %s</label>',
                checked($assessment_settings['check_relevance'], true, false),
                esc_html__('Check Topic Relevance', 'respectify')
            );
        case 'respectify_check_spam_title':
            return sprintf(
                '<label><input type="checkbox" name="respectify_assessment_settings[check_spam]" value="1" %s /> %s</label>',
                checked($assessment_settings['check_spam'], true, false),
                esc_html__('Check for Spam', 'respectify')
            );
        default:
            return $translated_text;
    }
}