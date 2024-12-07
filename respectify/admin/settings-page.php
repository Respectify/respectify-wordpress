<?php
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\Exceptions\RespectifyException;
use RespectifyScoper\Respectify\Exceptions\UnauthorizedException;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

// Encryption function
function respectify_encrypt($data) {
    $encryption_key = wp_salt('auth');
    // This prepends random bytes to the encrypted data to create the IV (initialization vector)
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
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

// Enqueue admin scripts
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