<?php
/**
 * Connector: no receiver is running at the destination URL.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * The URL answered, but not with our receiver (404, served as text, error
 * page). A definitive misconfiguration — not worth retrying until fixed.
 */
final class ConnectorHostException extends \RuntimeException {
}
