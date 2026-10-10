<?php
/**
 * Minimal stand-ins for the WordPress functions and classes the comment-handling path calls.
 *
 * They model only what the plugin relies on. Request-ending functions throw TerminatedRequest so a test can
 * inspect the outcome. State lives in WordPressStubState and is reset per test.
 */

use Respectify\Tests\Support\TerminatedRequest;
use Respectify\Tests\Support\WordPressStubState;

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}


class WP_Error {

    /** @var array<string, array<int, string>> Mirrors WordPress's public errors property. */
    public array $errors = [];

    /** @var array<string, mixed> */
    public array $error_data = [];


    public function __construct(string $code = '', string $message = '', mixed $data = '') {
        if ($code !== '') {
            $this->errors[$code][] = $message;
            if ($data !== '') {
                $this->error_data[$code] = $data;
            }
        }
    }


    public function get_error_code(): string {
        $codes = array_keys($this->errors);
        return $codes[0] ?? '';
    }


    public function get_error_message(): string {
        $code = $this->get_error_code();
        return $this->errors[$code][0] ?? '';
    }


    public function get_error_data(): mixed {
        return $this->error_data[$this->get_error_code()] ?? null;
    }
}


function is_wp_error(mixed $thing): bool {
    return $thing instanceof WP_Error;
}


function get_option(string $option, mixed $default_value = false): mixed {
    return array_key_exists($option, WordPressStubState::$options) ? WordPressStubState::$options[$option] : $default_value;
}


function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed {
    return WordPressStubState::$post_meta[$post_id][$key] ?? '';
}


function update_post_meta(int $post_id, string $meta_key, mixed $meta_value): bool {
    WordPressStubState::$post_meta[$post_id][$meta_key] = $meta_value;
    return true;
}


function delete_post_meta(int $post_id, string $meta_key): bool {
    unset(WordPressStubState::$post_meta[$post_id][$meta_key]);
    return true;
}


function get_post_field(string $field, int $post_id): string {
    return WordPressStubState::$post_fields[$post_id][$field] ?? '';
}


function get_comment(int $comment_id): ?object {
    return WordPressStubState::$comments[$comment_id] ?? null;
}


/**
 * @param array<string, mixed> $commentdata
 */
function wp_insert_comment(array $commentdata): int {
    WordPressStubState::$inserted_comments[] = $commentdata;
    return count(WordPressStubState::$inserted_comments);
}


function set_transient(string $transient, mixed $value, int $expiration = 0): bool {
    WordPressStubState::$transients[$transient] = $value;
    return true;
}


function get_transient(string $transient): mixed {
    return WordPressStubState::$transients[$transient] ?? false;
}


function delete_transient(string $transient): bool {
    unset(WordPressStubState::$transients[$transient]);
    return true;
}


function wp_doing_ajax(): bool {
    return WordPressStubState::$is_doing_ajax;
}


function wp_verify_nonce(string $nonce, string $action): bool {
    return $nonce === 'valid-test-nonce' && $action === 'respectify_submit_comment';
}


function wp_send_json_success(mixed $data = null): never {
    throw new TerminatedRequest('wp_send_json_success', $data);
}


function wp_send_json_error(mixed $data = null): never {
    throw new TerminatedRequest('wp_send_json_error', $data);
}


function wp_die(mixed $message = '', mixed $title = '', mixed $args = []): never {
    throw new TerminatedRequest('wp_die', $message);
}


function wp_mail(string $to, string $subject, string $message): bool {
    return true;
}


function current_time(string $type): string|int {
    return $type === 'mysql' ? '2026-01-01 00:00:00' : 1767225600;
}


function wp_json_encode(mixed $data): string|false {
    return json_encode($data);
}


function wp_salt(string $scheme = 'auth'): string {
    return 'test-salt';
}


function wp_unslash(mixed $value): mixed {
    return is_string($value) ? stripslashes($value) : $value;
}


function sanitize_text_field(string $str): string {
    return trim(strip_tags($str));
}


function sanitize_textarea_field(string $str): string {
    return trim(strip_tags($str));
}


function sanitize_email(string $email): string {
    return trim($email);
}


function esc_url_raw(string $url): string {
    return $url;
}


function esc_url(string $url): string {
    return $url;
}


function esc_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}


function __(string $text, string $domain = 'default'): string {
    return $text;
}


function esc_html__(string $text, string $domain = 'default'): string {
    return esc_html($text);
}


function absint(mixed $maybeint): int {
    return abs((int) $maybeint);
}


function get_current_user_id(): int {
    return 0;
}


function plugin_dir_path(string $file): string {
    return rtrim(dirname($file), '/') . '/';
}


function plugin_dir_url(string $file): string {
    return 'https://blog.example.test/wp-content/plugins/respectify/';
}


function plugins_url(string $path = '', string $plugin = ''): string {
    return 'https://blog.example.test/wp-content/plugins/respectify/' . ltrim($path, '/');
}


function admin_url(string $path = ''): string {
    return 'https://blog.example.test/wp-admin/' . ltrim($path, '/');
}


function get_bloginfo(string $show = ''): string {
    return 'Test Blog';
}


function get_page_by_path(string $page_path): ?object {
    return null;
}


/**
 * @param array<string, mixed> $args
 * @return array<int, object>
 */
function get_pages(array $args = []): array {
    return [];
}


function get_permalink(mixed $post = 0): string|false {
    return false;
}
