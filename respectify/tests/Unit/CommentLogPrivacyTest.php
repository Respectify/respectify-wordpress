<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\TerminatedRequest;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Exceptions\ServerException;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * Behavioural guard: running a comment through the plugin must never write the comment's text, the parent
 * comment's text, the post's content, or the commenter's name, email, IP, URL or user agent to the PHP error
 * log. Each test submits data carrying unique marker strings, captures everything error_log() writes, and
 * asserts that no marker appears.
 */
final class CommentLogPrivacyTest extends TestCase {

    private const POST_ID = 42;
    private const PARENT_COMMENT_ID = 7;

    private const MARKER_COMMENT_TEXT = 'ZqxCommentBodyMarker';
    private const MARKER_PARENT_TEXT = 'ZqxParentBodyMarker';
    private const MARKER_POST_CONTENT = 'ZqxPostContentMarker';
    private const MARKER_AUTHOR_NAME = 'ZqxAuthorNameMarker';
    private const MARKER_AUTHOR_EMAIL = 'zqx-author-email-marker@example.test';
    private const MARKER_AUTHOR_URL = 'https://zqx-author-url-marker.example.test';
    private const MARKER_AUTHOR_IP = '203.0.113.177';
    private const MARKER_USER_AGENT = 'ZqxUserAgentMarker/1.0';

    private const ALL_MARKERS = [
        'comment text' => self::MARKER_COMMENT_TEXT,
        'parent comment text' => self::MARKER_PARENT_TEXT,
        'post content' => self::MARKER_POST_CONTENT,
        'author name' => self::MARKER_AUTHOR_NAME,
        'author email' => self::MARKER_AUTHOR_EMAIL,
        'author URL' => self::MARKER_AUTHOR_URL,
        'author IP' => self::MARKER_AUTHOR_IP,
        'user agent' => self::MARKER_USER_AGENT,
    ];

    private string $captured_log_path;
    private string|false $previous_error_log;


    protected function setUp(): void {
        WordPressStubState::reset();
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = self::MARKER_AUTHOR_IP;
        $_SERVER['HTTP_USER_AGENT'] = self::MARKER_USER_AGENT;

        // Only spam checking: no article context is needed unless a test turns it on.
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = [
            'assess_health' => false,
            'check_relevance' => false,
            'check_spam' => true,
            'check_dogwhistle' => false,
        ];

        WordPressStubState::$comments[self::PARENT_COMMENT_ID] = (object) [
            'comment_ID' => self::PARENT_COMMENT_ID,
            'comment_post_ID' => self::POST_ID,
            'comment_content' => 'Earlier remark ' . self::MARKER_PARENT_TEXT,
        ];
        WordPressStubState::$post_fields[self::POST_ID]['post_content'] = 'Article body ' . self::MARKER_POST_CONTENT;

        $captured_log_path = tempnam(sys_get_temp_dir(), 'respectify-log-');
        $this->assertNotFalse($captured_log_path, 'Should create a temp file to capture the error log');
        $this->captured_log_path = $captured_log_path;
        $this->previous_error_log = ini_set('error_log', $this->captured_log_path);
    }


    protected function tearDown(): void {
        ini_set('error_log', $this->previous_error_log === false ? '' : $this->previous_error_log);
        if (is_file($this->captured_log_path)) {
            unlink($this->captured_log_path);
        }
        $_POST = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }


    public function testAjaxSubmitComment_PublishedReply_LogsNoCommentOrCommenterData(): void {
        $plugin = $this->createPlugin(new FakeRespectifyClient(self::cleanMegaCallResult()));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_success', $termination->terminating_function, 'Clean comment should be posted');
        $this->assertCapturedLogHasNoMarkers();
    }


    public function testAjaxSubmitComment_SpamFeedbackQuotesComment_LogsNoCommentOrCommenterData(): void {
        // Feedback is built from the API's reasoning, which can quote the comment back.
        $spam_result = new MegaCallResult([
            'spam_check' => [
                'is_spam' => true,
                'confidence' => 0.99,
                'reasoning' => 'The phrase "' . self::MARKER_COMMENT_TEXT . '" from ' . self::MARKER_AUTHOR_EMAIL . ' is promotional.',
            ],
        ]);
        $plugin = $this->createPlugin(new FakeRespectifyClient($spam_result));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'Spam comment should be refused');
        $this->assertCapturedLogHasNoMarkers();
    }


    public function testPreprocessCommentNoJs_PublishedReply_LogsNoCommentOrCommenterData(): void {
        $plugin = $this->createPlugin(new FakeRespectifyClient(self::cleanMegaCallResult()));
        $_POST['respectify_nonce'] = 'valid-test-nonce';

        $result = $plugin->respectify_preprocess_comment_no_js(self::commentData());

        $this->assertIsArray($result, 'Clean comment should be passed on to WordPress');
        $this->assertCapturedLogHasNoMarkers();
    }


    public function testPreprocessCommentNoJs_ApiErrorEchoesComment_LogsNoCommentOrCommenterData(): void {
        // Library exception messages carry server error details and raw response bodies, which can echo the
        // submitted comment (including the author lines the plugin prepends to it).
        $echoing_error = new ServerException(
            'Server Error 500: could not score "Author: ' . self::MARKER_AUTHOR_NAME . ' ' . self::MARKER_COMMENT_TEXT . '"',
            500
        );
        $plugin = $this->createPlugin(new FakeRespectifyClient($echoing_error));
        $_POST['respectify_nonce'] = 'valid-test-nonce';

        $termination = $this->runUntilTerminated(
            static fn () => $plugin->respectify_preprocess_comment_no_js(self::commentData())
        );

        $this->assertSame('wp_die', $termination->terminating_function, 'API failure should show the error page');
        $this->assertCapturedLogHasNoMarkers();
    }


    public function testAjaxSubmitComment_ArticleContextErrorEchoesPost_LogsNoPostOrCommentData(): void {
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS]['assess_health'] = true;
        $echoing_error = new ServerException(
            'Server Error 500: could not create topic from "' . self::MARKER_POST_CONTENT . '"',
            500
        );
        $plugin = $this->createPlugin(new FakeRespectifyClient(self::cleanMegaCallResult(), $echoing_error));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'Comment should be held on API failure');
        $this->assertCapturedLogHasNoMarkers();
    }


    private function createPlugin(FakeRespectifyClient $client): RespectifyWordpressPlugin {
        // The real constructor registers WordPress hooks and builds a live API client; neither is wanted here.
        $plugin_class = new \ReflectionClass(RespectifyWordpressPlugin::class);
        $plugin = $plugin_class->newInstanceWithoutConstructor();
        $plugin_class->getProperty('respectify_client')->setValue($plugin, $client);
        $plugin_class->getProperty('version')->setValue($plugin, 'test');
        return $plugin;
    }


    private function submitAjaxComment(): void {
        WordPressStubState::$is_doing_ajax = true;
        $_POST = [
            'respectify_nonce' => 'valid-test-nonce',
            'comment_post_ID' => (string) self::POST_ID,
            'comment_parent' => (string) self::PARENT_COMMENT_ID,
            'author' => self::MARKER_AUTHOR_NAME,
            'email' => self::MARKER_AUTHOR_EMAIL,
            'url' => self::MARKER_AUTHOR_URL,
            'comment_content' => 'I think ' . self::MARKER_COMMENT_TEXT . ' is a fair point.',
        ];
    }


    /**
     * The comment array WordPress hands to the preprocess_comment filter.
     *
     * @return array<string, mixed>
     */
    private static function commentData(): array {
        return [
            'comment_post_ID' => self::POST_ID,
            'comment_parent' => self::PARENT_COMMENT_ID,
            'comment_author' => self::MARKER_AUTHOR_NAME,
            'comment_author_email' => self::MARKER_AUTHOR_EMAIL,
            'comment_author_url' => self::MARKER_AUTHOR_URL,
            'comment_author_IP' => self::MARKER_AUTHOR_IP,
            'comment_agent' => self::MARKER_USER_AGENT,
            'comment_content' => 'I think ' . self::MARKER_COMMENT_TEXT . ' is a fair point.',
            'comment_type' => '',
            'user_id' => 0,
        ];
    }


    private static function cleanMegaCallResult(): MegaCallResult {
        return new MegaCallResult([
            'spam_check' => ['is_spam' => false, 'confidence' => 0.01, 'reasoning' => ''],
        ]);
    }


    /**
     * @param callable(): mixed $request_handler
     */
    private function runUntilTerminated(callable $request_handler): TerminatedRequest {
        try {
            $request_handler();
        } catch (TerminatedRequest $termination) {
            return $termination;
        }
        $this->fail('Request handler should have ended the request');
    }


    private function assertCapturedLogHasNoMarkers(): void {
        $captured_log = (string) file_get_contents($this->captured_log_path);

        // Guards against a vacuous pass: the comment path always logs this, so its absence means capture failed.
        $this->assertStringContainsString('Processing comment', $captured_log, 'Error log capture should be working');

        $leaked = [];
        foreach (self::ALL_MARKERS as $marker_kind => $marker) {
            if (str_contains($captured_log, $marker)) {
                $leaked[] = $marker_kind;
            }
        }
        $this->assertSame(
            [],
            $leaked,
            "Private data reached the error log. Captured log:\n" . $captured_log
        );
    }
}
