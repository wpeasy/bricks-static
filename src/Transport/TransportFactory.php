<?php
/**
 * Transport factory + capability detection.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Transport;

use WPEasy\BricksStatic\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Builds a transport from a config array and reports which transports the
 * current runtime can use.
 */
final class TransportFactory {

    /**
     * Build a transport for the given config (defaults to the saved settings).
     *
     * @param array<string,mixed>|null $config Connection config, or null to use saved settings.
     */
    public static function make(?array $config = null): TransportInterface {
        $config = $config ?? Settings::connection_config();

        /**
         * Filters the transport instance, allowing a custom implementation
         * (e.g. a local filesystem transport for testing).
         *
         * @param TransportInterface|null $transport Override, or null for default.
         * @param array<string,mixed>     $config    Connection config.
         */
        $override = apply_filters('bs_transport', null, $config);
        if ($override instanceof TransportInterface) {
            return $override;
        }

        switch ($config['transport'] ?? 'sftp') {
            case 'ftps':
                return new FtpTransport($config, true);
            case 'ftp':
                return new FtpTransport($config, false);
            case 'sftp':
            default:
                return new SftpTransport($config);
        }
    }

    /**
     * Which transports are usable on this server.
     *
     * @return array{sftp:bool,ftps:bool,ftp:bool}
     */
    public static function capabilities(): array {
        return [
            'sftp' => SftpTransport::is_available(),
            'ftps' => FtpTransport::is_secure_available(),
            'ftp'  => FtpTransport::is_available(),
        ];
    }
}
