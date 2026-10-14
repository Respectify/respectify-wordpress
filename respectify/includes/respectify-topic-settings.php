<?php
/**
 * Topic relevance and undesired topics settings: validating saved relevance settings, and sanitising the
 * one-per-line topic lists the settings page saves.
 *
 * Neither reads nor writes WordPress state, so the comment-handling path and the settings page share one set of
 * rules.
 */

namespace Respectify;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * Relevance settings with every field present and valid. A missing or invalid field takes its value from
 * $fallback, or from the default when $fallback's value is itself missing or invalid.
 *
 * @param array<mixed> $input Relevance settings to check (untrusted: may be submitted or a stale saved value).
 * @param array<mixed> $fallback Values to keep for missing or invalid fields, eg the currently saved settings.
 * @return array{off_topic_handling: string, banned_topics_handling: string, banned_topics_threshold: float, banned_topics_mode: string}
 */
function respectify_normalize_relevance_settings(array $input, array $fallback = RELEVANCE_DEFAULT_SETTINGS): array {
    $valid_actions = array(ACTION_PUBLISH, ACTION_REVISE, ACTION_DELETE);
    $valid_modes = array(RELEVANCE_BANNED_TOPICS_MODE_ANY, RELEVANCE_BANNED_TOPICS_MODE_THRESHOLD);

    $pick_choice = static function (string $field, array $allowed) use ($input, $fallback): string {
        foreach (array($input, $fallback) as $source) {
            if (isset($source[$field]) && is_string($source[$field]) && in_array($source[$field], $allowed, true)) {
                return $source[$field];
            }
        }
        return RELEVANCE_DEFAULT_SETTINGS[$field];
    };

    $pick_threshold = static function () use ($input, $fallback): float {
        foreach (array($input, $fallback) as $source) {
            if (isset($source['banned_topics_threshold']) && is_numeric($source['banned_topics_threshold'])) {
                return max(0.0, min(1.0, (float) $source['banned_topics_threshold']));
            }
        }
        return (float) RELEVANCE_DEFAULT_BANNED_TOPICS_THRESHOLD;
    };

    return array(
        'off_topic_handling' => $pick_choice('off_topic_handling', $valid_actions),
        'banned_topics_handling' => $pick_choice('banned_topics_handling', $valid_actions),
        'banned_topics_threshold' => $pick_threshold(),
        'banned_topics_mode' => $pick_choice('banned_topics_mode', $valid_modes),
    );
}


/**
 * Sanitise a one-per-line topic list as plain text: no HTML, each line trimmed, empty lines dropped, at most
 * TOPIC_LIST_MAX_ENTRIES lines of at most TOPIC_LIST_MAX_ENTRY_LENGTH characters. The limits match what the
 * Respectify API reads, so nothing saved here is silently ignored by the API.
 *
 * @param mixed $input The submitted value (untrusted).
 * @return array{topics: string, dropped_entries: int, shortened_entries: int} The sanitised list, one topic per
 *         line, and how many lines were dropped for being over the entry limit or shortened for being too long.
 */
function respectify_sanitize_topic_list($input): array {
    if (!is_string($input)) {
        return array('topics' => '', 'dropped_entries' => 0, 'shortened_entries' => 0);
    }

    $topics = array();
    foreach (preg_split('/\r\n|\r|\n/', $input) as $line) {
        // sanitize_text_field strips tags, line breaks and extra whitespace, and trims
        $topic = sanitize_text_field($line);
        if ($topic !== '') {
            $topics[] = $topic;
        }
    }

    $kept_topics = array_slice($topics, 0, TOPIC_LIST_MAX_ENTRIES);
    $shortened_entries = 0;
    foreach ($kept_topics as $index => $topic) {
        if (mb_strlen($topic, 'UTF-8') > TOPIC_LIST_MAX_ENTRY_LENGTH) {
            $kept_topics[$index] = trim(mb_substr($topic, 0, TOPIC_LIST_MAX_ENTRY_LENGTH, 'UTF-8'));
            $shortened_entries++;
        }
    }

    return array(
        'topics' => implode("\n", $kept_topics),
        'dropped_entries' => count($topics) - count($kept_topics),
        'shortened_entries' => $shortened_entries,
    );
}
