<?php
/**
 * Hosting-environment detection.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

use WPEasy\BricksStatic\System\WpCli;

defined('ABSPATH') || exit;

/**
 * Detects environments where browser-driven loopback rendering is unreliable —
 * notably Local (which serializes PHP requests on Windows), so the dashboard
 * can steer the user to the WP-CLI command instead.
 */
final class Environment {

    /**
     * Whether this looks like a Local by Flywheel (or similar) dev environment.
     */
    public static function is_local(): bool {
        $local = false;

        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local') {
            $local = true;
        }

        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        if (preg_match('/\.local$/i', $host)) {
            $local = true;
        }

        if (strpos(wp_normalize_path((string) ABSPATH), '/Local Sites/') !== false) {
            $local = true;
        }

        $software = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
        if (strpos($software, 'flywheel') !== false) {
            $local = true;
        }

        /**
         * Filters whether the environment is treated as a local dev host.
         *
         * @param bool $local Detected value.
         */
        return (bool) apply_filters('bs_is_local', $local);
    }

    /**
     * The WP-CLI command users should run for a reliable sync.
     */
    public static function cli_command(): string {
        return 'wp bricks-static sync';
    }

    /**
     * WP-CLI availability for the dashboard, via full cross-environment
     * detection (system PATH or Local's bundled runtime).
     *
     * @return array{detected:bool,version:string,runtime:string}
     */
    public static function wp_cli(): array {
        $status = WpCli::status();

        return [
            'detected' => $status['available'] === true,
            'version'  => (string) ($status['version'] ?? ''),
            'runtime'  => is_array($status['runtime'] ?? null) ? (string) ($status['runtime']['type'] ?? '') : '',
        ];
    }
}

