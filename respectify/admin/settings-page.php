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
    register_setting('respectify_options_group', 'respectify_email');
    register_setting('respectify_options_group', 'respectify_api_key_encrypted');

    add_settings_section(
        'respectify_settings_section',
        'API Credentials',
        null,
        'respectify'
    );

    add_settings_field(
        'respectify_email',
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

    // New Settings Section
    add_settings_section(
        'respectify_behavior_settings_section',
        'Comment Handling',
        'respectify_behavior_section_callback',
        'respectify'
    );

    // How to handle spam
    register_setting('respectify_options_group', 'respectify_spam_handling');
    add_settings_field(
        'respectify_spam_handling',
        'How to Handle Spam',
        'respectify_spam_handling_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );

    // Revise When Settings
    register_setting('respectify_options_group', 'respectify_revise_settings');
    add_settings_field(
        'respectify_revise_settings',
        'Revise a Comment When',
        'respectify_revise_settings_callback',
        'respectify',
        'respectify_behavior_settings_section'
    );
}

// Callback to render behavior section
function respectify_behavior_section_callback() {
    echo '<p>Configure how Respectify handles comments, especially the criteria for requesting a comment be revised before being posted.</p>';
    echo '<div class="respectify-settings-row">';
}

// Callback to render spam handling field
function respectify_spam_handling_callback() {
    $options = get_option('respectify_spam_handling', 'trash');
    ?>
    <select name="respectify_spam_handling" id="respectify_spam_handling">
        <option value="trash" <?php selected($options, 'trash'); ?>>Delete</option>
        <option value="reject_with_feedback" <?php selected($options, 'reject_with_feedback'); ?>>Give Opportunity to Revise</option>
    </select>
    <p class="description">By default spam is deleted, but you can treat them as normal comments and send them back for revision.</p>
    <?php
}

// Callback to render revise settings field
function respectify_revise_settings_callback() {
    $options = get_option('respectify_revise_settings', array(
        'min_score'             => 3,
        'low_effort'            => true,
        'logical_fallacies'     => true,
        'objectionable_phrases' => true,
        'negative_tone'         => true,
    ));
    ?>
    <div class="respectify-settings-column">
        <label for="respectify_revise_min_score">Minimum Score: <span id="revise_min_score_value"><?php echo esc_html($options['min_score']); ?></span> out of 5.</label>
        👎
        <input type="range" id="respectify_revise_min_score" name="respectify_revise_settings[min_score]" value="<?php echo esc_attr($options['min_score']); ?>" min="1" max="5" step="1" oninput="document.getElementById('revise_min_score_value').innerText = this.value;">
        🤩
        <br/>
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
    $email = get_option('respectify_email', '');
    echo '<input type="email" name="respectify_email" value="' . esc_attr($email) . '" class="regular-text" required />';
    echo '<p class="description">Enter the email associated with your Respectify account.</p>';
}

// Callback to render API key input
function respectify_api_key_callback() {
    $api_key = respectify_get_decrypted_api_key();
    echo '<input type="password" name="respectify_api_key" value="' . esc_attr($api_key) . '" class="regular-text" required />';
    echo '<p class="description">Enter the Respectify API key you wish to use for this Wordpress site.</p>';
}

// Encrypt API key before saving
add_filter('pre_update_option_respectify_api_key_encrypted', 'respectify_encrypt_api_key', 10, 2);
function respectify_encrypt_api_key($new_value, $old_value) {
    if (!empty($_POST['respectify_api_key'])) {
        $api_key = sanitize_text_field($_POST['respectify_api_key']);
        return respectify_encrypt($api_key);
    }
    return $old_value;
}

// Render the settings page
function respectify_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Respectify Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('respectify_options_group');
            do_settings_sections('respectify');
            ?>
            <div class="respectify-test-container">
                <button type="button" id="respectify-test-button" class="button">Test</button>
                <span id="respectify-test-result"></span>   
            </div>
            <p>Click 'Test' to verify the email and API key are working correctly.</p>
            <?php
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
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : get_option('respectify_email', '');
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : respectify_decrypt(get_option('respectify_api_key_encrypted', ''));

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
                wp_send_json_success(array('message' => "✅ Authorization successful - click Save, and then you're good to go!"));
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