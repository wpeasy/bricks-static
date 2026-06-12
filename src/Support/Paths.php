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
     * Absolute path to the rendered output directory (no trailing slash).
     *
     * Kept in a `site/` subdirectory so the actual deliverable is isolated from
     * the staging guards (index.html / .htaccess) in the parent, and so M3 can
     * upload the whole subtree without filtering meta files.
     */
    public static function output_dir(): string {
        return self::cache_dir() . '/site';
    }

    /**
     * Create the cache directory, output subdir, and access guards if needed.
     *
     * @return bool True if the directories exist and are writable.
     */
    public static function ensure(): bool {
        $dir = self::cache_dir();

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return false;
        }

        self::write_guards($dir);

        $output = self::output_dir();
        if (!is_dir($output) && !wp_mkdir_p($output)) {
            return false;
        }

        return wp_is_writable($output);
    }

    /**
     * Whether the cache directory is (or can be made) writable.
     */
    public static function is_ready(): bool {
        $dir = self::cache_dir();

        return is_dir($dir) ? wp_is_writable($dir) : wp_is_writable(dirname($dir));
    }

    /**
     * Resolve an asset URL to its local source file on disk, if one exists.
     *
     * Lets the sync upload binary assets straight from the source filesystem
     * instead of duplicating them into the cache. Returns null for URLs that
     * don't map to an existing static file (dynamic endpoints, off-tree paths).
     *
     * @param string $url Absolute or root-relative asset URL.
     * @return string|null Absolute local file path, or null.
     */
    public static function source_file(string $url): ?string {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $path = rawurldecode($path);

        foreach (self::url_dir_map() as [$url_prefix, $dir]) {
            if ($url_prefix === '' || strpos($path, $url_prefix) !== 0) {
                continue;
            }

            $relative = ltrim(substr($path, strlen($url_prefix)), '/');
            $file     = wp_normalize_path(trailingslashit($dir) . $relative);
            $base     = wp_normalize_path(trailingslashit($dir));

            // Guard against path traversal escaping the mapped directory.
            if (strpos($file, $base) === 0 && is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * URL-path prefix → filesystem directory map, longest prefix first.
     *
     * @return array<int,array{0:string,1:string}>
     */
    private static function url_dir_map(): array {
        $uploads = wp_get_upload_dir();

        $map = [
            [self::url_path($uploads['baseurl']), $uploads['basedir']],
            [self::url_path(content_url('/')), WP_CONTENT_DIR],
            [self::url_path(includes_url('/')), ABSPATH . WPINC],
            [self::url_path(home_url('/')), untrailingslashit(ABSPATH)],
        ];

        $map = array_values(array_filter($map, static fn(array $row): bool => $row[0] !== ''));
        usort($map, static fn(array $a, array $b): int => strlen($b[0]) <=> strlen($a[0]));

        return $map;
    }

    /**
     * Path component of a URL (always with a leading slash, no trailing-only).
     *
     * @param string $url URL.
     */
    private static function url_path(string $url): string {
        $path = wp_parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
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
