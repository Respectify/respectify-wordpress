<?php
/**
 * Minimal stand-ins for respectify-php 0.6.0's Moderation classes, used ONLY while vendor/ still holds 0.5.0
 * (see scoped-library-autoloader.php). Constants mirror the 0.6.0 library. Delete this file once 0.6.0 is
 * tagged and installed with `composer update respectify/respectify-php`.
 */

namespace RespectifyScoper\Respectify\Moderation;


final class ModerationCategory {

    public const THREAT = 'threat';
    public const IDENTITY_ATTACK = 'identity_attack';
    public const SEXUAL_CONTENT = 'sexual_content';
    public const HARASSMENT = 'harassment';
    public const INSULT = 'insult';
    public const PROFANITY = 'profanity';
    public const DISMISSIVE_TONE = 'dismissive_tone';
    public const LOGICAL_FALLACY = 'logical_fallacy';
    public const LOW_EFFORT = 'low_effort';

    public const ALL = [
        self::THREAT,
        self::IDENTITY_ATTACK,
        self::SEXUAL_CONTENT,
        self::HARASSMENT,
        self::INSULT,
        self::PROFANITY,
        self::DISMISSIVE_TONE,
        self::LOGICAL_FALLACY,
        self::LOW_EFFORT,
    ];

    public const FLOORED = [
        self::THREAT,
        self::IDENTITY_ATTACK,
        self::SEXUAL_CONTENT,
        self::HARASSMENT,
    ];


    public static function isValid(string $category): bool {
        return in_array($category, self::ALL, true);
    }


    public static function isFloored(string $category): bool {
        return in_array($category, self::FLOORED, true);
    }
}


final class ToleranceLevel {

    public const RELAXED = 'relaxed';
    public const STANDARD = 'standard';
    public const STRICT = 'strict';
    public const ZERO_TOLERANCE = 'zero_tolerance';

    public const ALL = [
        self::RELAXED,
        self::STANDARD,
        self::STRICT,
        self::ZERO_TOLERANCE,
    ];


    public static function isValid(string $level): bool {
        return in_array($level, self::ALL, true);
    }
}


final class ModerationTolerances {

    /**
     * @param array<string, string> $levels Category => level for the categories being overridden.
     */
    public function __construct(private readonly array $levels = []) {
    }


    /**
     * @return array<string, string>
     */
    public function allLevels(): array {
        return $this->levels;
    }
}
