<?php

namespace Respectify;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

const RESPECTIFY_VERSION = '0.4.1';

const OPTION_EMAIL = 'respectify_email';
const OPTION_API_KEY_ENCRYPTED = 'respectify_api_key_encrypted';
const OPTION_SPAM_HANDLING = 'respectify_spam_handling';
// Before 0.4.0: minimum score, per-check checkboxes and toxicity threshold. Read only to migrate it to OPTION_TOLERANCES.
const OPTION_REVISE_SETTINGS = 'respectify_revise_settings';
const OPTION_RELEVANCE_SETTINGS = 'respectify_relevance_settings';
const OPTION_BANNED_TOPICS = 'respectify_banned_topics';
const OPTION_ASSESSMENT_SETTINGS = 'respectify_assessment_settings';
const OPTION_DOGWHISTLE_SETTINGS = 'respectify_dogwhistle_settings';
const OPTION_SENSITIVE_TOPICS = 'respectify_sensitive_topics';
const OPTION_DOGWHISTLE_EXAMPLES = 'respectify_dogwhistle_examples';
const OPTION_BASE_URL = 'respectify_base_url';
const OPTION_API_VERSION = 'respectify_api_version';
// Comment health: one level per health item (the nine problem categories and quality), item => level. Items and
// levels come from the bundled respectify-php library (Moderation\HealthItem and Moderation\ToleranceLevel).
// Default is all 'standard'. Sent in full with every evaluation: nothing is stored per API key.
const OPTION_TOLERANCES = 'respectify_tolerances';
// Which settings migrations have run. Missing means a settings layout from before 0.4.0.
const OPTION_SETTINGS_SCHEMA_VERSION = 'respectify_settings_schema_version';
// Settings schema with comment health levels (0.4.0): OPTION_REVISE_SETTINGS has been migrated to OPTION_TOLERANCES
const SETTINGS_SCHEMA_COMMENT_HEALTH = 2;

// Action Constants
const ACTION_DELETE = 'trash';
const ACTION_REVISE = 'reject_with_feedback';
const ACTION_PUBLISH = 'post';

// Default Settings
const DEFAULT_SPAM_HANDLING = ACTION_DELETE; // Options: one of the ACTION_* constants
const ASSESSMENT_DEFAULT_SETTINGS = array(
    'assess_health' => true,
    'check_relevance' => true,
    'check_spam' => true,
    'check_dogwhistle' => true,
);

// Relevance Settings
const RELEVANCE_DEFAULT_OFF_TOPIC_HANDLING = ACTION_PUBLISH; // Default to allow off-topic comments
const RELEVANCE_DEFAULT_BANNED_TOPICS_HANDLING = ACTION_REVISE; // Default to revise for banned topics
const RELEVANCE_DEFAULT_BANNED_TOPICS_THRESHOLD = 0.1; // Default 10% threshold for banned topics

// Dogwhistle Settings
const DOGWHISTLE_DEFAULT_HANDLING = ACTION_REVISE; // Default to revise for dogwhistles
// Undesired topics modes: any mention is acted on, or only a comment at or over the threshold
const RELEVANCE_BANNED_TOPICS_MODE_ANY = 'any';
const RELEVANCE_BANNED_TOPICS_MODE_THRESHOLD = 'threshold';
const RELEVANCE_DEFAULT_BANNED_TOPICS_MODE = RELEVANCE_BANNED_TOPICS_MODE_ANY; // Default: any mention triggers action

// One-per-line lists (undesired topics, sensitive topics, dogwhistle examples): the Respectify API reads at most this
// many entries of each, each at most this many characters (input_limits.MAX_PROMPT_LIST_ENTRIES and
// MAX_PROMPT_LIST_ENTRY_LENGTH), so the settings page saves no more than that
const TOPIC_LIST_MAX_ENTRIES = 20;
const TOPIC_LIST_MAX_ENTRY_LENGTH = 5000;

const RELEVANCE_DEFAULT_SETTINGS = array(
    'off_topic_handling' => RELEVANCE_DEFAULT_OFF_TOPIC_HANDLING,
    'banned_topics_handling' => RELEVANCE_DEFAULT_BANNED_TOPICS_HANDLING,
    'banned_topics_threshold' => RELEVANCE_DEFAULT_BANNED_TOPICS_THRESHOLD,
    'banned_topics_mode' => RELEVANCE_DEFAULT_BANNED_TOPICS_MODE,
);

