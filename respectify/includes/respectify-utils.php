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

    $params = [
        'email' => $email,
        'apiKey' => $api_key,
        'baseUrl' => !empty($base_url) ? $base_url : null,
        'version' => !empty($api_version) ? floatval($api_version) : null
    ];

    \Respectify\respectify_log('Parameters being passed to constructor: ' . print_r($params, true));
    return new \RespectifyScoper\Respectify\RespectifyClientAsync(...array_values($params));
}

function respectify_create_test_client($email, $api_key, $base_url, $api_version) {
    $params = [
        'email' => $email,
        'apiKey' => $api_key,
        'baseUrl' => !empty($base_url) ? $base_url : null,
        'version' => !empty($api_version) ? floatval($api_version) : null
    ];

    respectify_log('Parameters being passed to test constructor: ' . print_r($params, true));
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

