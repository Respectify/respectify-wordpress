<?php
/**
 * Comment health settings: one level per health item, sent with every evaluation.
 *
 * The health items are the nine problem categories and quality (respectify-php's Moderation\HealthItem). Each
 * has one level: off, permissive, relaxed, standard, strict or zero_tolerance. The four floored categories (threats, identity
 * attacks, sexual content, harassment) allow only standard, strict and zero_tolerance. Nothing is stored per API
 * key, so every evaluation sends all ten levels, and the API's exceeds_tolerance decides whether to coach.
 */

namespace Respectify;

use RespectifyScoper\Respectify\Exceptions\BadRequestException;
use RespectifyScoper\Respectify\Moderation\HealthItem;
use RespectifyScoper\Respectify\Moderation\ModerationCategory;
use RespectifyScoper\Respectify\Moderation\ModerationTolerances;
use RespectifyScoper\Respectify\Moderation\ToleranceLevel;
use RespectifyScoper\Respectify\Schemas\CommentScore;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * The default comment health settings: every item at 'standard'.
 *
 * @return array<string, string> Map of every health item to 'standard'.
 */
function respectify_default_comment_health_settings(): array {
    return array_fill_keys(HealthItem::ALL, ToleranceLevel::STANDARD);
}


/**
 * Whether a level may be saved for a health item. Mirrors the API's rules: the item and level must be known, and
 * floored categories can never be 'permissive', 'relaxed' or 'off'.
 *
 * @param mixed $item The health item, eg 'insult' or 'quality' (untrusted input).
 * @param mixed $level The candidate level (untrusted input).
 * @return bool True if the level is allowed for that item.
 */
function respectify_is_allowed_comment_health_level($item, $level): bool {
    if (!is_string($item) || !is_string($level) || !HealthItem::isValid($item)) {
        return false;
    }
    return in_array($level, HealthItem::allowedLevels($item), true);
}


/**
 * The saved comment health settings, with every item present. A missing or invalid saved entry (including a
 * floored category somehow saved as 'permissive', 'relaxed' or 'off') reads as 'standard'.
 *
 * @return array<string, string> Map of every health item to its level, in the API's order.
 */
function respectify_get_comment_health_settings(): array {
    $saved = get_option(OPTION_TOLERANCES, array());
    if (!is_array($saved)) {
        $saved = array();
    }

    $settings = respectify_default_comment_health_settings();
    foreach (array_keys($settings) as $item) {
        if (isset($saved[$item]) && respectify_is_allowed_comment_health_level($item, $saved[$item])) {
            $settings[$item] = $saved[$item];
        }
    }
    return $settings;
}


/**
 * Sanitize submitted comment health levels against the current ones. Only known items are kept, each with an
 * allowed level. A disallowed value keeps that item's current level, so a bad submission never relaxes an item.
 *
 * @param mixed $input The submitted value (untrusted).
 * @param array<string, string> $current The current settings, from respectify_get_comment_health_settings().
 * @return array{levels: array<string, string>, rejected: array<int, string>} The sanitized levels for every item,
 *     and the items whose submitted level was rejected.
 */
function respectify_sanitize_comment_health_levels($input, array $current): array {
    $levels = $current;
    $rejected = array();
    if (!is_array($input)) {
        return array('levels' => $levels, 'rejected' => $rejected);
    }

    foreach (array_keys($levels) as $item) {
        if (!array_key_exists($item, $input)) {
            continue;
        }
        if (respectify_is_allowed_comment_health_level($item, $input[$item])) {
            $levels[$item] = $input[$item];
        } else {
            $rejected[] = $item;
        }
    }
    return array('levels' => $levels, 'rejected' => $rejected);
}


/**
 * The tolerances to send with each evaluation: every health item's level. Nothing is stored per API key, so all ten
 * are sent, including those at 'standard'.
 *
 * @return ModerationTolerances|null The tolerances, or null if the bundled library rejects them (then the API
 *     judges every item at 'standard').
 */
function respectify_build_comment_health_tolerances(): ?ModerationTolerances {
    try {
        return new ModerationTolerances(respectify_get_comment_health_settings());
    } catch (BadRequestException $e) {
        // Saved values are validated on save and again on read, so this indicates a mismatch between the plugin and
        // the bundled library. The library's message names only items and levels, never comment content.
        respectify_log('Respectify: sending no comment health levels; the library rejected them: ' . respectify_describe_exception($e));
        return null;
    }
}


/**
 * Migrate the comment health settings of plugin versions before 0.4.0, once.
 *
 * Before 0.4.0 health was set by a minimum overall score, a checkbox per kind of finding, a toxicity threshold and
 * (in 0.3.0) a tolerance level per category. They become one level per item:
 * - the minimum score becomes the quality level: 1-2 relaxed, 3 standard, 4 strict, 5 zero_tolerance;
 * - each category keeps its old tolerance level;
 * - a check that was switched off turns its items off, where the item allows it: low effort -> low_effort,
 *   logical fallacies -> logical_fallacy, objectionable phrases -> insult and profanity, negative tone ->
 *   dismissive_tone. Floored categories are never turned off;
 * - the toxicity checkbox and threshold have no equivalent: the API's exceeds_tolerance accounts for toxicity.
 * The old option is left in place, unused, so a downgrade to an earlier version still finds it.
 */
function respectify_migrate_comment_health_settings(): void {
    if ((int) get_option(OPTION_SETTINGS_SCHEMA_VERSION, 0) >= SETTINGS_SCHEMA_COMMENT_HEALTH) {
        return;
    }

    // Old category tolerances carry over: every 0.3.0 level is still valid
    $levels = respectify_get_comment_health_settings();

    $old_revise_settings = get_option(OPTION_REVISE_SETTINGS, null);
    if (is_array($old_revise_settings)) {
        $levels = respectify_comment_health_levels_from_revise_settings($old_revise_settings, $levels);
    }

    update_option(OPTION_TOLERANCES, $levels);
    update_option(OPTION_SETTINGS_SCHEMA_VERSION, SETTINGS_SCHEMA_COMMENT_HEALTH);
    respectify_log('Respectify: migrated comment health settings to schema ' . SETTINGS_SCHEMA_COMMENT_HEALTH);
}


/**
 * Apply the pre-0.4.0 revise settings to a set of comment health levels. See respectify_migrate_comment_health_settings().
 *
 * @param array<mixed> $old_revise_settings The saved OPTION_REVISE_SETTINGS. The settings form saved only ticked
 *     checkboxes, so a missing checkbox key means it was switched off.
 * @param array<string, string> $levels The levels to start from, for every item.
 * @return array<string, string> The migrated levels.
 */
function respectify_comment_health_levels_from_revise_settings(array $old_revise_settings, array $levels): array {
    $min_score = $old_revise_settings['min_score'] ?? null;
    if (is_numeric($min_score)) {
        $quality_levels = array(
            1 => ToleranceLevel::RELAXED,
            2 => ToleranceLevel::RELAXED,
            3 => ToleranceLevel::STANDARD,
            4 => ToleranceLevel::STRICT,
            5 => ToleranceLevel::ZERO_TOLERANCE,
        );
        $levels[HealthItem::QUALITY] = $quality_levels[(int) $min_score] ?? ToleranceLevel::STANDARD;
    }

    $items_by_old_checkbox = array(
        'low_effort' => array(ModerationCategory::LOW_EFFORT),
        'logical_fallacies' => array(ModerationCategory::LOGICAL_FALLACY),
        'objectionable_phrases' => array(ModerationCategory::INSULT, ModerationCategory::PROFANITY),
        'negative_tone' => array(ModerationCategory::DISMISSIVE_TONE),
    );
    foreach ($items_by_old_checkbox as $checkbox => $items) {
        if (!empty($old_revise_settings[$checkbox])) {
            continue;
        }
        foreach ($items as $item) {
            if (respectify_is_allowed_comment_health_level($item, ToleranceLevel::OFF)) {
                $levels[$item] = ToleranceLevel::OFF;
            }
        }
    }
    return $levels;
}


/**
 * A summary of a comment's health result to keep with the comment: the verdict and each item's score, minimum and
 * whether it was acceptable. It holds numbers, booleans and item names only, never comment text.
 *
 * @param CommentScore $comment_score The comment score from the API.
 * @return array{exceeds_tolerance: bool, health_score: float|null, overall_score: int, items: array<string, array{score: int, minimum: int, is_acceptable: bool}>}
 */
function respectify_comment_health_summary(CommentScore $comment_score): array {
    $items = array();
    $health_items = isset($comment_score->healthItems) && is_array($comment_score->healthItems) ? $comment_score->healthItems : array();
    // Item names come from a fixed whitelist, in the API's order, so nothing from the response is stored as a key
    foreach (HealthItem::ALL as $item) {
        if (!isset($health_items[$item])) {
            continue;
        }
        $items[$item] = array(
            'score' => (int) $health_items[$item]->score,
            'minimum' => (int) $health_items[$item]->minimum,
            'is_acceptable' => (bool) $health_items[$item]->isAcceptable,
        );
    }

    return array(
        'exceeds_tolerance' => (bool) $comment_score->exceedsTolerance,
        'health_score' => isset($comment_score->healthScore) ? (float) $comment_score->healthScore : null,
        'overall_score' => (int) $comment_score->overallScore,
        'items' => $items,
    );
}


/**
 * Whether a health item was scored and found below its minimum.
 *
 * @param CommentScore $comment_score The comment score from the API.
 * @param string $item A HealthItem value.
 * @return bool True if the item was reported as not acceptable. False if it was acceptable, off, or not reported.
 */
function respectify_is_health_item_unacceptable(CommentScore $comment_score, string $item): bool {
    $health_items = isset($comment_score->healthItems) && is_array($comment_score->healthItems) ? $comment_score->healthItems : array();
    return isset($health_items[$item]) && !$health_items[$item]->isAcceptable;
}
