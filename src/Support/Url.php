<?php
/**
 * URL helpers: origins, internal detection, absolutising, and URL→file mapping.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

use WPEasy\BricksStatic\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Pure URL utilities shared by discovery, rendering, and rewriting.
 */
final class Url {

    /**
     * The site's home sub-path — the path component of home_url(), normalised to
     * a leading-slash prefix with no trailing slash. Empty string when the site is
     * installed at the domain root.
     *
     * On this platform the dev host mounts each site under /<name>/, so home_url()
     * carries that prefix; it must be stripped from the static output (which is
     * served from the destination's own root, or its configured sub-path).
     */
    public static function home_base(): string {
        $path = (string) (wp_parse_url((string) home_url(), PHP_URL_PATH) ?? '');
        $path = '/' . trim($path, '/');

        return $path === '/' ? '' : $path;
    }

    /**
     * The configured "Served from sub-path" (basePath) — where the destination
     * serves the static site from — normalised to "" (domain root) or "/sub"
     * (leading slash, no trailing slash).
     */
    public static function base_path(): string {
        $base = '/' . trim((string) Settings::get('basePath'), '/');

        return $base === '/' ? '' : $base;
    }

    /**
     * Remap a site path from the source home sub-path to the configured base path,
     * so static output is rooted where the destination actually serves it. With the
     * default base ("/") this simply strips the dev home prefix:
     * "/test-for-vid/about/" → "/about/". With a base of "/blog" it becomes
     * "/blog/about/". A path outside the home sub-path is returned unchanged (bar
     * the base prefix).
     *
     * @param string $path A leading-slash URL path (no scheme/host).
     */
    public static function remap_site_path(string $path): string {
        $home = self::home_base();
        if ($home !== '' && ($path === $home || strpos($path, $home . '/') === 0)) {
            $path = substr($path, strlen($home));
        }

        $path = '/' . ltrim($path, '/');
        $base = self::base_path();

        return $base === '' ? $path : $base . ($path === '/' ? '/' : $path);
    }

    /**
     * Scheme+host origin prefixes for this site (http/https, www/non-www,
     * protocol-relative), used to detect and strip the source origin.
     *
     * @return array<int,string>
     */
    public static function origins(): array {
        $out = [];

        foreach (array_unique([home_url(), site_url()]) as $base) {
            $host = wp_parse_url($base, PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                continue;
            }

            $hosts = [$host];
            $hosts[] = strpos($host, 'www.') === 0 ? substr($host, 4) : 'www.' . $host;

            foreach (array_unique($hosts) as $candidate) {
                $out[] = 'https://' . $candidate;
                $out[] = 'http://' . $candidate;
                $out[] = '//' . $candidate;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Whether a URL points at this site (or is a site-relative path).
     *
     * @param string $url URL or path.
     */
    public static function is_internal(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        foreach (['#', 'mailto:', 'tel:', 'data:', 'javascript:'] as $skip) {
            if (stripos($url, $skip) === 0) {
                return false;
            }
        }

        if (strpos($url, '//') === 0) {
            $host = wp_parse_url('https:' . $url, PHP_URL_HOST);
        } elseif (preg_match('#^https?://#i', $url)) {
            $host = wp_parse_url($url, PHP_URL_HOST);
        } else {
            return true; // Relative or root-relative path → internal.
        }

        $self = wp_parse_url(home_url(), PHP_URL_HOST);

        return is_string($host) && self::normalize_host($host) === self::normalize_host((string) $self);
    }

    /**
     * Resolve a (possibly relative) URL against a base URL into an absolute URL.
     * Fragments are dropped. Returns '' if it cannot be resolved.
     *
     * @param string $url  URL or path found in markup.
     * @param string $base Absolute URL of the document it was found in.
     */
    public static function absolutize(string $url, string $base): string {
        $url = trim($url);
        $hash = strpos($url, '#');
        if ($hash !== false) {
            $url = substr($url, 0, $hash);
        }
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (strpos($url, '//') === 0) {
            return (wp_parse_url($base, PHP_URL_SCHEME) ?: 'https') . ':' . $url;
        }

        $parts = wp_parse_url($base);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $root = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (strpos($url, '/') === 0) {
            return $root . $url;
        }

        // Relative to the base directory.
        $base_path = isset($parts['path']) ? $parts['path'] : '/';
        $dir = (substr($base_path, -1) === '/') ? $base_path : dirname($base_path) . '/';

        return $root . self::collapse_dots($dir . $url);
    }

    /**
     * Map a URL (or path) to a relative file path under the cache root.
     *
     * Directory-style URLs (trailing slash or no extension) become index.html;
     * file-style URLs keep their path. The query string is ignored. Returns
     * null if the URL carries a query string for a page (cannot be made static).
     *
     * @param string $url URL or path.
     * @return string|null Relative file path, or null if unmappable.
     */
    public static function to_relative_path(string $url): ?string {
        $path  = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '');
        $query = wp_parse_url($url, PHP_URL_QUERY);
        $path  = rawurldecode($path);
        $path  = self::remap_site_path($path);

        if ($path === '' || $path === '/') {
            return 'index.html';
        }

        $path     = ltrim($path, '/');
        $basename = basename($path);

        // Directory style → index.html.
        if (substr($path, -1) === '/' || strpos($basename, '.') === false) {
            return rtrim($path, '/') . '/index.html';
        }

        // File style (has an extension). A query string on a real file is fine
        // to ignore (e.g. style.css?ver=1); on an "html" page it is not static.
        unset($query);

        return $path;
    }

    /**
     * Resolve an internal URL to its WordPress post id.
     *
     * Wraps `url_to_postid()`, but also resolves the static front page and the
     * "Posts page" — both serve archives, so `url_to_postid()` returns 0 for them
     * even though each is a real, toggleable Page. Returns 0 when no post matches.
     *
     * @param string $url Internal URL.
     */
    public static function to_post_id(string $url): int {
        $id = (int) url_to_postid($url);
        if ($id > 0) {
            return $id;
        }

        $rel = self::to_relative_path($url);
        if ($rel === null) {
            return 0;
        }

        foreach (['page_on_front', 'page_for_posts'] as $option) {
            $pid = (int) get_option($option);
            if ($pid > 0 && self::to_relative_path((string) get_permalink($pid)) === $rel) {
                return $pid;
            }
        }

        return 0;
    }

    /**
     * Lower-case a host with any leading "www." removed.
     *
     * @param string $host Hostname.
     */
    private static function normalize_host(string $host): string {
        return preg_replace('#^www\.#i', '', strtolower($host)) ?? strtolower($host);
    }

    /**
     * Collapse ./ and ../ segments in a path.
     *
     * @param string $path Path possibly containing dot segments.
     */
    private static function collapse_dots(string $path): string {
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return '/' . implode('/', $out);
    }
}
