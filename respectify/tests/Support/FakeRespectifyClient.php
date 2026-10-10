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
 * Parameters are left untyped so the overrides stay compatible with both respectify-php 0.5.0 and 0.6.0
 * (0.6.0 adds the $tolerances parameter to megacall).
 */
final class FakeRespectifyClient extends RespectifyClientAsync {

    /** @var array<int, string> Comment text sent to megacall, in call order. */
    public array $megacall_comments = [];


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
        return self::settle($this->megacall_outcome);
    }


    public function run(): void {
        // Promises settle synchronously; there is no event loop to run.
    }


    private static function settle(mixed $outcome): PromiseInterface {
        return $outcome instanceof \Throwable ? reject($outcome) : resolve($outcome);
    }
}
