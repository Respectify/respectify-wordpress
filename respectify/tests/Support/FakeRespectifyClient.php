<?php

namespace Respectify\Tests\Support;

use React\Promise\PromiseInterface;
use RespectifyScoper\Respectify\RespectifyClientAsync;

use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * Stands in for the Respectify API client so no network call is made. Each call settles immediately with the
 * outcome the test configured: a value to resolve with, or a Throwable to reject with.
 *
 * Parameters are left untyped so the override stays compatible with the library's signature.
 */
final class FakeRespectifyClient extends RespectifyClientAsync {

    /** @var array<int, string> Comment text sent to megacall, in call order. */
    public array $megacall_comments = [];

    /** @var array<int, array<int, string>> Services requested from megacall, in call order. */
    public array $megacall_services = [];

    /** @var array<int, mixed> The tolerances argument passed to megacall, in call order. */
    public array $megacall_tolerances = [];

    /** @var array<int, string> Article text sent to initTopicFromText, in call order. */
    public array $init_topic_texts = [];


    /**
     * The parent constructor is deliberately not called: it would build a real HTTP client.
     *
     * @param mixed $megacall_outcome A MegaCallResult to resolve with, or a Throwable to reject with.
     * @param mixed $init_topic_outcome An article ID string to resolve with, or a Throwable to reject with.
     */
    public function __construct(
        private readonly mixed $megacall_outcome = null,
        private readonly mixed $init_topic_outcome = 'test-article-context-id'
    ) {
    }


    public function initTopicFromText($text, $topicDescription = null): PromiseInterface {
        $this->init_topic_texts[] = $text;
        return self::settle($this->init_topic_outcome);
    }


    public function megacall(
        $comment,
        $articleContextId = null,
        $services = ['spam', 'relevance', 'commentscore'],
        $bannedTopics = null,
        $replyToComment = null,
        $sensitiveTopics = null,
        $dogwhistleExamples = null,
        $tolerances = null
    ): PromiseInterface {
        $this->megacall_comments[] = $comment;
        $this->megacall_services[] = $services;
        $this->megacall_tolerances[] = $tolerances;
        return self::settle($this->megacall_outcome);
    }


    public function run(): void {
        // Promises settle synchronously; there is no event loop to run.
    }


    private static function settle(mixed $outcome): PromiseInterface {
        return $outcome instanceof \Throwable ? reject($outcome) : resolve($outcome);
    }
}
