<?php
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\Exceptions\RespectifyException;
use RespectifyScoper\Respectify\Exceptions\UnauthorizedException;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../includes/respectify-utils.php';

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
    register_setting(
        'respectify_options_group',
        \Respectify\OPTION_EMAIL,
        array(
            'sanitize_callback' => 'sanitize_email',
        )
    );
    register_setting(
        'respectify_options_group',
        \Respectify\OPTION_API_KEY_ENCRYPTED,
        array(
            'sanitize_callback' => 'sanitize_text_field',
        )
    );

    add_settings_section(
        'respectify_settings_section',
        esc_html__('API Credentials', 'respectify'),
        null,
        'respectify'
    );

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

    add_settings_section(
        'respectify_behavior_settings_section',
        esc_html__('Comment Handling', 'respectify'),
        'respectify_behavior_section_callback',
        'respectify'
    );

    // How to handle spam
    register_setting(
        'respectify_options_group',
        \Respectify\OPTION_SPAM_HANDLING,
        array(
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    add_settings_field(
        \Respectify\OPTION_SPAM_HANDLING,
        esc_html__('How to Handle Spam', 'respectify'),
        'respectify_spam_handling_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    // Revise When Settings
    register_setting(
        'respectify_options_group',
        \Respectify\OPTION_REVISE_SETTINGS,
        array(
            'sanitize_callback' => 'respectify_sanitize_revise_settings',
        )
    );
    add_settings_field(
        \Respectify\OPTION_REVISE_SETTINGS,
        esc_html__('Revise a Comment When', 'respectify'),
        'respectify_revise_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    // New Settings Section for Advanced Parameters
    add_settings_section(
        'respectify_advanced_settings_section',
        esc_html__('Rarely Used Settings', 'respectify'),
        'respectify_advanced_section_callback',
        'respectify'
    );

    // Base URL
    register_setting(
        'respectify_options_group',
        \Respectify\OPTION_BASE_URL,
        array(
            'sanitize_callback' => 'respectify_sanitize_base_url',
        )
    );
    add_settings_field(
        \Respectify\OPTION_BASE_URL,
        esc_html__('Base URL', 'respectify'),
        'respectify_base_url_callback',
        'respectify',
        'respectify_advanced_settings_section'
    );

    // API Version
    register_setting(
        'respectify_options_group',
        \Respectify\OPTION_API_VERSION,
        array(
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    add_settings_field(
        \Respectify\OPTION_API_VERSION,
        esc_html__('API Version', 'respectify'),
        'respectify_api_version_callback',
        'respectify',
        'respectify_advanced_settings_section'
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
    echo '<p class="description description-match-font-size">' . esc_html__('Configure how Respectify handles comments, especially the criteria for requesting a comment be revised before being posted.', 'respectify') . '</p>';
    echo '<div class="respectify-settings-row">';
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
        <div class="respectify-slider-row">
            <label for="respectify_revise_min_score"><?php esc_html_e('Minimum Score:', 'respectify'); ?></label>
            <span class="emoji">😧</span>
            <input type="range" id="respectify_revise_min_score" name="respectify_revise_settings[min_score]" value="<?php echo esc_attr($options['min_score']); ?>" min="1" max="5" step="1">
            <span class="emoji">🤩</span>
            <span class="out-of description">
                <span id="revise_min_score_value"><?php echo esc_html($options['min_score']); ?></span> 
                <?php esc_html_e('out of 5.', 'respectify'); ?>
            </span>
            <span class="out-of description">
                <br/>
                <?php esc_html_e('Recommended value: 3 out of 5.', 'respectify'); ?>
                <br/>
                <?php esc_html_e('A score of 3 out of 5 is a normal, good quality comment. 4 and 5 are outstanding and unusual.', 'respectify'); ?>
            </span>
        </div>
        <div class="respectify-checkbox-group">
            <label>
                <input type="checkbox" name="respectify_revise_settings[low_effort]" value="1" <?php checked($options['low_effort'], true); ?> />
                <?php esc_html_e('Seems Low Effort', 'respectify'); ?>
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[logical_fallacies]" value="1" <?php checked($options['logical_fallacies'], true); ?> />
                <?php esc_html_e('Contains Logical Fallacies', 'respectify'); ?>
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[objectionable_phrases]" value="1" <?php checked($options['objectionable_phrases'], true); ?> />
                <?php esc_html_e('Contains Objectionable Phrases', 'respectify'); ?>
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[negative_tone]" value="1" <?php checked($options['negative_tone'], true); ?> />
                <?php esc_html_e('Negative Tone Indications', 'respectify'); ?>
            </label>
        </div>
    </div>
    <p class="description"><?php esc_html_e('Define the conditions under which a comment should be revised.', 'respectify'); ?></p>
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
    echo '<input type="password" name="respectify_api_key" value="' . esc_attr($api_key) . '" class="regular-text" required />';
    echo '<p class="description">' . esc_html__('Enter the Respectify API key you wish to use for this Wordpress site.', 'respectify') . '</p>';

    ?>
    <div class="respectify-test-container">
        <button type="button" id="respectify-test-button" class="button"><?php esc_html_e('Test', 'respectify'); ?></button>
        <span id="respectify-test-result"></span>   
    </div>
    <p><?php esc_html_e('Click "Test" to verify the email and API key are working correctly.', 'respectify'); ?></p>
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

// Encrypt API key before saving
add_filter('pre_update_option_respectify_api_key_encrypted', 'respectify_encrypt_api_key', 10, 2);
function respectify_encrypt_api_key($new_value, $old_value) {
    respectify_verify_nonce(); // wp_die-is if not valid

    if (!empty($_POST['respectify_api_key'])) {
        $api_key = sanitize_text_field(wp_unslash($_POST['respectify_api_key']));
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
    <div class="wrap">
        <h1><?php esc_html_e('Respectify Settings', 'respectify'); ?></h1>
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
    wp_localize_script('respectify-admin-js', 'respectify_admin_i18n', array(
        'testing' => esc_html__('Testing...', 'respectify'),
        'success_no_message' => '✅ ' . esc_html__('Success, but no message provided. Try using Respectify but if you get errors, contact Support.', 'respectify'),
        'error_prefix' => '❌ ' . esc_html__('An error occurred: ', 'respectify'),
    ));

    wp_localize_script('respectify-admin-js', 'respectify_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('respectify_test_nonce'),
    ));

    // Add inline CSS for side-by-side settings and checkbox alignment
    $custom_css = '
    .respectify-indented th {
        padding-left: 20px;
    }
    .respectify-indented td {
        padding-left: 20px;
    }
    ';
    wp_add_inline_style('wp-admin', $custom_css);
}

// Handle AJAX request for testing credentials
add_action('wp_ajax_respectify_test_credentials', 'respectify_test_credentials');
function respectify_test_credentials() {
    check_ajax_referer('respectify_test_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '❌ ' . esc_html__('Unauthorized. Please check your permissions and try again.', 'respectify')));
    }

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : get_option(\Respectify\OPTION_EMAIL, '');
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : \Respectify\respectify_get_decrypted_api_key();
    
    // advanced settings
    $base_url = isset($_POST['base_url']) ? sanitize_text_field(wp_unslash($_POST['base_url'])) : get_option(\Respectify\OPTION_BASE_URL, '');
    $api_version = isset($_POST['api_version']) ? sanitize_text_field(wp_unslash($_POST['api_version'])) : get_option(\Respectify\OPTION_API_VERSION, '');

    if (!class_exists('RespectifyScoper\Respectify\RespectifyClientAsync')) {
        wp_send_json_error(array('message' => esc_html__('Class not found.', 'respectify')));
    }
    $which_client = \Respectify\get_friendly_message_which_client($base_url, $api_version);
    error_log('Testing credentials with email ' . $email . ' - ' . $which_client);
    
    // Choose the test client creation method based on advanced settings values
    $client = \Respectify\respectify_create_test_client($email, $api_key, $base_url, $api_version);
    
    $promise = $client->checkUserCredentials();

    $promise->then(
        function ($result) use ($base_url, $api_version) {
            list($success, $info) = $result;
            $which_client = \Respectify\get_friendly_message_which_client($base_url, $api_version);
            error_log('Base url ' . $base_url . ' and API version ' . (!empty($api_version) ? $api_version : 'default') . ' give: which client ' . $which_client);
            if (!empty($which_client)) {
                $which_client = '<br><span style="font-size: smaller;">' . esc_html($which_client) . "</span>";
            }
            if ($success) {
                wp_send_json_success(array('message' => '✅ ' . esc_html__('Authorization successful - click Save Changes, and then you\'re good to go!', 'respectify') . $which_client));
            } else {
                wp_send_json_error(array('message' => '⚠️ ' . esc_html($info) . $which_client));
            }
        },
        function ($ex) {
            $unauth_message = '⛔️ ' . esc_html__('Unauthorized. This means there was an error with the email and/or API key. Please check them and try again.', 'respectify');
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
                        '⛔️ ' . esc_html__('Connection to %1$s version %2$s failed. Please check the URL and try again.', 'respectify'),
                        esc_html($base_url),
                        esc_html($api_version)
                    ) . '<br/><span style="font-size: smaller;">' . esc_html($errorMessage) . "</span>";
                }
            }
            wp_send_json_error(array('message' => $errorMessage));
        }
    );

    $client->run();
}