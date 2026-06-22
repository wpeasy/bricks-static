<?php
/**
 * Spawns the WP-CLI runner as a detached background process.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\CLI;

use WPEasy\BricksStatic\Support\Paths;
use WPEasy\BricksStatic\System\WpCli;

defined('ABSPATH') || exit;

/**
 * Lets the dashboard run a sync via WP-CLI (no web-worker contention) whenever
 * WP-CLI is reachable — including Local by Flywheel, whose bundled php.exe +
 * wp-cli.phar are resolved by WpCli. A separate process renders the pages while
 * the browser polls status. Falls back to browser-driven curl when WP-CLI is
 * genuinely unavailable.
 */
final class Background {

    /**
     * Whether we can spawn a WP-CLI process on this host.
     */
    public static function can_spawn(): bool {
        $available = WpCli::status()['available'] === true;

        /**
         * Filters whether the dashboard may auto-run sync via WP-CLI.
         *
         * @param bool $can Whether spawning is allowed.
         */
        return (bool) apply_filters('bs_can_spawn_cli', $available);
    }

    /**
     * Spawn `wp bricks-static run` (detached) to drive the existing job.
     *
     * @return bool True if the spawn was attempted.
     */
    public static function spawn_run(): bool {
        if (!self::can_spawn()) {
            return false;
        }

        $command = WpCli::build_command('bricks-static run --path=' . escapeshellarg(rtrim((string) ABSPATH, '/\\')));
        if ($command === null) {
            return false;
        }

        return self::spawn_detached($command);
    }

    /**
     * Launch a command detached so the web request returns immediately.
     *
     * @param string $command Fully-formed shell command.
     */
    private static function spawn_detached(string $command): bool {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: wrap the (heavily-quoted) command in a .bat so START gets
            // a single clean argument — nested quotes break START otherwise.
            $bat = Paths::cache_dir() . '/.bs-run.bat';
            if (!Paths::ensure() || file_put_contents($bat, "@echo off\r\n" . $command . " > NUL 2>&1\r\n") === false) {
                return false;
            }
            $bat_win = str_replace('/', '\\', $bat);

            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_popen -- spawns the background WP-CLI sync runner; the command is built from internal paths, not request input.
            $handle = @popen('start "bricks-static" /B "' . $bat_win . '"', 'r');
            if (is_resource($handle)) {
                pclose($handle);
                return true;
            }
            return false;
        }

        if (function_exists('exec')) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- spawns the background WP-CLI sync runner; the command is built internally, not from request input.
            @exec('nohup ' . $command . ' > /dev/null 2>&1 &');
            return true;
        }

        return false;
    }
}
