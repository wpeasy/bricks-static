<?php
/**
 * Filesystem paths for the staging cache.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

defined('ABSPATH') || exit;

/**
 * Resolves and prepares the local staging cache directory.
 *
 * Rendered HTML, gzip siblings, mirrored assets, and the current-render manifest
 * live here. It is regenerable and must never hold durable state (settings, the
 * pushed manifest) — those live in the database so a third-party cache purge of
 * wp-content/cache cannot cause drift.
 */
final class Paths {

    /**
     * Memoised resolved cache directory (no trailing slash).
     *
     * @var string|null
     */
    private static ?string $resolved = null;

    /**
     * Absolute path to the staging cache directory (no trailing slash).
     */
    public static function cache_dir(): string {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        self::$resolved = untrailingslashit(wp_normalize_path(self::resolve()));

        return self::$resolved;
    }

    /**
     * Create the cache directory (and access guards) if needed.
     *
     * @return bool True if the directory exists and is writable.
     */
    public static function ensure(): bool {
        $dir = self::cache_dir();

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return false;
        }

        self::write_guards($dir);

        return wp_is_writable($dir);
    }

    /**
     * Whether the cache directory is (or can be made) writable.
     */
    public static function is_ready(): bool {
        $dir = self::cache_dir();

        return is_dir($dir) ? wp_is_writable($dir) : wp_is_writable(dirname($dir));
    }

    /**
     * Resolve the cache directory: constant → filter → wp-content/cache, with a
     * fallback to uploads/ when wp-content is not writable.
     */
    private static function resolve(): string {
        if (defined('BS_CACHE_DIR') && BS_CACHE_DIR) {
            /** This filter is documented below. */
            return (string) apply_filters('bs_cache_dir', (string) BS_CACHE_DIR);
        }

        $dir = trailingslashit(WP_CONTENT_DIR) . 'cache/bricks-static';

        $content_ok = wp_is_writable(WP_CONTENT_DIR)
            || is_dir(trailingslashit(WP_CONTENT_DIR) . 'cache');

        if (!$content_ok) {
            $uploads = wp_upload_dir();
            if (empty($uploads['error'])) {
                $dir = trailingslashit($uploads['basedir']) . 'bricks-static';
            }
        }

        /**
         * Filters the absolute path to the staging cache directory.
         *
         * @param string $dir Resolved cache directory.
         */
        return (string) apply_filters('bs_cache_dir', $dir);
    }

    /**
     * Drop in guards so the staging dir is not served or directory-listed.
     *
     * @param string $dir Cache directory.
     */
    private static function write_guards(string $dir): void {
        $index = $dir . '/index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '');
        }

        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
    }
}
