<?php

namespace Respectify\Tests\Support;

/**
 * Thrown by the stubs for WordPress functions that end the request (wp_die, wp_send_json_*), so a test can
 * observe the response instead of the PHP process exiting.
 */
final class TerminatedRequest extends \RuntimeException {

    /**
     * @param string $terminating_function The WordPress function that ended the request.
     * @param mixed $response_payload What that function was asked to send.
     */
    public function __construct(
        public readonly string $terminating_function,
        public readonly mixed $response_payload
    ) {
        parent::__construct("Request terminated by {$terminating_function}");
    }
}
