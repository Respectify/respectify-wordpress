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
        'Respectify Settings',
        'Respectify',
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
        'API Credentials',
        null,
        'respectify'
    );

    add_settings_field(
        \Respectify\OPTION_EMAIL,
        'Email',
        'respectify_email_callback',
        'respectify',
        'respectify_settings_section'
    );

    add_settings_field(
        'respectify_api_key',
        'API Key',
        'respectify_api_key_callback',
        'respectify',
        'respectify_settings_section'
    );

    add_settings_section(
        'respectify_behavior_settings_section',
        'Comment Handling',
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
        'How to Handle Spam',
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
        'Revise a Comment When',
        'respectify_revise_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    // New Settings Section for Advanced Parameters
    add_settings_section(
        'respectify_advanced_settings_section',
        'Rarely Used Settings',
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
        'Base URL',
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
        'API Version',
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
    echo '<p class="description description-match-font-size">Configure how Respectify handles comments, especially the criteria for requesting a comment be revised before being posted.</p>';
    echo '<div class="respectify-settings-row">';
}

// Callback to render spam handling field
function respectify_spam_handling_callback() {
    $options = get_option(\Respectify\OPTION_SPAM_HANDLING, \Respectify\ACTION_DELETE);
    ?>
    <select name="respectify_spam_handling" id="respectify_spam_handling">
        <option value="trash" <?php selected($options, \Respectify\ACTION_DELETE); ?>>Delete</option>
        <option value="reject_with_feedback" <?php selected($options, \Respectify\ACTION_REVISE); ?>>Give Opportunity to Revise</option>
    </select>
    <p class="description">By default spam is deleted, but you can treat them as normal comments and send them back for revision.</p>
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
            <label for="respectify_revise_min_score">Minimum Score:</label>
            <span class="emoji">😧</span>
            <input type="range" id="respectify_revise_min_score" name="respectify_revise_settings[min_score]" value="<?php echo esc_attr($options['min_score']); ?>" min="1" max="5" step="1">
            <span class="emoji">🤩</span>
            <span class="out-of description"><span id="revise_min_score_value"><?php echo esc_html($options['min_score']); ?></span> out of 5.</span>
            <span class="out-of description"><br/>Recommended value: 3 out of 5.<br/>A score of 3 out of 5 is a normal, good quality comment. 4 and 5 are outstanding and unusual.</span>
        </div>
        <div class="respectify-checkbox-group">
            <label>
                <input type="checkbox" name="respectify_revise_settings[low_effort]" value="1" <?php checked($options['low_effort'], true); ?> />
                Seems Low Effort
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[logical_fallacies]" value="1" <?php checked($options['logical_fallacies'], true); ?> />
                Contains Logical Fallacies
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[objectionable_phrases]" value="1" <?php checked($options['objectionable_phrases'], true); ?> />
                Contains Objectionable Phrases
            </label>
            <label>
                <input type="checkbox" name="respectify_revise_settings[negative_tone]" value="1" <?php checked($options['negative_tone'], true); ?> />
                Negative Tone Indications
            </label>
        </div>
    </div>
    <p class="description">Define the conditions under which a comment should be revised.</p>
    <?php
}

// Callback to render email input
function respectify_email_callback() {
    $email = get_option(\Respectify\OPTION_EMAIL, '');
    echo '<input type="email" name="respectify_email" value="' . esc_attr($email) . '" class="regular-text" required />';
    echo '<p class="description">Enter the email associated with your Respectify account.</p>';
}

// Callback to render API key input
function respectify_api_key_callback() {
    $api_key = respectify_get_decrypted_api_key();
    echo '<input type="password" name="respectify_api_key" value="' . esc_attr($api_key) . '" class="regular-text" required />';
    echo '<p class="description">Enter the Respectify API key you wish to use for this Wordpress site.</p>';

    ?>
    <div class="respectify-test-container">
        <button type="button" id="respectify-test-button" class="button">Test</button>
        <span id="respectify-test-result"></span>   
    </div>
    <p>Click 'Test' to verify the email and API key are working correctly.</p>
    <?php
}

// Callback to render advanced section
function respectify_advanced_section_callback() {
    echo '<button type="button" class="respectify-accordion" id="respectify-advanced-settings-button">Display rarely used settings</button>';
    echo '<div class="respectify-panel">';
    echo '<p class="description">Configure a custom Respectify server.</p>';
}

// Callback to render base URL input
function respectify_base_url_callback() {
    $base_url = get_option(\Respectify\OPTION_BASE_URL, '');
    echo '<input type="text" name="respectify_base_url" value="' . esc_attr($base_url) . '" class="regular-text" />';
    echo '<p class="description">Enter the server URL for the Respectify API.<br/>An example is "app.respectify.org". Leave blank for the normal Respectify service.</p>';
}

// Callback to render API version input
function respectify_api_version_callback() {
    $api_version = get_option(\Respectify\OPTION_API_VERSION, '');
    echo '<input type="text" name="respectify_api_version" value="' . esc_attr($api_version) . '" class="regular-text" />';
    echo '<p class="description">Enter the API version for the Respectify API.<br/>An example is "1.0". Leave blank for the normal Respectify service.</p>';
}

// Encrypt API key before saving
add_filter('pre_update_option_respectify_api_key_encrypted', 'respectify_encrypt_api_key', 10, 2);
function respectify_encrypt_api_key($new_value, $old_value) {
    respectify_verify_nonce(); // wp_die-is if not valid

    if (!empty($_POST['respectify_api_key'])) {
        $api_key = sanitize_text_field(wp_unslash($_POST['respectify_api_key']));
        return respectify_encrypt($api_key);
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
        <h1>Respectify Settings</h1>
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

require_once plugin_dir_path( __FILE__ ) . '../includes/class-respectify-wordpress-plugin.php';

// Handle AJAX request for testing credentials
add_action('wp_ajax_respectify_test_credentials', 'respectify_test_credentials');
function respectify_test_credentials() {
    check_ajax_referer('respectify_test_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '❌ Unauthorized. Please check your permissions and try again.'));
    }

    // Get the email and API key from the AJAX request, if provided
    // Note this is NOT from the settings, because they may not be saved yet
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : get_option(\Respectify\OPTION_EMAIL, '');
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : respectify_decrypt(get_option(\Respectify\OPTION_API_KEY_ENCRYPTED, ''));

    // Ensure the class is loaded correctly
    if (!class_exists('RespectifyScoper\Respectify\RespectifyClientAsync')) {
        wp_send_json_error(array('message' => 'Class not found.'));
    }

    // Instantiate your API client and test credentials
    $client = new \RespectifyScoper\Respectify\RespectifyClientAsync($email, $api_key);
    $promise = $client->checkUserCredentials();

    $promise->then(
        function ($result) {
            list($success, $info) = $result;
            if ($success) {
                wp_send_json_success(array('message' => "✅ Authorization successful - click Save Changes, and then you're good to go!"));
            } else {
                wp_send_json_error(array('message' => '⚠️ ' . $info));
            }
        },
        function ($ex) {
            $unauth_message = '⛔️ Unauthorized. This means there was an error with the email and/or API key. Please check them and try again.';

            $errorMessage = 'Error (' . get_class($ex) . '): ' . $ex->getMessage();
            if ($ex->getCode() === 401) {
                $errorMessage = $unauth_message;
            }
            wp_send_json_error(array('message' => $errorMessage));
        }
    );

    $client->run();
}