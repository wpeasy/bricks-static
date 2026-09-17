<?php
/**
 * Connector: protocol version mismatch.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * The receiver speaks a different protocol version. Fix = re-download.
 */
final class ConnectorVersionException extends \RuntimeException {
}
