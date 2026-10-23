<?php
/**
 * GitHub API: request rejected as invalid.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * GitHub answered 422 (or 409): the request was well-formed but refused —
 * e.g. a ref update that isn't a fast-forward, or a tree entry naming a blob
 * GitHub no longer has. Callers translate it into something specific.
 */
final class GitHubValidationException extends \RuntimeException {
}
