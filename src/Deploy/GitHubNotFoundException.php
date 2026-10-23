<?php
/**
 * GitHub API: not found.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * GitHub answered 404 — the repository, branch or object doesn't exist (or the
 * token can't see a private repository, which GitHub also reports as 404).
 */
final class GitHubNotFoundException extends \RuntimeException {
}
