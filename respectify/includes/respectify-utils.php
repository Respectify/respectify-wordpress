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

    if (!empty($base_url) && !empty($api_version)) {
        \Respectify\respectify_log('Creating client with base URL ' . $base_url . ' and API version ' . $api_version);
        return new \RespectifyScoper\Respectify\RespectifyClientAsync($email, $api_key, $base_url, floatval($api_version));
    } else {
        \Respectify\respectify_log('Creating client with default base URL and API version');
        return new \RespectifyScoper\Respectify\RespectifyClientAsync($email, $api_key);
    }
}

function get_friendly_message_which_client() {
    $email = get_option(\Respectify\OPTION_EMAIL, '');
    $api_key = \Respectify\respectify_get_decrypted_api_key();
    $base_url = get_option(\Respectify\OPTION_BASE_URL, '');
    $api_version = get_option(\Respectify\OPTION_API_VERSION, '');

    if (!empty($base_url) && !empty($api_version)) {
        return 'Connecting to URL ' . $base_url . ' and API version ' . $api_version;
    } else {
        return ''; // Default client, say nothing
    }
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

