<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * Undesired topics: in "Any mention at all" mode any mention is acted on and explained; in threshold mode only a
 * comment at or over the threshold is. The decision and the feedback follow the same rule.
 */
final class UndesiredTopicsTest extends TestCase {

    private const POST_ID = 42;

    private const UNDESIRED_TOPICS_EXPLANATION = 'Your comment touches on topics that the site owner prefers not to have discussed here.';

    private const UNDESIRED_TOPICS_REASONING = 'The comment briefly mentions US politics after 1970.';


    protected function setUp(): void {
        WordPressStubState::reset();
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = [
            'assess_health' => false,
            'check_relevance' => true,
            'check_spam' => false,
            'check_dogwhistle' => false,
        ];
        WordPressStubState::$options[\Respectify\OPTION_BANNED_TOPICS] = 'US politics after 1970';
        WordPressStubState::$post_meta[self::POST_ID]['_respectify_article_id'] = 'test-article-context-id';
        $_POST = ['respectify_nonce' => 'valid-test-nonce'];
    }


    protected function tearDown(): void {
        $_POST = [];
    }


    public function testInterceptComment_AnyMentionModeBelowThreshold_GivesUndesiredTopicsFeedback(): void {
        // The threshold is still saved from when threshold mode was chosen; in "any mention" mode it must not apply
        $this->setRelevanceSettings('any', 0.5);

        $result = $this->intercept(0.2);

        $this->assertInstanceOf(\WP_Error::class, $result, 'Any mention of an undesired topic should be acted on');
        $this->assertSame(\Respectify\ACTION_REVISE, $result->get_error_code(), 'The comment should be sent back for revision');
        $this->assertStringContainsString(self::UNDESIRED_TOPICS_EXPLANATION, $result->get_error_message(), 'The undesired-topics explanation should be shown, not the generic fallback');
        $this->assertStringContainsString(self::UNDESIRED_TOPICS_REASONING, $result->get_error_message(), 'The API reasoning should be shown');
    }


    public function testInterceptComment_ThresholdModeBelowThreshold_IsPublished(): void {
        $this->setRelevanceSettings('threshold', 0.5);

        $result = $this->intercept(0.2);

        $this->assertIsArray($result, 'A mention below the threshold should be published in threshold mode');
        $this->assertSame(1, $result['comment_approved']);
    }


    public function testInterceptComment_ThresholdModeAtThreshold_GivesUndesiredTopicsFeedback(): void {
        $this->setRelevanceSettings('threshold', 0.5);

        $result = $this->intercept(0.5);

        $this->assertInstanceOf(\WP_Error::class, $result, 'A comment at the threshold should be acted on');
        $this->assertSame(\Respectify\ACTION_REVISE, $result->get_error_code());
        $this->assertStringContainsString(self::UNDESIRED_TOPICS_EXPLANATION, $result->get_error_message(), 'The undesired-topics explanation should be shown');
    }


    private function setRelevanceSettings(string $mode, float $threshold): void {
        WordPressStubState::$options[\Respectify\OPTION_RELEVANCE_SETTINGS] = [
            'off_topic_handling' => \Respectify\ACTION_PUBLISH,
            'banned_topics_handling' => \Respectify\ACTION_REVISE,
            'banned_topics_threshold' => $threshold,
            'banned_topics_mode' => $mode,
        ];
    }


    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function intercept(float $quantity_on_undesired_topics): array|\WP_Error {
        $plugin_class = new \ReflectionClass(RespectifyWordpressPlugin::class);
        $plugin = $plugin_class->newInstanceWithoutConstructor();
        $plugin_class->getProperty('respectify_client')->setValue($plugin, new FakeRespectifyClient(new MegaCallResult([
            'relevance_check' => [
                'on_topic' => ['on_topic' => true, 'confidence' => 0.9, 'reasoning' => ''],
                'banned_topics' => [
                    'banned_topics' => ['US politics after 1970'],
                    'quantity_on_banned_topics' => $quantity_on_undesired_topics,
                    'confidence' => 0.9,
                    'reasoning' => self::UNDESIRED_TOPICS_REASONING,
                ],
            ],
        ])));
        $plugin_class->getProperty('version')->setValue($plugin, 'test');

        return $plugin->intercept_comment([
            'comment_post_ID' => self::POST_ID,
            'comment_content' => 'Nice article. Reminds me of the 1980 election.',
            'comment_parent' => 0,
        ]);
    }
}
