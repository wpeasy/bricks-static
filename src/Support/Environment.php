<?php
/**
 * Hosting-environment detection.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

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
     * Whether PHP can spawn processes (needed for any future auto-run of WP-CLI).
     */
    public static function exec_available(): bool {
        if (!function_exists('exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('exec', $disabled, true);
    }

    /**
     * Best-effort WP-CLI detection (cached). Detection is positive-only: a true
     * result is reliable, but false just means `wp` isn't on the web server's
     * PATH — the user may still have it in their own shell (common on Local).
     *
     * @return array{execAvailable:bool,detected:bool,version:string}
     */
    public static function wp_cli(): array {
        $cached = get_transient('bs_wpcli');
        if (is_array($cached)) {
            return $cached;
        }

        $info = ['execAvailable' => self::exec_available(), 'detected' => false, 'version' => ''];

        if ($info['execAvailable']) {
            $output = [];
            $code   = 1;
            @exec('wp --version 2>&1', $output, $code);
            $line = trim(implode(' ', $output));

            if ($code === 0 && stripos($line, 'WP-CLI') !== false) {
                $info['detected'] = true;
                $info['version']  = $line;
            }
        }

        set_transient('bs_wpcli', $info, 6 * HOUR_IN_SECONDS);

        return $info;
    }
}

