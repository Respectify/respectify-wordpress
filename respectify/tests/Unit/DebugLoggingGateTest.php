<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\TerminatedRequest;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * respectify_log() writes to the PHP error log only when WP_DEBUG is true, following the WordPress convention
 * for debug output. A production site, where WP_DEBUG is off, gets no Respectify log lines for each comment.
 *
 * WP_DEBUG is a constant, so it cannot be changed once defined: each test runs in its own PHP process and
 * defines it (or leaves it undefined) for itself.
 */
final class DebugLoggingGateTest extends TestCase {

    private const POST_ID = 42;

    private string $captured_log_path;
    private string|false $previous_error_log;


    protected function setUp(): void {
        WordPressStubState::reset();
        $_POST = [];
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = [
            'assess_health' => false,
            'check_relevance' => false,
            'check_spam' => true,
            'check_dogwhistle' => false,
        ];

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
    }


    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRespectifyLog_WpDebugUndefined_LogsNothing(): void {
        $this->assertFalse(defined('WP_DEBUG'), 'This test needs WP_DEBUG left undefined, as on a default install');

        \Respectify\respectify_log('Respectify test message');

        $this->assertSame('', $this->capturedLog(), 'Nothing should be logged without WP_DEBUG');
    }


    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRespectifyLog_WpDebugFalse_LogsNothing(): void {
        define('WP_DEBUG', false);

        \Respectify\respectify_log('Respectify test message');

        $this->assertSame('', $this->capturedLog(), 'Nothing should be logged with WP_DEBUG false');
    }


    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRespectifyLog_WpDebugTrue_LogsMessage(): void {
        define('WP_DEBUG', true);

        \Respectify\respectify_log('Respectify test message');

        $this->assertStringContainsString('Respectify test message', $this->capturedLog(), 'The message should be logged with WP_DEBUG true');
    }


    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAjaxSubmitComment_WpDebugFalse_LogsNothing(): void {
        define('WP_DEBUG', false);
        $plugin = $this->createPlugin(new FakeRespectifyClient(self::cleanMegaCallResult()));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_success', $termination->terminating_function, 'Clean comment should be posted');
        $this->assertSame('', $this->capturedLog(), 'Processing a comment should log nothing with WP_DEBUG false');
    }


    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAjaxSubmitComment_WpDebugTrue_LogsProcessing(): void {
        define('WP_DEBUG', true);
        $plugin = $this->createPlugin(new FakeRespectifyClient(self::cleanMegaCallResult()));
        $this->submitAjaxComment();

        $termination = $this->runUntilTerminated(static fn () => $plugin->ajax_submit_comment());

        $this->assertSame('wp_send_json_success', $termination->terminating_function, 'Clean comment should be posted');
        $this->assertStringContainsString('Processing comment', $this->capturedLog(), 'Processing a comment should be logged with WP_DEBUG true');
    }


    private function capturedLog(): string {
        return (string) file_get_contents($this->captured_log_path);
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
            'comment_content' => 'A perfectly ordinary comment.',
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
}
