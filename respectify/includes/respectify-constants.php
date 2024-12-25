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

const REVISE_DEFAULT_SETTINGS = array (
    'min_score'             => REVISE_DEFAULT_MIN_SCORE,
    'low_effort'            => REVISE_DEFAULT_LOW_EFFORT,
    'logical_fallacies'     => REVISE_DEFAULT_LOGICAL_FALLACIES,
    'objectionable_phrases' => REVISE_DEFAULT_OBJECTIONABLE_PHRASES,
    'negative_tone'         => REVISE_DEFAULT_NEGATIVE_TONE,    
);

