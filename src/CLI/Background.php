<?php
/**
 * Spawns the WP-CLI runner as a detached background process.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\CLI;

use WPEasy\BricksStatic\Support\Environment;

defined('ABSPATH') || exit;

/**
 * Lets the dashboard run a sync via WP-CLI (no web-worker contention) when the
 * host supports it: a separate process renders the pages while the browser just
 * polls status. Falls back to browser-driven curl when unavailable.
 */
final class Background {

    /**
     * Whether we can spawn a detached WP-CLI process on this host.
     *
     * Requires a POSIX shell (not Windows/Local — there we steer users to run
     * the command manually), exec(), and a `wp` binary on the web PATH.
     */
    public static function can_spawn(): bool {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false;
        }

        if (!Environment::exec_available()) {
            return false;
        }

        $info = Environment::wp_cli();

        /**
         * Filters whether the dashboard may auto-run sync via a spawned WP-CLI
         * process. Return false to always use the browser-driven path.
         *
         * @param bool $can Whether spawning is allowed.
         */
        return (bool) apply_filters('bs_can_spawn_cli', !empty($info['detected']));
    }

    /**
     * Spawn `wp bricks-static run` to drive the existing job to completion.
     *
     * @return bool True if the spawn was attempted.
     */
    public static function spawn_run(): bool {
        if (!self::can_spawn()) {
            return false;
        }

        $command = 'wp bricks-static run --path=' . escapeshellarg(rtrim((string) ABSPATH, '/\\'));

        // Detached + non-blocking so the web request returns immediately.
        @exec('nohup ' . $command . ' > /dev/null 2>&1 &');

        return true;
    }
}
