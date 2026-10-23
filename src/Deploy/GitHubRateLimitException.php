<?php
/**
 * GitHub API: rate limited.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * GitHub's primary (requests per hour) or secondary (content-creating
 * requests per minute/hour) limit was hit. Carries when to try again, so a
 * resumable deploy can pause instead of failing.
 */
final class GitHubRateLimitException extends \RuntimeException {

    /**
     * Unix time after which requests may resume.
     */
    private int $wait_until;

    /**
     * @param string $message    Plain-text message.
     * @param int    $wait_until Unix time to resume at.
     */
    public function __construct(string $message, int $wait_until) {
        parent::__construct($message);
        $this->wait_until = $wait_until;
    }

    /**
     * Unix time after which requests may resume.
     */
    public function wait_until(): int {
        return $this->wait_until;
    }
}
