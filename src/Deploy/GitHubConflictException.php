<?php
/**
 * GitHub API: the branch moved.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * Moving the branch was refused because it is not a fast-forward: someone
 * pushed to it after the deploy read it. The deploy re-reads the branch and
 * tries again once; it never force-pushes.
 */
final class GitHubConflictException extends \RuntimeException {
}
