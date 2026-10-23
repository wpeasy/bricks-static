<?php
/**
 * GitHub API: authentication / permission failure.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * GitHub answered 401, or 403 for a reason other than a rate limit: a bad or
 * expired token, a token without access to this repository, or one missing
 * the Contents: Read and write permission. Not retryable.
 */
final class GitHubAuthException extends \RuntimeException {
}
