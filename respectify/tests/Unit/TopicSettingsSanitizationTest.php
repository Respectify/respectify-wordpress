<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respectify\Tests\Support\WordPressStubState;

/**
 * The relevance settings and the topic lists are saved only through their sanitisers: relevance settings keep
 * every field the settings page posts and only allowed values, and the undesired topics, sensitive topics and
 * dogwhistle examples lists are plain text, one per line, within the limits the Respectify API reads.
 */
final class TopicSettingsSanitizationTest extends TestCase {

    protected function setUp(): void {
        WordPressStubState::reset();
    }


    public function testRegisterSettings_RelevanceSettings_HasSanitizeCallback(): void {
        respectify_register_settings();

        $this->assertSame(
            'respectify_sanitize_relevance_settings',
            self::registeredSanitizeCallback(\Respectify\OPTION_RELEVANCE_SETTINGS),
            'Relevance settings should be saved through their sanitiser'
        );
    }


    public function testRegisterSettings_BannedTopics_HasSanitizeCallback(): void {
        respectify_register_settings();

        $this->assertSame(
            'respectify_sanitize_banned_topics',
            self::registeredSanitizeCallback(\Respectify\OPTION_BANNED_TOPICS),
            'Undesired topics should be saved through their sanitiser'
        );
    }


    public function testSanitizeRelevanceSettings_EveryPostedField_IsKept(): void {
        $sanitized = respectify_sanitize_relevance_settings([
            'off_topic_handling' => \Respectify\ACTION_REVISE,
            'banned_topics_mode' => 'threshold',
            'banned_topics_threshold' => '0.3',
            'banned_topics_handling' => \Respectify\ACTION_DELETE,
        ]);

        $this->assertSame([
            'off_topic_handling' => \Respectify\ACTION_REVISE,
            'banned_topics_handling' => \Respectify\ACTION_DELETE,
            'banned_topics_threshold' => 0.3,
            'banned_topics_mode' => 'threshold',
        ], $sanitized, 'Every field the settings page posts, including the undesired topics mode, should be saved');
    }


    public function testSanitizeRelevanceSettings_ThresholdNotPosted_KeepsSavedThreshold(): void {
        // In "any mention" mode the threshold slider is disabled, so the browser does not post it
        WordPressStubState::$options[\Respectify\OPTION_RELEVANCE_SETTINGS] = [
            'off_topic_handling' => \Respectify\ACTION_PUBLISH,
            'banned_topics_handling' => \Respectify\ACTION_REVISE,
            'banned_topics_threshold' => 0.4,
            'banned_topics_mode' => 'threshold',
        ];

        $sanitized = respectify_sanitize_relevance_settings([
            'off_topic_handling' => \Respectify\ACTION_PUBLISH,
            'banned_topics_mode' => 'any',
            'banned_topics_handling' => \Respectify\ACTION_REVISE,
        ]);

        $this->assertSame(0.4, $sanitized['banned_topics_threshold'], 'The saved threshold should be kept, not dropped');
        $this->assertSame('any', $sanitized['banned_topics_mode']);
    }


    public function testSanitizeRelevanceSettings_InvalidValues_AreReplacedByDefaults(): void {
        $sanitized = respectify_sanitize_relevance_settings([
            'off_topic_handling' => '<script>alert(1)</script>',
            'banned_topics_mode' => 'sometimes',
            'banned_topics_threshold' => 'lots',
            'banned_topics_handling' => 'approve_everything',
        ]);

        $this->assertSame(\Respectify\RELEVANCE_DEFAULT_SETTINGS['off_topic_handling'], $sanitized['off_topic_handling'], 'An unknown off-topic action should not be saved');
        $this->assertSame(\Respectify\RELEVANCE_DEFAULT_SETTINGS['banned_topics_mode'], $sanitized['banned_topics_mode'], 'An unknown mode should not be saved');
        $this->assertSame((float) \Respectify\RELEVANCE_DEFAULT_SETTINGS['banned_topics_threshold'], $sanitized['banned_topics_threshold'], 'A non-numeric threshold should not be saved');
        $this->assertSame(\Respectify\RELEVANCE_DEFAULT_SETTINGS['banned_topics_handling'], $sanitized['banned_topics_handling'], 'An unknown undesired-topics action should not be saved');
    }


    public function testSanitizeRelevanceSettings_ThresholdOutOfRange_IsClamped(): void {
        $this->assertSame(1.0, respectify_sanitize_relevance_settings(['banned_topics_threshold' => '7'])['banned_topics_threshold']);
        $this->assertSame(0.0, respectify_sanitize_relevance_settings(['banned_topics_threshold' => '-2'])['banned_topics_threshold']);
    }


    public function testSanitizeRelevanceSettings_NotAnArray_KeepsSavedSettings(): void {
        WordPressStubState::$options[\Respectify\OPTION_RELEVANCE_SETTINGS] = [
            'off_topic_handling' => \Respectify\ACTION_DELETE,
            'banned_topics_handling' => \Respectify\ACTION_DELETE,
            'banned_topics_threshold' => 0.6,
            'banned_topics_mode' => 'threshold',
        ];

        $sanitized = respectify_sanitize_relevance_settings('not settings');

        $this->assertSame(WordPressStubState::$options[\Respectify\OPTION_RELEVANCE_SETTINGS], $sanitized, 'A malformed submission should not change the saved settings');
    }


    public function testSanitizeBannedTopics_HtmlWhitespaceAndEmptyLines_AreCleaned(): void {
        $sanitized = respectify_sanitize_banned_topics("  US politics after 1970  \r\n\r\n<b>Celebrity</b> <em>gossip</em>\n   \nCrypto investing\n");

        $this->assertSame("US politics after 1970\nCelebrity gossip\nCrypto investing", $sanitized, 'Topics should be plain text, trimmed, one per line, with empty lines dropped');
    }


    public function testSanitizeBannedTopics_NotAString_SavesNothing(): void {
        $this->assertSame('', respectify_sanitize_banned_topics(['US politics']), 'A malformed submission should not be saved');
    }


    public function testSanitizeBannedTopics_OverEntryLimit_KeepsOnlyWhatTheApiReads(): void {
        $topics = [];
        for ($index = 1; $index <= \Respectify\TOPIC_LIST_MAX_ENTRIES + 5; $index++) {
            $topics[] = "Topic {$index}";
        }

        $sanitized = respectify_sanitize_banned_topics(implode("\n", $topics));

        $this->assertCount(\Respectify\TOPIC_LIST_MAX_ENTRIES, explode("\n", $sanitized), 'No more topics should be saved than the API reads');
        $this->assertCount(1, WordPressStubState::$settings_errors, 'The admin should be told that topics were dropped');
    }


    public function testSanitizeBannedTopics_OverlongTopic_IsShortened(): void {
        $sanitized = respectify_sanitize_banned_topics(str_repeat('a', \Respectify\TOPIC_LIST_MAX_ENTRY_LENGTH + 10));

        $this->assertSame(\Respectify\TOPIC_LIST_MAX_ENTRY_LENGTH, strlen($sanitized), 'A topic should be no longer than the API reads');
        $this->assertCount(1, WordPressStubState::$settings_errors, 'The admin should be told that a topic was shortened');
    }


    public function testSanitizeBannedTopics_WithinLimits_ReportsNoError(): void {
        respectify_sanitize_banned_topics("US politics after 1970\nCrypto investing");

        $this->assertSame([], WordPressStubState::$settings_errors, 'A valid list should not show an error');
    }


    /**
     * The dogwhistle check's two lists, each with the option it is saved to and its sanitiser. The API caps both
     * at the same prompt-list limits as undesired topics (input_limits.MAX_PROMPT_LIST_ENTRIES and
     * MAX_PROMPT_LIST_ENTRY_LENGTH).
     *
     * @return array<string, array{string, string}>
     */
    public static function dogwhistleLists(): array {
        return [
            'sensitive topics' => [\Respectify\OPTION_SENSITIVE_TOPICS, 'respectify_sanitize_sensitive_topics'],
            'dogwhistle examples' => [\Respectify\OPTION_DOGWHISTLE_EXAMPLES, 'respectify_sanitize_dogwhistle_examples'],
        ];
    }


    #[DataProvider('dogwhistleLists')]
    public function testRegisterSettings_DogwhistleList_HasSanitizeCallback(string $option_name, string $sanitizer): void {
        respectify_register_settings();

        $this->assertSame($sanitizer, self::registeredSanitizeCallback($option_name), 'The list should be saved through its sanitiser');
    }


    #[DataProvider('dogwhistleLists')]
    public function testSanitizeDogwhistleList_HtmlWhitespaceAndEmptyLines_AreCleaned(string $option_name, string $sanitizer): void {
        $sanitized = $sanitizer("  Vaccine safety  \r\n\r\n<b>Election</b> <em>integrity</em>\n   \nGlobalists\n");

        $this->assertSame("Vaccine safety\nElection integrity\nGlobalists", $sanitized, 'Entries should be plain text, trimmed, one per line, with empty lines dropped');
    }


    #[DataProvider('dogwhistleLists')]
    public function testSanitizeDogwhistleList_NotAString_SavesNothing(string $option_name, string $sanitizer): void {
        $this->assertSame('', $sanitizer(['Vaccine safety']), 'A malformed submission should not be saved');
    }


    #[DataProvider('dogwhistleLists')]
    public function testSanitizeDogwhistleList_OverEntryLimit_KeepsOnlyWhatTheApiReads(string $option_name, string $sanitizer): void {
        $entries = [];
        for ($index = 1; $index <= \Respectify\TOPIC_LIST_MAX_ENTRIES + 5; $index++) {
            $entries[] = "Entry {$index}";
        }

        $sanitized = $sanitizer(implode("\n", $entries));

        $this->assertSame(implode("\n", array_slice($entries, 0, \Respectify\TOPIC_LIST_MAX_ENTRIES)), $sanitized, 'Only the first entries the API reads should be saved');
        $this->assertCount(1, WordPressStubState::$settings_errors, 'The admin should be told that entries were dropped');
        $this->assertSame($option_name, WordPressStubState::$settings_errors[0]['setting'], 'The warning should belong to this list');
        $this->assertSame('warning', WordPressStubState::$settings_errors[0]['type'], 'Fitting the list to the limits is a warning, not an error');
    }


    #[DataProvider('dogwhistleLists')]
    public function testSanitizeDogwhistleList_OverlongEntry_IsShortened(string $option_name, string $sanitizer): void {
        $sanitized = $sanitizer(str_repeat('a', \Respectify\TOPIC_LIST_MAX_ENTRY_LENGTH + 10));

        $this->assertSame(\Respectify\TOPIC_LIST_MAX_ENTRY_LENGTH, strlen($sanitized), 'An entry should be no longer than the API reads');
        $this->assertCount(1, WordPressStubState::$settings_errors, 'The admin should be told that an entry was shortened');
        $this->assertSame($option_name, WordPressStubState::$settings_errors[0]['setting'], 'The warning should belong to this list');
    }


    #[DataProvider('dogwhistleLists')]
    public function testSanitizeDogwhistleList_WithinLimits_ReportsNoError(string $option_name, string $sanitizer): void {
        $sanitizer("Vaccine safety\nGlobalists");

        $this->assertSame([], WordPressStubState::$settings_errors, 'A valid list should not show an error');
    }


    private static function registeredSanitizeCallback(string $option_name): mixed {
        $registration = WordPressStubState::$registered_settings[$option_name] ?? null;
        if ($registration === null) {
            return null;
        }
        // register_setting() takes either the callback itself (older form) or an args array
        $args = $registration['args'];
        return is_array($args) ? ($args['sanitize_callback'] ?? null) : $args;
    }
}
