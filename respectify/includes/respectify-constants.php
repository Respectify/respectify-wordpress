<?php

namespace Respectify;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

const RESPECTIFY_VERSION = '0.9.0';

const OPTION_EMAIL = 'respectify_email';
const OPTION_API_KEY_ENCRYPTED = 'respectify_api_key_encrypted';
const OPTION_SPAM_HANDLING = 'respectify_spam_handling';
const OPTION_REVISE_SETTINGS = 'respectify_revise_settings';
const OPTION_RELEVANCE_SETTINGS = 'respectify_relevance_settings';
const OPTION_BANNED_TOPICS = 'respectify_banned_topics';
const OPTION_ASSESSMENT_SETTINGS = 'respectify_assessment_settings';

// Action Constants
const ACTION_DELETE = 'trash';
const ACTION_REVISE = 'reject_with_feedback';
const ACTION_PUBLISH = 'post';

// Default Settings
const DEFAULT_SPAM_HANDLING = ACTION_DELETE; // Options: one of the ACTION_* constants
const REVISE_DEFAULT_MIN_SCORE = 3; // Revise if lower than this
const REVISE_DEFAULT_LOW_EFFORT = true; // Revise if low effort
const REVISE_DEFAULT_LOGICAL_FALLACIES = true; // Revise if any number of logical fallacies
const REVISE_DEFAULT_OBJECTIONABLE_PHRASES = true; // Revise if any number of objectionable phrases
const REVISE_DEFAULT_NEGATIVE_TONE = true; // Revise if any number of negative tone phrases
const ASSESSMENT_DEFAULT_SETTINGS = array(
    'assess_health' => true,
    'check_relevance' => true,
    'check_spam' => true
);

// Relevance Settings
const RELEVANCE_DEFAULT_OFF_TOPIC_HANDLING = ACTION_REVISE; // Default to revise for off-topic comments
const RELEVANCE_DEFAULT_BANNED_TOPICS_HANDLING = ACTION_REVISE; // Default to revise for banned topics
const RELEVANCE_DEFAULT_BANNED_TOPICS_THRESHOLD = 0.1; // Default 10% threshold for banned topics
const RELEVANCE_DEFAULT_BANNED_TOPICS_MODE = 'any'; // Default to 'any' (any mention triggers action)

const RELEVANCE_DEFAULT_SETTINGS = array(
    'off_topic_handling' => RELEVANCE_DEFAULT_OFF_TOPIC_HANDLING,
    'banned_topics_handling' => RELEVANCE_DEFAULT_BANNED_TOPICS_HANDLING,
    'banned_topics_threshold' => RELEVANCE_DEFAULT_BANNED_TOPICS_THRESHOLD,
    'banned_topics_mode' => RELEVANCE_DEFAULT_BANNED_TOPICS_MODE,
);

const REVISE_DEFAULT_SETTINGS = array (
    'min_score'             => REVISE_DEFAULT_MIN_SCORE,
    'low_effort'            => REVISE_DEFAULT_LOW_EFFORT,
    'logical_fallacies'     => REVISE_DEFAULT_LOGICAL_FALLACIES,
    'objectionable_phrases' => REVISE_DEFAULT_OBJECTIONABLE_PHRASES,
    'negative_tone'         => REVISE_DEFAULT_NEGATIVE_TONE,    
);

