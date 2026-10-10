<?php

namespace Respectify\Tests\Support;

/**
 * In-memory state behind the WordPress function stubs. Reset before every test so tests share nothing.
 */
final class WordPressStubState {

    /** @var array<string, mixed> Options keyed by option name, read by get_option(). */
    public static array $options = [];

    /** @var array<int, array<string, mixed>> Post meta keyed by post ID then meta key. */
    public static array $post_meta = [];

    /** @var array<int, array<string, string>> Post fields keyed by post ID then field name. */
    public static array $post_fields = [];

    /** @var array<int, object> Existing comments keyed by comment ID, returned by get_comment(). */
    public static array $comments = [];

    /** @var array<int, array<string, mixed>> Comments passed to wp_insert_comment(), in order. */
    public static array $inserted_comments = [];

    /** @var array<string, mixed> Transients keyed by name. */
    public static array $transients = [];

    /** @var array<int, array<string, mixed>> Comment meta keyed by comment ID then meta key. */
    public static array $comment_meta = [];

    /** @var array<int, array{to: string, subject: string, message: string}> Mail passed to wp_mail(), in order. */
    public static array $sent_mail = [];

    /** Whether wp_doing_ajax() reports an AJAX request. */
    public static bool $is_doing_ajax = false;


    public static function reset(): void {
        self::$options = [];
        self::$post_meta = [];
        self::$post_fields = [];
        self::$comments = [];
        self::$inserted_comments = [];
        self::$transients = [];
        self::$comment_meta = [];
        self::$sent_mail = [];
        self::$is_doing_ajax = false;
    }
}
