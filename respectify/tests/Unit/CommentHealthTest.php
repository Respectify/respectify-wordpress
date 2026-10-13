<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respectify\RespectifyWordpressPlugin;
use Respectify\Tests\Support\FakeRespectifyClient;
use Respectify\Tests\Support\TerminatedRequest;
use Respectify\Tests\Support\WordPressStubState;
use RespectifyScoper\Respectify\Moderation\ModerationTolerances;
use RespectifyScoper\Respectify\Schemas\MegaCallResult;

/**
 * Comment health: one level per health item (the nine problem categories and quality), sent with every
 * evaluation, with the coaching decision taken from the API's exceeds_tolerance. Also covers migrating the
 * settings of earlier plugin versions (minimum score, per-check checkboxes, per-category tolerances).
 */
final class CommentHealthTest extends TestCase {

    private const POST_ID = 42;

    private const HEALTH_ITEMS = [
        'threat', 'identity_attack', 'sexual_content', 'harassment', 'insult', 'profanity',
        'dismissive_tone', 'logical_fallacy', 'low_effort', 'quality',
    ];

    private const FLOORED_ITEMS = ['threat', 'identity_attack', 'sexual_content', 'harassment'];


    protected function setUp(): void {
        WordPressStubState::reset();
        $_POST = [];
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS] = [
            'assess_health' => true,
            'check_relevance' => false,
            'check_spam' => true,
            'check_dogwhistle' => false,
        ];
        WordPressStubState::$post_meta[self::POST_ID]['_respectify_article_id'] = 'test-article-context-id';
    }


    protected function tearDown(): void {
        $_POST = [];
    }


    public function testGetCommentHealthSettings_NothingSaved_EveryItemIsStandard(): void {
        $settings = \Respectify\respectify_get_comment_health_settings();

        $this->assertSame(self::HEALTH_ITEMS, array_keys($settings), 'All ten health items, quality last');
        $this->assertSame(array_fill_keys(self::HEALTH_ITEMS, 'standard'), $settings);
    }


    public function testGetCommentHealthSettings_InvalidSavedLevels_ReadAsStandard(): void {
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES] = [
            'threat' => 'off',
            'harassment' => 'relaxed',
            'identity_attack' => 'permissive',
            'insult' => 'lenient',
            'dismissive_tone' => 'permissive',
            'quality' => 'off',
            'profanity' => 'relaxed',
            'spam' => 'strict',
        ];

        $settings = \Respectify\respectify_get_comment_health_settings();

        $this->assertSame('standard', $settings['threat'], 'A floored category can never read as off');
        $this->assertSame('standard', $settings['harassment'], 'A floored category can never read as relaxed');
        $this->assertSame('standard', $settings['identity_attack'], 'A floored category can never read as permissive');
        $this->assertSame('permissive', $settings['dismissive_tone'], 'A non-floored item keeps a saved permissive level');
        $this->assertSame('standard', $settings['insult'], 'An unknown level reads as standard');
        $this->assertSame('off', $settings['quality']);
        $this->assertSame('relaxed', $settings['profanity']);
        $this->assertArrayNotHasKey('spam', $settings, 'Spam is a separate check, not a health item');
    }


    public function testIsAllowedCommentHealthLevel_FlooredItems_OnlyStandardOrStricter(): void {
        foreach (self::FLOORED_ITEMS as $item) {
            $this->assertFalse(\Respectify\respectify_is_allowed_comment_health_level($item, 'off'), "$item cannot be off");
            $this->assertFalse(\Respectify\respectify_is_allowed_comment_health_level($item, 'relaxed'), "$item cannot be relaxed");
            $this->assertFalse(\Respectify\respectify_is_allowed_comment_health_level($item, 'permissive'), "$item cannot be permissive");
            foreach (['standard', 'strict', 'zero_tolerance'] as $level) {
                $this->assertTrue(\Respectify\respectify_is_allowed_comment_health_level($item, $level), "$item allows $level");
            }
        }
        foreach (array_diff(self::HEALTH_ITEMS, self::FLOORED_ITEMS) as $item) {
            foreach (['off', 'permissive', 'relaxed', 'standard', 'strict', 'zero_tolerance'] as $level) {
                $this->assertTrue(\Respectify\respectify_is_allowed_comment_health_level($item, $level), "$item allows $level");
            }
        }
        $this->assertFalse(\Respectify\respectify_is_allowed_comment_health_level('quality', ['strict']), 'Non-string input is rejected');
    }


    public function testIsAllowedCommentHealthLevel_Permissive_OnlyForNonFlooredItems(): void {
        foreach (self::HEALTH_ITEMS as $item) {
            $is_floored = in_array($item, self::FLOORED_ITEMS, true);
            $this->assertSame(!$is_floored, \Respectify\respectify_is_allowed_comment_health_level($item, 'permissive'),
                $is_floored ? "$item is floored, so cannot be permissive" : "$item allows permissive");
        }
    }


    public function testSanitizeCommentHealthLevels_Permissive_AcceptedForNonFlooredRejectedForFloored(): void {
        $current = array_fill_keys(self::HEALTH_ITEMS, 'standard');

        $outcome = \Respectify\respectify_sanitize_comment_health_levels([
            'profanity' => 'permissive',
            'quality' => 'permissive',
            'sexual_content' => 'permissive',
        ], $current);

        $this->assertSame('permissive', $outcome['levels']['profanity']);
        $this->assertSame('permissive', $outcome['levels']['quality']);
        $this->assertSame('standard', $outcome['levels']['sexual_content'], 'A rejected permissive floor keeps the previous level');
        $this->assertSame(['sexual_content'], $outcome['rejected']);
    }


    public function testSanitizeCommentHealthLevels_DisallowedLevel_KeepsPreviousLevelAndReportsIt(): void {
        $current = array_fill_keys(self::HEALTH_ITEMS, 'standard');
        $current['threat'] = 'strict';

        $outcome = \Respectify\respectify_sanitize_comment_health_levels([
            'threat' => 'off',
            'insult' => 'off',
            'quality' => 'zero_tolerance',
            'profanity' => '<script>',
            'not_an_item' => 'strict',
        ], $current);

        $this->assertSame('strict', $outcome['levels']['threat'], 'A rejected floor level never changes (or relaxes) the item');
        $this->assertSame('off', $outcome['levels']['insult']);
        $this->assertSame('zero_tolerance', $outcome['levels']['quality']);
        $this->assertSame('standard', $outcome['levels']['profanity']);
        $this->assertSame(self::HEALTH_ITEMS, array_keys($outcome['levels']), 'Unknown items are dropped');
        $this->assertSame(['threat', 'profanity'], $outcome['rejected']);
    }


    public function testBuildCommentHealthTolerances_SendsEveryItemLevel(): void {
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES] = ['quality' => 'strict', 'logical_fallacy' => 'off', 'profanity' => 'permissive'];

        $tolerances = \Respectify\respectify_build_comment_health_tolerances();

        $this->assertInstanceOf(ModerationTolerances::class, $tolerances);
        $expected = array_fill_keys(self::HEALTH_ITEMS, 'standard');
        $expected['quality'] = 'strict';
        $expected['logical_fallacy'] = 'off';
        $expected['profanity'] = 'permissive';
        $this->assertSame($expected, $tolerances->toArray(), 'Nothing is stored per API key, so all ten levels are sent');
    }


    public function testMigrateCommentHealthSettings_FreshInstall_DefaultsAndMarksMigrated(): void {
        \Respectify\respectify_migrate_comment_health_settings();

        $this->assertSame(array_fill_keys(self::HEALTH_ITEMS, 'standard'), WordPressStubState::$options[\Respectify\OPTION_TOLERANCES]);
        $this->assertSame(\Respectify\SETTINGS_SCHEMA_COMMENT_HEALTH, WordPressStubState::$options[\Respectify\OPTION_SETTINGS_SCHEMA_VERSION]);
    }


    public function testMigrateCommentHealthSettings_MinimumScore_MapsToQualityLevel(): void {
        $expected_quality_levels = [1 => 'relaxed', 2 => 'relaxed', 3 => 'standard', 4 => 'strict', 5 => 'zero_tolerance'];
        foreach ($expected_quality_levels as $min_score => $expected_level) {
            WordPressStubState::reset();
            WordPressStubState::$options[\Respectify\OPTION_REVISE_SETTINGS] = self::oldReviseSettings(['min_score' => (string) $min_score]);

            \Respectify\respectify_migrate_comment_health_settings();

            $this->assertSame($expected_level, WordPressStubState::$options[\Respectify\OPTION_TOLERANCES]['quality'],
                "Minimum score $min_score should become quality $expected_level");
        }
    }


    public function testMigrateCommentHealthSettings_OldTolerancesCarryOver(): void {
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES] = [
            'threat' => 'zero_tolerance',
            'harassment' => 'strict',
            'profanity' => 'relaxed',
            'insult' => 'strict',
        ];

        \Respectify\respectify_migrate_comment_health_settings();

        $migrated = WordPressStubState::$options[\Respectify\OPTION_TOLERANCES];
        $this->assertSame('zero_tolerance', $migrated['threat']);
        $this->assertSame('strict', $migrated['harassment']);
        $this->assertSame('relaxed', $migrated['profanity']);
        $this->assertSame('strict', $migrated['insult']);
        $this->assertSame('standard', $migrated['quality'], 'No saved minimum score means standard quality');
    }


    public function testMigrateCommentHealthSettings_SwitchedOffChecks_BecomeOffWhereAllowed(): void {
        // Unchecked boxes are absent from the saved array (the form only posts checked boxes)
        WordPressStubState::$options[\Respectify\OPTION_REVISE_SETTINGS] = [
            'min_score' => '3',
            'toxicity' => '1',
            'toxicity_threshold' => '0.5',
        ];
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES] = ['threat' => 'strict', 'insult' => 'strict'];

        \Respectify\respectify_migrate_comment_health_settings();

        $migrated = WordPressStubState::$options[\Respectify\OPTION_TOLERANCES];
        $this->assertSame('off', $migrated['low_effort'], 'Low effort checkbox off');
        $this->assertSame('off', $migrated['logical_fallacy'], 'Logical fallacies checkbox off');
        $this->assertSame('off', $migrated['insult'], 'Objectionable phrases checkbox off turns insults off, overriding the old level');
        $this->assertSame('off', $migrated['profanity'], 'Objectionable phrases checkbox off turns profanity off');
        $this->assertSame('off', $migrated['dismissive_tone'], 'Negative tone checkbox off');
        $this->assertSame('strict', $migrated['threat'], 'Floored categories are never turned off');
        foreach (['identity_attack', 'sexual_content', 'harassment'] as $floored) {
            $this->assertSame('standard', $migrated[$floored], "$floored stays at standard");
        }
        $this->assertSame('standard', $migrated['quality']);
    }


    public function testMigrateCommentHealthSettings_AllChecksOn_LeavesItemsAtTheirLevel(): void {
        WordPressStubState::$options[\Respectify\OPTION_REVISE_SETTINGS] = self::oldReviseSettings(['min_score' => '4']);

        \Respectify\respectify_migrate_comment_health_settings();

        $expected = array_fill_keys(self::HEALTH_ITEMS, 'standard');
        $expected['quality'] = 'strict';
        $this->assertSame($expected, WordPressStubState::$options[\Respectify\OPTION_TOLERANCES]);
    }


    public function testMigrateCommentHealthSettings_RunsOnlyOnce(): void {
        WordPressStubState::$options[\Respectify\OPTION_REVISE_SETTINGS] = self::oldReviseSettings(['min_score' => '5']);
        \Respectify\respectify_migrate_comment_health_settings();

        // The admin then lowers quality; a second run must not reapply the old minimum score
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES]['quality'] = 'relaxed';
        \Respectify\respectify_migrate_comment_health_settings();

        $this->assertSame('relaxed', WordPressStubState::$options[\Respectify\OPTION_TOLERANCES]['quality']);
    }


    public function testEvaluation_SendsAllLevelsAsTolerancesWithCommentScore(): void {
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES] = ['quality' => 'zero_tolerance'];
        $client = new FakeRespectifyClient(self::megaCallResult(['exceeds_tolerance' => false]));

        $this->submitComment($this->createPlugin($client));

        $this->assertContains('commentscore', $client->megacall_services[0]);
        $sent_tolerances = $client->megacall_tolerances[0];
        $this->assertInstanceOf(ModerationTolerances::class, $sent_tolerances);
        $this->assertCount(10, $sent_tolerances->toArray(), 'Every evaluation carries all ten levels');
        $this->assertSame('zero_tolerance', $sent_tolerances->toArray()['quality']);
    }


    public function testEvaluation_ExceedsTolerance_AsksForRevisionWithFindings(): void {
        $client = new FakeRespectifyClient(self::megaCallResult([
            'exceeds_tolerance' => true,
            'objectionable_phrases' => [[
                'quoted_objectionable_phrase' => 'you fool',
                'explanation' => 'A put-down of another commenter.',
                'suggested_rewrite' => '',
                'category' => 'insult',
                'severity' => 2,
            ]],
            'health_items' => self::healthItems(['insult' => ['score' => 3, 'minimum' => 4, 'is_acceptable' => false]]),
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'A comment over tolerance is sent back');
        $this->assertStringContainsString('you fool', (string) $termination->response_payload['message'], 'The unaccepted finding is shown');
        $this->assertSame([], WordPressStubState::$inserted_comments, 'A comment sent back for revision is not stored');
    }


    public function testEvaluation_ExtremeFinding_AsksForRevisionWithFindings(): void {
        // Severity 4 (extreme) is the very worst; no level accepts it, and it scores 1
        WordPressStubState::$options[\Respectify\OPTION_TOLERANCES] = ['insult' => 'permissive'];
        $client = new FakeRespectifyClient(self::megaCallResult([
            'exceeds_tolerance' => true,
            'objectionable_phrases' => [[
                'quoted_objectionable_phrase' => 'you worthless fool',
                'explanation' => 'An insult at full force.',
                'suggested_rewrite' => '',
                'category' => 'insult',
                'severity' => 4,
            ]],
            'health_items' => self::healthItems(['insult' => ['score' => 1, 'minimum' => 2, 'is_acceptable' => false]]),
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_error', $termination->terminating_function, 'An extreme finding fails even a permissive item');
        $this->assertStringContainsString('you worthless fool', (string) $termination->response_payload['message'], 'The extreme finding is shown');
        $this->assertSame('permissive', $client->megacall_tolerances[0]->toArray()['insult'], 'Permissive is sent as saved');
    }


    public function testEvaluation_WithinTolerance_PublishesEvenWithAcceptedLowScoresElsewhere(): void {
        // Quality 2 under a relaxed quality level is acceptable; the old minimum score of 3 no longer applies
        WordPressStubState::$options[\Respectify\OPTION_REVISE_SETTINGS] = self::oldReviseSettings(['min_score' => '5']);
        WordPressStubState::$options[\Respectify\OPTION_SETTINGS_SCHEMA_VERSION] = \Respectify\SETTINGS_SCHEMA_COMMENT_HEALTH;
        $client = new FakeRespectifyClient(self::megaCallResult([
            'exceeds_tolerance' => false,
            'overall_score' => 2,
            'toxicity_score' => 0.45,
            'health_items' => self::healthItems(['quality' => ['score' => 2, 'minimum' => 2, 'is_acceptable' => true]]),
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_success', $termination->terminating_function, 'The verdict comes from exceeds_tolerance alone');
        $this->assertCount(1, WordPressStubState::$inserted_comments);
    }


    public function testEvaluation_QualityBelowMinimumOnly_GivesQualityFeedback(): void {
        $client = new FakeRespectifyClient(self::megaCallResult([
            'exceeds_tolerance' => true,
            'overall_score' => 2,
            'health_items' => self::healthItems(['quality' => ['score' => 2, 'minimum' => 3, 'is_acceptable' => false]]),
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_error', $termination->terminating_function);
        $this->assertStringContainsString('could add more to the discussion', (string) $termination->response_payload['message']);
    }


    public function testEvaluation_SevereToxicityWithoutFindings_GivesToxicityFeedback(): void {
        $client = new FakeRespectifyClient(self::megaCallResult([
            'exceeds_tolerance' => true,
            'toxicity_score' => 0.9,
            'toxicity_explanation' => 'The comment is hostile throughout.',
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_error', $termination->terminating_function);
        $message = (string) $termination->response_payload['message'];
        $this->assertStringContainsString('may come across as hostile', $message);
        $this->assertStringContainsString('The comment is hostile throughout.', $message);
    }


    public function testEvaluation_HealthAssessmentOff_IgnoresCommentScore(): void {
        WordPressStubState::$options[\Respectify\OPTION_ASSESSMENT_SETTINGS]['assess_health'] = false;
        $client = new FakeRespectifyClient(self::megaCallResult(['exceeds_tolerance' => true]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertNotContains('commentscore', $client->megacall_services[0]);
        $this->assertSame('wp_send_json_success', $termination->terminating_function);
    }


    public function testEvaluation_SpamStillHandledBeforeHealth(): void {
        WordPressStubState::$options[\Respectify\OPTION_SPAM_HANDLING] = \Respectify\ACTION_DELETE;
        $client = new FakeRespectifyClient(new MegaCallResult([
            'spam_check' => ['is_spam' => true, 'confidence' => 0.99, 'reasoning' => 'Promotional.'],
            'comment_score' => self::commentScoreData(['exceeds_tolerance' => false]),
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_error', $termination->terminating_function);
        $this->assertStringContainsString('This looks like spam', (string) $termination->response_payload['message']);
    }


    public function testEvaluation_Published_StoresPerItemResultsWithoutCommentText(): void {
        $client = new FakeRespectifyClient(self::megaCallResult([
            'exceeds_tolerance' => false,
            'health_score' => 4.47,
            'overall_score' => 4,
            'health_items' => self::healthItems(['dismissive_tone' => ['score' => 4, 'minimum' => 4, 'is_acceptable' => true]]),
        ]));

        $this->submitComment($this->createPlugin($client));

        $health_meta = WordPressStubState::$inserted_comments[0]['comment_meta']['respectify_health'];
        $this->assertFalse($health_meta['exceeds_tolerance']);
        $this->assertEqualsWithDelta(4.47, $health_meta['health_score'], 0.0001);
        $this->assertSame(4, $health_meta['overall_score']);
        $this->assertSame(self::HEALTH_ITEMS, array_keys($health_meta['items']));
        $this->assertSame(['score' => 4, 'minimum' => 4, 'is_acceptable' => true], $health_meta['items']['dismissive_tone']);
        $this->assertStringNotContainsString('ZqxHealthCommentMarker', (string) wp_json_encode($health_meta), 'The summary carries no comment text');
    }


    public function testEvaluation_OlderServerWithoutHealthFields_StoresEmptyItems(): void {
        $score = self::commentScoreData(['exceeds_tolerance' => false]);
        unset($score['health_items'], $score['health_score']);
        $client = new FakeRespectifyClient(new MegaCallResult([
            'spam_check' => ['is_spam' => false, 'confidence' => 0.01, 'reasoning' => ''],
            'comment_score' => $score,
        ]));

        $termination = $this->submitComment($this->createPlugin($client));

        $this->assertSame('wp_send_json_success', $termination->terminating_function);
        $health_meta = WordPressStubState::$inserted_comments[0]['comment_meta']['respectify_health'];
        $this->assertSame([], $health_meta['items']);
        $this->assertNull($health_meta['health_score']);
    }


    private function createPlugin(FakeRespectifyClient $client): RespectifyWordpressPlugin {
        // The real constructor registers WordPress hooks and builds a live API client; neither is wanted here.
        $plugin_class = new \ReflectionClass(RespectifyWordpressPlugin::class);
        $plugin = $plugin_class->newInstanceWithoutConstructor();
        $plugin_class->getProperty('respectify_client')->setValue($plugin, $client);
        $plugin_class->getProperty('version')->setValue($plugin, 'test');
        return $plugin;
    }


    private function submitComment(RespectifyWordpressPlugin $plugin): TerminatedRequest {
        WordPressStubState::$is_doing_ajax = true;
        $_POST = [
            'respectify_nonce' => 'valid-test-nonce',
            'comment_post_ID' => (string) self::POST_ID,
            'comment_content' => 'I think ZqxHealthCommentMarker is a fair point.',
        ];
        try {
            $plugin->ajax_submit_comment();
        } catch (TerminatedRequest $termination) {
            return $termination;
        }
        $this->fail('ajax_submit_comment should have ended the request');
    }


    /**
     * The revise settings an earlier plugin version saved with every checkbox ticked.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private static function oldReviseSettings(array $overrides = []): array {
        return array_merge([
            'min_score' => '3',
            'low_effort' => '1',
            'logical_fallacies' => '1',
            'objectionable_phrases' => '1',
            'negative_tone' => '1',
            'toxicity' => '1',
            'toxicity_threshold' => '0.3',
        ], $overrides);
    }


    /**
     * Every health item scoring 5 at its standard minimum, with some items replaced.
     *
     * @param array<string, array{score: int, minimum: int, is_acceptable: bool}> $overrides
     * @return array<string, array{score: int, minimum: int, is_acceptable: bool}>
     */
    private static function healthItems(array $overrides = []): array {
        $items = [];
        foreach (self::HEALTH_ITEMS as $item) {
            $minimum = in_array($item, self::FLOORED_ITEMS, true) ? 5 : ($item === 'quality' ? 3 : 4);
            $items[$item] = ['score' => $item === 'quality' ? 4 : 5, 'minimum' => $minimum, 'is_acceptable' => true];
        }
        return array_merge($items, $overrides);
    }


    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function commentScoreData(array $overrides = []): array {
        return array_merge([
            'logical_fallacies' => [],
            'objectionable_phrases' => [],
            'negative_tone_phrases' => [],
            'appears_low_effort' => false,
            'overall_score' => 4,
            'toxicity_score' => 0.05,
            'toxicity_explanation' => '',
            'exceeds_tolerance' => false,
            'tolerances' => array_fill_keys(self::HEALTH_ITEMS, 'standard'),
            'health_score' => 4.47,
            'health_items' => self::healthItems(),
        ], $overrides);
    }


    /**
     * @param array<string, mixed> $comment_score_overrides
     */
    private static function megaCallResult(array $comment_score_overrides = []): MegaCallResult {
        return new MegaCallResult([
            'spam_check' => ['is_spam' => false, 'confidence' => 0.01, 'reasoning' => ''],
            'comment_score' => self::commentScoreData($comment_score_overrides),
        ]);
    }
}
