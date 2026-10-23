<?php
/**
 * Rewrites absolute source-origin URLs to root-relative.
 *
 * @package WPEasy\ShipStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Render;

use WPEasy\ShipStatic\Support\Url;

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
        // The render serves every destination, so links get a placeholder
        // for "this destination's sub-path" (Url::BASE_TOKEN), swapped per
        // destination at deploy (Url::rebase()). With WordPress at the domain
        // root it goes where the origin was: "https://host/about" →
        // "/__shipstatic_base__/about"; with a home sub-path, apply_base_path()
        // puts it in place of that sub-path instead.
        $prefix  = Url::home_base() === '' ? Url::BASE_TOKEN : '';
        $escaped = str_replace('/', '\/', $prefix);

        $search  = [];
        $replace = [];

        foreach (self::source_hosts() as $host) {
            // Order matters: scheme-qualified forms before bare "//host", so the
            // latter can't chew the slashes out of the former. Both plain and
            // JSON-escaped (\/\/) slash forms are covered.
            array_push($search, 'https://' . $host, 'http://' . $host, '//' . $host);
            array_push($replace, $prefix, $prefix, $prefix);
            array_push($search, 'https:\/\/' . $host, 'http:\/\/' . $host, '\/\/' . $host);
            array_push($replace, $escaped, $escaped, $escaped);
        }

        // Replacing the origin with '' (or the placeholder) leaves the following
        // path (escaped or not): "https://host/about" → "/about", "https:\/\/host\/x" → "\/x".
        $content = str_replace($search, $replace, $content);

        // Now swap the source home sub-path for the placeholder, so e.g.
        // "/test-for-vid/about/" becomes "/__shipstatic_base__/about/".
        return self::apply_base_path($content);
    }

    /**
     * Swap the source home sub-path (home_url()'s path, e.g. "/test-for-vid")
     * for the destination-base placeholder (Url::BASE_TOKEN) in
     * already-root-relative document URLs.
     *
     * Each occurrence must be preceded by a URL-context delimiter (quote, paren,
     * "=", comma or whitespace), so only path-initial matches are touched — an
     * external URL such as https://other.com/test-for-vid/x is left intact because
     * its sub-path is preceded by the host, not a delimiter. Handles both plain
     * ("/x") and JSON-escaped ("\/x") slash forms found in inline scripts/JSON-LD.
     *
     * @param string $content HTML or CSS with root-relative URLs.
     */
    private static function apply_base_path(string $content): string {
        $home = Url::home_base();
        if ($home === '') {
            return $content; // Site installed at domain root — nothing to strip.
        }

        $base = Url::BASE_TOKEN;
        // A path-initial URL is either at the very start of the string (\A — this
        // method also runs on standalone URLs like wp_get_attachment_url(), not just
        // documents) or preceded by a URL-context delimiter. ";" covers HTML-entity
        // quotes (e.g. url(&quot;/path…) in data-style attributes).
        $pre  = '(?:\A|(?<=[\'"(=,;\s]))';

        // [ separator, escaped home sub-path, escaped base path ] for plain and
        // JSON-escaped forms.
        $forms = [
            ['/',   $home,                          $base],
            ['\\/', str_replace('/', '\\/', $home), str_replace('/', '\\/', $base)],
        ];

        foreach ($forms as [$sep, $hb, $bp]) {
            $rep_sep = preg_quote($sep, '#');
            $rep     = $bp === '' ? $sep : $bp . $sep;

            // "/test-for-vid/…" → base + "/…" (the trailing slash is a safe boundary).
            $content = (string) preg_replace(
                '#' . $pre . preg_quote($hb, '#') . $rep_sep . '#',
                $rep,
                $content
            );

            // Bare home link ("/test-for-vid" with no further path) → base + "/".
            // Negative lookahead keeps a sibling like "/test-for-vid-2" untouched.
            $content = (string) preg_replace(
                '#' . $pre . preg_quote($hb, '#') . '(?![\w' . $rep_sep . '-])#',
                $rep,
                $content
            );
        }

        return $content;
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
     * @param string $base      Path the destination serves the site from ("" or "/sub").
     * @return string Rewritten content (unchanged if $dest_base has no host).
     */
    public static function to_absolute(string $content, string $dest_base, string $base = ''): string {
        $parts = wp_parse_url($dest_base);
        if (empty($parts['host'])) {
            return $content;
        }

        $scheme    = $parts['scheme'] ?? 'https';
        $authority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $origin    = $scheme . '://' . $authority;
        $base      = Url::normalize_base($base);

        // Sitemap/robots entries are SOURCE URLs (home_url + render path), so
        // this destination's sub-path goes after its origin — directly for a
        // root install, in place of the home sub-path otherwise (below).
        $prefix  = Url::home_base() === '' ? $base : '';
        $search  = [];
        $replace = [];
        foreach (self::source_hosts() as $host) {
            // Same ordering as rewrite(): scheme-qualified before bare "//host".
            $search[] = 'https://' . $host;
            $replace[] = $origin . $prefix;
            $search[] = 'http://' . $host;
            $replace[] = $origin . $prefix;
            $search[] = '//' . $host;
            $replace[] = '//' . $authority . $prefix;
            $search[] = 'https:\/\/' . $host;
            $replace[] = str_replace('/', '\/', $origin . $prefix);
            $search[] = 'http:\/\/' . $host;
            $replace[] = str_replace('/', '\/', $origin . $prefix);
            $search[] = '\/\/' . $host;
            $replace[] = str_replace('/', '\/', '//' . $authority . $prefix);
        }

        $content = str_replace($search, $replace, $content);

        // Swap the source home sub-path for this destination's sub-path
        // ("/test-for-vid/about/" → "/about/" or "/repo/about/"). Anchored to the
        // destination origin, so nothing else is affected.
        $home = Url::home_base();
        if ($home === '') {
            return $content;
        }

        foreach ([$origin, '//' . $authority] as $anchor) {
            $content = str_replace($anchor . $home . '/', $anchor . $base . '/', $content);
            $content = (string) preg_replace(
                '#' . preg_quote($anchor . $home, '#') . '(?![\w/-])#',
                $anchor . $base . '/',
                $content
            );
        }

        return $content;
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
