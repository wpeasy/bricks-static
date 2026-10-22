<?php
/**
 * Cloudflare: the requested resource does not exist.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * Cloudflare answered 404 — e.g. the account has no workers.dev subdomain
 * registered yet. Callers decide whether that is an error.
 */
final class CloudflareNotFoundException extends \RuntimeException {
}
