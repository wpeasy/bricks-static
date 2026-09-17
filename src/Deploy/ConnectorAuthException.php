<?php
/**
 * Connector: request rejected by the receiver's auth (wrong pairing / locked).
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * The receiver refused our credentials: the file on the destination was paired
 * with a different secret (or none), or it is rate-locked. Fix = re-pair.
 */
final class ConnectorAuthException extends \RuntimeException {
}
