<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * The plugin requests exactly the checks the site has turned on, and nothing more: every check is charged per use,
 * so a check that is off must never be requested. With every check off, no API call is made at all and the comment
 * is published as it would be with every check passing.
 */
final class EnabledChecksTest extends TestCase {

    private const POST_ID = 42;

    private const ARTICLE_ID = 'test-article-context-id';


    protected function setUp(): void {
        WordPressStubState::reset();
        WordPressStubState::$post_fields[self::POST_ID]['post_content'] = 'An article about gardening.';
        $_POST = ['respectify_nonce' => 'valid-test-nonce'];
    }


    protected function tearDown(): void {
        $_POST = [];
    }


    public function testInterceptComment_AllChecksOff_MakesNoApiCall(): void {
        $this->enableChecks([]);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $this->createPlugin($client)->intercept_comment(self::commentData());

        $this->assertSame([], $client->megacall_services, 'No check is on, so no evaluation should be requested');
        $this->assertSame([], $client->init_topic_texts, 'No check needs the article context, so none should be created');
    }


    public function testInterceptComment_AllChecksOff_PublishesComment(): void {
        $this->enableChecks([]);

        $result = $this->createPlugin(new FakeRespectifyClient(self::passingMegaCallResult()))->intercept_comment(self::commentData());

        $this->assertIsArray($result, 'With every check off, the comment should be published');
        $this->assertSame(1, $result['comment_approved'], 'The comment should be approved');
        $this->assertSame('respectify_approved', $result['comment_type'], 'The comment should be published as one that passed');
        $this->assertSame([], WordPressStubState::$inserted_comments, 'Nothing should be held for moderation');
    }


    public function testEvaluateComment_AllChecksOffWithArticleContext_MakesNoApiCall(): void {
        // Even with an article context to hand, no check means nothing to request
        $this->enableChecks([]);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $evaluation = $this->createPlugin($client)->evaluate_comment(self::ARTICLE_ID, 'A comment.');

        $this->assertSame([], $client->megacall_services, 'No check is on, so no evaluation should be requested');
        $this->assertInstanceOf(MegaCallResult::class, $evaluation, 'An evaluation with no findings should be returned');
    }


    public function testInterceptComment_OnlySpamOn_RequestsOnlySpam(): void {
        $this->enableChecks(['check_spam']);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $this->createPlugin($client)->intercept_comment(self::commentData());

        $this->assertSame([['spam']], $client->megacall_services, 'Only the spam check should be requested');
        $this->assertSame([], $client->init_topic_texts, 'Spam needs no article context');
    }


    public function testInterceptComment_OnlyHealthOn_RequestsOnlyCommentScore(): void {
        $this->enableChecks(['assess_health']);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $this->createPlugin($client)->intercept_comment(self::commentData());

        $this->assertSame([['commentscore']], $client->megacall_services, 'Only comment health should be requested');
    }


    public function testInterceptComment_OnlyRelevanceOn_RequestsOnlyRelevance(): void {
        $this->enableChecks(['check_relevance']);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $this->createPlugin($client)->intercept_comment(self::commentData());

        $this->assertSame([['relevance']], $client->megacall_services, 'Only relevance should be requested');
        $this->assertCount(1, $client->init_topic_texts, 'Relevance needs the article context, so it should be created');
    }


    public function testInterceptComment_OnlyDogwhistleOn_RequestsOnlyDogwhistle(): void {
        $this->enableChecks(['check_dogwhistle']);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $this->createPlugin($client)->intercept_comment(self::commentData());

        $this->assertSame([['dogwhistle']], $client->megacall_services, 'Only the dogwhistle check should be requested');
    }


    public function testInterceptComment_AllChecksOn_RequestsEveryCheck(): void {
        $this->enableChecks(['check_spam', 'assess_health', 'check_relevance', 'check_dogwhistle']);
        $client = new FakeRespectifyClient(self::passingMegaCallResult());

        $this->createPlugin($client)->intercept_comment(self::commentData());

        $this->assertCount(1, $client->megacall_services, 'One evaluation should be requested');
        $requested_services = $client->megacall_services[0];
        sort($requested_services);
        $this->assertSame(['commentscore', 'dogwhistle', 'relevance', 'spam'], $requested_services, 'Every check should be requested');
    }


    /**
     * @param array<int, string> $enabled_checks Assessment setting keys to turn on; every other check is off.
     */
    private function enableChecks(array $enabled_checks): void {
        $assessment_settings = [];
        foreach (['assess_health', 'check_relevance', 'check_spam', 'check_dogwhistle'] as $check) {
            $assessment_settings[$check] = in_array($check, $enabled_checks, true);
        }
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = $assessment_settings;
    }


    /**
     * @return array<string, mixed>
     */
    private static function commentData(): array {
        return [
            'comment_post_ID' => self::POST_ID,
            'comment_content' => 'I planted tomatoes this year and they did well.',
            'comment_parent' => 0,
        ];
    }


    private function createPlugin(FakeRespectifyClient $client): RespectifyWordpressPlugin {
        // The real constructor registers WordPress hooks and builds a live API client; neither is wanted here.
        $plugin_class = new \ReflectionClass(RespectifyWordpressPlugin::class);
        $plugin = $plugin_class->newInstanceWithoutConstructor();
        $plugin_class->getProperty('respectify_client')->setValue($plugin, $client);
        $plugin_class->getProperty('version')->setValue($plugin, 'test');
        return $plugin;
    }


    private static function passingMegaCallResult(): MegaCallResult {
        return new MegaCallResult([
            'spam_check' => ['is_spam' => false, 'confidence' => 0.05, 'reasoning' => ''],
            'comment_score' => [
                'logical_fallacies' => [],
                'objectionable_phrases' => [],
                'negative_tone_phrases' => [],
                'appears_low_effort' => false,
                'overall_score' => 4,
                'toxicity_score' => 0.05,
                'toxicity_explanation' => '',
                'exceeds_tolerance' => false,
            ],
            'relevance_check' => [
                'on_topic' => ['on_topic' => true, 'confidence' => 0.9, 'reasoning' => ''],
                'banned_topics' => ['banned_topics' => [], 'quantity_on_banned_topics' => 0.0, 'confidence' => 0.9, 'reasoning' => ''],
            ],
            'dogwhistle_check' => [
                'detection' => ['dogwhistles_detected' => false, 'confidence' => 0.9, 'reasoning' => ''],
            ],
        ]);
    }
}
