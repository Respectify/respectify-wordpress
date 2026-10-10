<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\TerminatedRequest;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Exceptions\BadRequestException;
use RespectifyScoper\Respectify\Exceptions\JsonDecodingException;
use RespectifyScoper\Respectify\Exceptions\PaymentRequiredException;
use RespectifyScoper\Respectify\Exceptions\RespectifyException;
use RespectifyScoper\Respectify\Exceptions\ServerException;
use RespectifyScoper\Respectify\Exceptions\UnauthorizedException;
use RespectifyScoper\Respectify\Exceptions\UnsupportedMediaTypeException;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * Behavioural guard: when the Respectify API fails, the error the plugin reports to the site admin - the
 * transient behind the admin notice, the held comment's meta, and the notification email - must describe what
 * went wrong without repeating the exception's message. Library exception messages carry server error details
 * and raw response bodies, which can echo the comment, the commenter's details or the post content, and none of
 * that may be persisted to options, transients or meta, or emailed, as part of error reporting.
 *
 * Each test makes the API fail with an exception whose message carries unique marker strings, captures every
 * transient, option, meta value and email the plugin writes, and asserts that no marker appears while the
 * admin-facing report still says what went wrong.
 */
final class ApiErrorReportingPrivacyTest extends TestCase {

    private const POST_ID = 42;
    private const ADMIN_EMAIL = 'site-admin@example.test';

    private const MARKER_COMMENT_TEXT = 'ZqxCommentBodyMarker';
    private const MARKER_POST_CONTENT = 'ZqxPostContentMarker';
    private const MARKER_AUTHOR_NAME = 'ZqxAuthorNameMarker';
    private const MARKER_AUTHOR_EMAIL = 'zqx-author-email-marker@example.test';

    private const ALL_MARKERS = [
        'comment text' => self::MARKER_COMMENT_TEXT,
        'post content' => self::MARKER_POST_CONTENT,
        'author name' => self::MARKER_AUTHOR_NAME,
        'author email' => self::MARKER_AUTHOR_EMAIL,
    ];

    /** What a library exception message echoing the submitted comment looks like. */
    private const ECHOED_COMMENT = ': could not score "Author: ' . self::MARKER_AUTHOR_NAME
        . "\nAuthor email: " . self::MARKER_AUTHOR_EMAIL . "\n\n" . self::MARKER_COMMENT_TEXT . '"';

    private string|false $previous_error_log;


    protected function setUp(): void {
        WordPressStubState::reset();
        $_POST = [];
        WordPressStubState::$options['admin_email'] = self::ADMIN_EMAIL;
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = [
            'assess_health' => false,
            'check_relevance' => false,
            'check_spam' => true,
            'check_dogwhistle' => false,
        ];
        WordPressStubState::$post_fields[self::POST_ID]['post_content'] = 'Article body ' . self::MARKER_POST_CONTENT;

        // This test inspects stored and emailed data, not the log; keep any log output out of the test run.
        $this->previous_error_log = ini_set('error_log', '/dev/null');
    }


    protected function tearDown(): void {
        ini_set('error_log', $this->previous_error_log === false ? '' : $this->previous_error_log);
        $_POST = [];
    }


    /**
     * Each library failure, with a message that echoes the comment, and the phrase and status the admin-facing
     * report must contain instead.
     *
     * @return array<string, array{0: \Throwable, 1: string, 2: string}>
     */
    public static function apiFailures(): array {
        return [
            'rejected API key' => [
                new UnauthorizedException('Unauthorized' . self::ECHOED_COMMENT, 401),
                'rejected the email address or API key',
                'HTTP 401',
            ],
            'no credit' => [
                new PaymentRequiredException('Payment Required' . self::ECHOED_COMMENT, 402),
                'credit',
                'HTTP 402',
            ],
            'bad request' => [
                new BadRequestException('Bad Request' . self::ECHOED_COMMENT, 400),
                'rejected the request as invalid',
                'HTTP 400',
            ],
            'unsupported media type' => [
                new UnsupportedMediaTypeException('Unsupported Media Type' . self::ECHOED_COMMENT, 415),
                'request format',
                'HTTP 415',
            ],
            'server error' => [
                new ServerException('Server Error 500' . self::ECHOED_COMMENT, 500),
                'internal error',
                'HTTP 500',
            ],
            'unreadable response' => [
                new JsonDecodingException('Error decoding JSON response from response: ' . self::ECHOED_COMMENT),
                'could not read',
                'JsonDecodingException',
            ],
            'other API error' => [
                new RespectifyException('Error 429' . self::ECHOED_COMMENT, 429),
                'unexpected error',
                'HTTP 429',
            ],
            'connection failure' => [
                new \RuntimeException('Connection to tcp://api.example.test:443 failed' . self::ECHOED_COMMENT, 111),
                'could not get a response from Respectify',
                'RuntimeException',
            ],
        ];
    }


    #[DataProvider('apiFailures')]
    public function testAjaxSubmitComment_ApiErrorEchoesComment_ReportsErrorWithoutCommentData(
        \Throwable $api_error,
        string $expected_explanation,
        string $expected_status
    ): void {
        $plugin = $this->createPlugin(new FakeRespectifyClient($api_error));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'Comment should be held on API failure');
        $this->assertStoredAndEmailedDataHasNoMarkers();
        $this->assertErrorReportExplains($expected_explanation, $expected_status);
    }


    public function testPreprocessCommentNoJs_ApiErrorEchoesComment_ReportsErrorWithoutCommentData(): void {
        $echoing_error = new UnauthorizedException('Unauthorized' . self::ECHOED_COMMENT, 401);
        $plugin = $this->createPlugin(new FakeRespectifyClient($echoing_error));
        $_POST['respectify_nonce'] = 'valid-test-nonce';

        $termination = $this->runUntilTerminated(
            static fn () => $plugin->respectify_preprocess_comment_no_js(self::commentData())
        );

        $this->assertSame('wp_die', $termination->terminating_function, 'API failure should show the error page');
        $this->assertStoredAndEmailedDataHasNoMarkers();
        $this->assertErrorReportExplains('rejected the email address or API key', 'HTTP 401');
    }


    public function testAjaxSubmitComment_ArticleContextErrorEchoesPost_ReportsErrorWithoutPostData(): void {
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS]['assess_health'] = true;
        $echoing_error = new ServerException(
            'Server Error 500: could not create topic from "' . self::MARKER_POST_CONTENT . '"',
            500
        );
        $plugin = $this->createPlugin(new FakeRespectifyClient(self::cleanMegaCallResult(), $echoing_error));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'Comment should be held on API failure');
        $this->assertStoredAndEmailedDataHasNoMarkers();
        $this->assertErrorReportExplains('internal error', 'HTTP 500');
    }


    public function testAjaxSubmitComment_ApiReturnsNoResult_ReportsUnexpectedResult(): void {
        // The API call succeeds but yields something other than a MegaCallResult.
        $plugin = $this->createPlugin(new FakeRespectifyClient(null));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'Comment should be held on API failure');
        $this->assertStoredAndEmailedDataHasNoMarkers();
        $this->assertErrorReportExplains('incomplete or unexpected result', 'UnexpectedValueException');
    }


    public function testGetApiError_LegacyErrorHoldsRawMessage_NeitherReturnsNorKeepsIt(): void {
        // Earlier plugin versions stored the raw exception message, which could echo the comment.
        WordPressStubState::$transients['respectify_api_error'] = [
            'message' => 'Server Error 500' . self::ECHOED_COMMENT,
            'timestamp' => 1767225600,
            'formatted_time' => '2026-01-01 00:00:00',
        ];

        $error = \Respectify\respectify_get_api_error();

        $this->assertIsArray($error, 'A stored error should still be reported to the admin');
        $this->assertStringContainsString('could not process a comment', $error['message'], 'Admin notice should still say something went wrong');
        $this->assertSame('2026-01-01 00:00:00', $error['formatted_time'], 'Admin notice should still say when');
        $this->assertStoredAndEmailedDataHasNoMarkers();
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
            'author' => self::MARKER_AUTHOR_NAME,
            'email' => self::MARKER_AUTHOR_EMAIL,
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
            'comment_parent' => 0,
            'comment_author' => self::MARKER_AUTHOR_NAME,
            'comment_author_email' => self::MARKER_AUTHOR_EMAIL,
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


    /**
     * Everything the plugin persisted or sent while reporting the error. The held comment itself legitimately
     * stores the comment and commenter, so only its meta - the error report attached to it - is included.
     *
     * @return array<string, mixed>
     */
    private static function storedAndEmailedData(): array {
        $held_comment_meta = array_map(
            static fn (array $inserted_comment): mixed => $inserted_comment['comment_meta'] ?? null,
            WordPressStubState::$inserted_comments
        );
        return [
            'transients' => WordPressStubState::$transients,
            'options' => WordPressStubState::$options,
            'post meta' => WordPressStubState::$post_meta,
            'comment meta' => WordPressStubState::$comment_meta,
            'held comment meta' => $held_comment_meta,
            'emails' => WordPressStubState::$sent_mail,
        ];
    }


    private function assertStoredAndEmailedDataHasNoMarkers(): void {
        $leaked = [];
        foreach (self::storedAndEmailedData() as $destination => $data) {
            $serialised = print_r($data, true);
            foreach (self::ALL_MARKERS as $marker_kind => $marker) {
                if (str_contains($serialised, $marker)) {
                    $leaked[] = "{$marker_kind} in {$destination}";
                }
            }
        }
        $this->assertSame(
            [],
            $leaked,
            "Private data was stored or emailed as part of error reporting:\n" . print_r(self::storedAndEmailedData(), true)
        );
    }


    private function assertErrorReportExplains(string $expected_explanation, string $expected_status): void {
        $stored_error = WordPressStubState::$transients['respectify_api_error'] ?? null;
        $this->assertIsArray($stored_error, 'The error should be stored for the admin notice');
        $this->assertIsString($stored_error['message'] ?? null, 'The stored error should have a message for the admin notice');
        $this->assertStringContainsString($expected_explanation, $stored_error['message'], 'Admin notice should explain the failure');
        $this->assertStringContainsString($expected_status, $stored_error['message'], 'Admin notice should identify the failure');

        $this->assertCount(1, WordPressStubState::$sent_mail, 'The admin should be emailed about the failure');
        $email = WordPressStubState::$sent_mail[0];
        $this->assertSame(self::ADMIN_EMAIL, $email['to'], 'The notification should go to the site admin');
        $this->assertStringContainsString($expected_explanation, $email['message'], 'Email should explain the failure');
        $this->assertStringContainsString($expected_status, $email['message'], 'Email should identify the failure');

        $this->assertCount(1, WordPressStubState::$inserted_comments, 'The comment should be held for moderation');
        $held_comment = WordPressStubState::$inserted_comments[0];
        $this->assertSame(0, $held_comment['comment_approved'], 'The held comment should await moderation');
        $this->assertIsString($held_comment['comment_meta']['respectify_error'] ?? null, 'The held comment should record the error');
        $this->assertStringContainsString(
            $expected_explanation,
            $held_comment['comment_meta']['respectify_error'],
            'The held comment should record what went wrong'
        );
    }
}
