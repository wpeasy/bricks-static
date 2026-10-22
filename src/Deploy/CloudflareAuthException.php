<?php
/**
 * Cloudflare: request rejected for authentication/authorisation.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * Cloudflare answered 401/403: a wrong account id, a token without the
 * Workers Scripts → Edit permission, or an expired upload JWT. Fix = check the
 * destination's Cloudflare settings (or, for a JWT, start a new session).
 */
final class CloudflareAuthException extends \RuntimeException {
}
