<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * The three spam outcomes: spam is deleted, suspected spam is sent back to the commenter with the API's suggestion
 * (like a comment-health revision, and combined with any health feedback), and anything else is published.
 */
final class SuspectedSpamTest extends TestCase {

    private const POST_ID = 42;

    private const LINK_SUGGESTION = 'This looks like spam. How about removing the link?';

    private const PROMOTION_SUGGESTION = 'This reads like promotion. How about saying what you think about the discussion?';


    protected function setUp(): void {
        WordPressStubState::reset();
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = [
            'assess_health' => true,
            'check_relevance' => false,
            'check_spam' => true,
            'check_dogwhistle' => false,
        ];
        WordPressStubState::$options[\Respectify\OPTION_SPAM_HANDLING] = \Respectify\ACTION_DELETE;
        WordPressStubState::$post_meta[self::POST_ID]['_respectify_article_id'] = 'test-article-context-id';
        $_POST = ['respectify_nonce' => 'valid-test-nonce'];
    }


    protected function tearDown(): void {
        $_POST = [];
    }


    public function testInterceptComment_Spam_IsDeleted(): void {
        $result = $this->intercept(self::megaCallResult(['is_spam' => true, 'confidence' => 0.97, 'reasoning' => '']));

        $this->assertInstanceOf(\WP_Error::class, $result, 'Spam should be refused');
        $this->assertSame(\Respectify\ACTION_DELETE, $result->get_error_code(), 'Spam should be deleted, not sent back');
        $this->assertStringContainsString('This looks like spam', $result->get_error_message());
    }


    public function testInterceptComment_SuspectedSpam_IsSentBackWithSuggestion(): void {
        $result = $this->intercept(self::megaCallResult([
            'is_spam' => false,
            'suspected_spam' => true,
            'suggestion' => self::LINK_SUGGESTION,
            'confidence' => 0.7,
            'reasoning' => '',
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result, 'Suspected spam should not be published');
        $this->assertSame(\Respectify\ACTION_REVISE, $result->get_error_code(), 'Suspected spam should be sent back, never deleted');
        $this->assertStringContainsString(self::LINK_SUGGESTION, $result->get_error_message(), 'The commenter should see the suggestion');
    }


    public function testInterceptComment_SuspectedSpamWhileSpamHandlingIsDelete_IsStillSentBack(): void {
        // The spam handling setting applies only to spam; a suspected comment is never deleted for being suspected
        WordPressStubState::$options[\Respectify\OPTION_SPAM_HANDLING] = \Respectify\ACTION_DELETE;

        $result = $this->intercept(self::megaCallResult([
            'is_spam' => false,
            'suspected_spam' => true,
            'suggestion' => self::PROMOTION_SUGGESTION,
            'confidence' => 0.6,
            'reasoning' => '',
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(\Respectify\ACTION_REVISE, $result->get_error_code());
        $this->assertSame([], WordPressStubState::$inserted_comments, 'A comment sent back is not stored');
    }


    public function testInterceptComment_SuspectedSpamAndHealthFeedback_ShowsBoth(): void {
        $result = $this->intercept(self::megaCallResult(
            [
                'is_spam' => false,
                'suspected_spam' => true,
                'suggestion' => self::LINK_SUGGESTION,
                'confidence' => 0.7,
                'reasoning' => '',
            ],
            [
                'exceeds_tolerance' => true,
                'toxicity_score' => 0.9,
                'toxicity_explanation' => 'The comment is hostile throughout.',
            ]
        ));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(\Respectify\ACTION_REVISE, $result->get_error_code());
        $message = $result->get_error_message();
        $this->assertStringContainsString(self::LINK_SUGGESTION, $message, 'The spam suggestion should be shown');
        $this->assertStringContainsString('may come across as hostile', $message, 'The comment-health feedback should be shown too');
    }


    public function testInterceptComment_SuspectedSpamWithSpamCheckOff_IsPublished(): void {
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS]['check_spam'] = false;

        $result = $this->intercept(self::megaCallResult([
            'is_spam' => false,
            'suspected_spam' => true,
            'suggestion' => self::LINK_SUGGESTION,
            'confidence' => 0.7,
            'reasoning' => '',
        ]));

        $this->assertIsArray($result, 'With spam checking off, a suspected comment is published');
        $this->assertSame(1, $result['comment_approved']);
    }


    public function testInterceptComment_NotSpam_IsPublished(): void {
        $result = $this->intercept(self::megaCallResult([
            'is_spam' => false,
            'suspected_spam' => false,
            'suggestion' => '',
            'confidence' => 0.1,
            'reasoning' => '',
        ]));

        $this->assertIsArray($result, 'A comment that is not spam should be published');
        $this->assertSame(1, $result['comment_approved']);
        $this->assertSame('respectify_approved', $result['comment_type']);
    }


    public function testInterceptComment_OlderServerWithoutSuspectedFields_IsPublished(): void {
        $result = $this->intercept(self::megaCallResult(['is_spam' => false, 'confidence' => 0.1, 'reasoning' => '']));

        $this->assertIsArray($result, 'An older server sends no suspected_spam; the comment is published');
        $this->assertSame(1, $result['comment_approved']);
    }


    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function intercept(MegaCallResult $mega_call_result): array|\WP_Error {
        $plugin = $this->createPlugin(new FakeRespectifyClient($mega_call_result));
        return $plugin->intercept_comment([
            'comment_post_ID' => self::POST_ID,
            'comment_content' => 'Great article, see my site for more.',
            'comment_parent' => 0,
        ]);
    }


    private function createPlugin(FakeRespectifyClient $client): RespectifyWordpressPlugin {
        // The real constructor registers WordPress hooks and builds a live API client; neither is wanted here.
        $plugin_class = new \ReflectionClass(RespectifyWordpressPlugin::class);
        $plugin = $plugin_class->newInstanceWithoutConstructor();
        $plugin_class->getProperty('respectify_client')->setValue($plugin, $client);
        $plugin_class->getProperty('version')->setValue($plugin, 'test');
        return $plugin;
    }


    /**
     * @param array<string, mixed> $spam_check
     * @param array<string, mixed> $comment_score_overrides
     */
    private static function megaCallResult(array $spam_check, array $comment_score_overrides = []): MegaCallResult {
        return new MegaCallResult([
            'spam_check' => $spam_check,
            'comment_score' => array_merge([
                'logical_fallacies' => [],
                'objectionable_phrases' => [],
                'negative_tone_phrases' => [],
                'appears_low_effort' => false,
                'overall_score' => 4,
                'toxicity_score' => 0.05,
                'toxicity_explanation' => '',
                'exceeds_tolerance' => false,
            ], $comment_score_overrides),
        ]);
    }
}
