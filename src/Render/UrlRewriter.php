<?php
/**
 * Rewrites absolute source-origin URLs to root-relative.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Replaces every absolute reference to the source origin with a root-relative
 * path, so the output resolves correctly when served from the destination
 * regardless of its domain.
 *
 * Works on whole documents (HTML or CSS) so it also catches origins embedded in
 * inline scripts, JSON-LD, inline styles, and data-attributes — not just
 * href/src. The match handles JSON-escaped slashes (https:\/\/host) and
 * protocol-relative (//host) forms, leaving the path (escaped or not) intact.
 */
final class UrlRewriter {

    /**
     * Rewrite all source-origin URLs in a document to root-relative.
     *
     * @param string $content HTML or CSS.
     * @return string Rewritten content.
     */
    public static function rewrite(string $content): string {
        $search = [];

        foreach (self::source_hosts() as $host) {
            // Order matters: scheme-qualified forms before bare "//host", so the
            // latter can't chew the slashes out of the former. Both plain and
            // JSON-escaped (\/\/) slash forms are covered.
            $search[] = 'https://' . $host;
            $search[] = 'http://' . $host;
            $search[] = '//' . $host;
            $search[] = 'https:\/\/' . $host;
            $search[] = 'http:\/\/' . $host;
            $search[] = '\/\/' . $host;
        }

        // Replacing the origin with '' leaves the following path (escaped or not):
        // "https://host/about" → "/about", "https:\/\/host\/x" → "\/x".
        return str_replace($search, '', $content);
    }

    /**
     * Rewrite source-origin URLs to ABSOLUTE destination URLs.
     *
     * Used for files that must carry absolute links — sitemaps and robots.txt —
     * where the root-relative form rewrite() produces is invalid (a `<loc>` and a
     * robots `Sitemap:` line must be a full URL). Replaces each source origin with
     * the destination's origin, preserving the path and the //, http(s) and
     * JSON-escaped forms.
     *
     * @param string $content   Document text (XML or plain text).
     * @param string $dest_base Destination base URL (e.g. "https://example.com").
     * @return string Rewritten content (unchanged if $dest_base has no host).
     */
    public static function to_absolute(string $content, string $dest_base): string {
        $parts = wp_parse_url($dest_base);
        if (empty($parts['host'])) {
            return $content;
        }

        $scheme    = $parts['scheme'] ?? 'https';
        $authority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $origin    = $scheme . '://' . $authority;

        $search  = [];
        $replace = [];
        foreach (self::source_hosts() as $host) {
            // Same ordering as rewrite(): scheme-qualified before bare "//host".
            $search[] = 'https://' . $host;
            $replace[] = $origin;
            $search[] = 'http://' . $host;
            $replace[] = $origin;
            $search[] = '//' . $host;
            $replace[] = '//' . $authority;
            $search[] = 'https:\/\/' . $host;
            $replace[] = str_replace('/', '\/', $origin);
            $search[] = 'http:\/\/' . $host;
            $replace[] = str_replace('/', '\/', $origin);
            $search[] = '\/\/' . $host;
            $replace[] = '\/\/' . $authority;
        }

        return str_replace($search, $replace, $content);
    }

    /**
     * Source hostnames (with www/non-www variants), longest first.
     *
     * @return array<int,string>
     */
    public static function source_hosts(): array {
        $hosts = [];

        foreach (array_unique([home_url(), site_url()]) as $base) {
            $host = wp_parse_url($base, PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                continue;
            }
            $hosts[$host] = true;
            $hosts[strpos($host, 'www.') === 0 ? substr($host, 4) : 'www.' . $host] = true;
        }

        $list = array_keys($hosts);
        usort($list, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $list;
    }
}
