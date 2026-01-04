<?php
/**
 * PHPStan stubs for RespectifyScoper classes.
 * These classes are created by PHP-Scoper at build time.
 * This stub file tells PHPStan they exist so it can type-check our code.
 */

namespace RespectifyScoper\Respectify;

class RespectifyClientAsync {
    public function __construct(string $email, string $apiKey, ?string $baseUrl = null, ?float $version = null, ?string $website = null) {}
    public function run(): void {}
    public function initTopicFromText(string $text): \React\Promise\PromiseInterface {}
    public function megacall(
        string $comment,
        ?string $articleContextId = null,
        ?array $services = null,
        ?array $bannedTopics = null,
        ?string $replyToComment = null,
        ?array $sensitiveTopics = null,
        ?array $dogwhistleExamples = null
    ): \React\Promise\PromiseInterface {}
    public function checkUserCredentials(): \React\Promise\PromiseInterface {}
}

namespace RespectifyScoper\Respectify\Schemas;

class MegaCallResult {
    public ?CommentScore $commentScore;
    public ?SpamDetectionResult $spamCheck;
    public ?CommentRelevanceResult $relevanceCheck;
    public ?DogwhistleResult $dogwhistleCheck;
    public function __construct(array $data) {}
}

class CommentScore {
    public array $logicalFallacies;
    public array $objectionablePhrases;
    public array $negativeTonePhrases;
    public bool $appearsLowEffort;
    public int $overallScore;
    public float $toxicityScore;
    public string $toxicityExplanation;
    public function __construct(array $data) {}
}

class SpamDetectionResult {
    public string $reasoning;
    public bool $isSpam;
    public float $confidence;
    public function __construct(array $data) {}
}

class CommentRelevanceResult {
    public OnTopicResult $onTopic;
    public BannedTopicsResult $bannedTopics;
    public function __construct(array $data) {}
}

class OnTopicResult {
    public string $reasoning;
    public bool $onTopic;
    public float $confidence;
    public function __construct(array $data) {}
}

class BannedTopicsResult {
    public string $reasoning;
    public array $bannedTopics;
    public float $quantityOnBannedTopics;
    public float $confidence;
    public function __construct(array $data) {}
}

class DogwhistleResult {
    public DogwhistleDetection $detection;
    public ?DogwhistleDetails $details;
    public function __construct(array $data) {}
}

class DogwhistleDetection {
    public string $reasoning;
    public bool $dogwhistlesDetected;
    public float $confidence;
    public function __construct(array $data) {}
}

class DogwhistleDetails {
    public array $dogwhistleTerms;
    public array $categories;
    public float $subtletyLevel;
    public float $harmPotential;
    public function __construct(array $data) {}
}

class LogicalFallacy {
    public string $fallacyName;
    public string $quotedLogicalFallacyExample;
    public string $explanation;
    public string $suggestedRewrite;
    public function __construct(array $data) {}
}

class ObjectionablePhrase {
    public string $quotedObjectionablePhrase;
    public string $explanation;
    public string $suggestedRewrite;
    public function __construct(array $data) {}
}

class NegativeTonePhrase {
    public string $quotedNegativeTonePhrase;
    public string $explanation;
    public string $suggestedRewrite;
    public function __construct(array $data) {}
}

class UserCheckResponse {
    public bool $active;
    public string $status;
    public ?string $expires;
    public ?string $planName;
    public ?array $allowedEndpoints;
    public ?string $error;
    public function __construct(array $data) {}
}

namespace RespectifyScoper\Respectify\Exceptions;

class RespectifyException extends \Exception {}
class UnauthorizedException extends RespectifyException {}
class PaymentRequiredException extends RespectifyException {}
class BadRequestException extends RespectifyException {}
class ServerException extends RespectifyException {}
class JsonDecodingException extends RespectifyException {}
class UnsupportedMediaTypeException extends RespectifyException {}
