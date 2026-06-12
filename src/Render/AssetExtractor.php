<?php
/**
 * Extracts internal links and same-origin assets from rendered output.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

use WPEasy\BricksStatic\Support\Url;

defined('ABSPATH') || exit;

/**
 * Regex-based discovery of links (for crawling) and assets (to mirror).
 *
 * Covers href/src/srcset, <link href>, and CSS url()/@import (inline and in CSS
 * files). JS-constructed URLs are not statically detectable — that gap is
 * reported, not silently hidden.
 */
final class AssetExtractor {

    /**
     * Internal page links from an HTML document (absolute URLs, deduped).
     *
     * @param string $html HTML.
     * @param string $base Absolute URL of the document.
     * @return array<int,string>
     */
    public static function extract_links(string $html, string $base): array {
        $raw = self::match($html, '#<a\b[^>]*?\shref\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i');

        return self::resolve($raw, $base);
    }

    /**
     * Same-origin asset URLs referenced by an HTML document (absolute, deduped).
     *
     * @param string $html HTML.
     * @param string $base Absolute URL of the document.
     * @return array<int,string>
     */
    public static function extract_assets(string $html, string $base): array {
        $raw = array_merge(
            // src, data-src, data-lazy-src, lazy-src (covers <img>/<script>/<source>/<iframe>).
            self::match($html, '#\s(?:data-(?:lazy-)?|lazy-)?src\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i'),
            // Common single-URL lazy attributes and video posters.
            self::match($html, '#\s(?:data-original|data-lazy|data-poster|poster)\s*=\s*("[^"]*"|\'[^\']*\')#i'),
            self::link_assets($html),
            // srcset, data-srcset, data-lazy-srcset, lazy-srcset, data-original-set.
            self::srcset($html, '#\s(?:data-(?:lazy-)?|lazy-)?srcset\s*=\s*("[^"]*"|\'[^\']*\')#i'),
            self::srcset($html, '#\sdata-original-set\s*=\s*("[^"]*"|\'[^\']*\')#i'),
            self::background_refs($html),
            self::css_refs($html)
        );

        return self::resolve($raw, $base);
    }

    /**
     * href values of <link> tags whose rel marks them as assets (stylesheets,
     * icons, preloads, manifest). Excludes canonical/alternate/shortlink/etc.,
     * which point at pages or dynamic endpoints — not files to mirror.
     *
     * @param string $html HTML.
     * @return array<int,string>
     */
    private static function link_assets(string $html): array {
        static $asset_rels = [
            'stylesheet', 'icon', 'shortcut', 'apple-touch-icon',
            'apple-touch-icon-precomposed', 'mask-icon', 'preload', 'prefetch', 'manifest',
        ];

        $out = [];

        if (!preg_match_all('#<link\b[^>]*>#i', $html, $tags)) {
            return $out;
        }

        foreach ($tags[0] as $tag) {
            if (!preg_match('#\srel\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', $tag, $rel_m)) {
                continue;
            }

            $rel    = strtolower(trim(trim($rel_m[1]), "\"'"));
            $tokens = preg_split('/\s+/', $rel) ?: [];

            if (!array_intersect($tokens, $asset_rels)) {
                continue;
            }

            if (preg_match('#\shref\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', $tag, $href_m)) {
                $out[] = trim(trim($href_m[1]), "\"'");
            }
        }

        return $out;
    }

    /**
     * Same-origin asset URLs referenced inside a CSS file (absolute, deduped).
     *
     * @param string $css  CSS source.
     * @param string $base Absolute URL of the CSS file (for relative url()).
     * @return array<int,string>
     */
    public static function extract_css_assets(string $css, string $base): array {
        return self::resolve(self::css_refs($css), $base);
    }

    /**
     * url(...) and @import targets in CSS or inline styles.
     *
     * @param string $content CSS or HTML.
     * @return array<int,string>
     */
    private static function css_refs(string $content): array {
        return array_merge(
            self::match($content, '#url\(\s*("[^"]*"|\'[^\']*\'|[^)\s]+)\s*\)#i'),
            self::match($content, '#@import\s+("[^"]*"|\'[^\']*\')#i')
        );
    }

    /**
     * Parse srcset-style attributes (matched by $regex) into candidate URLs.
     *
     * @param string $html  HTML.
     * @param string $regex Regex whose group 1 captures the attribute value.
     * @return array<int,string>
     */
    private static function srcset(string $html, string $regex): array {
        $out = [];

        foreach (self::match($html, $regex) as $value) {
            foreach (explode(',', $value) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }
                // "url 2x" / "url 800w" → take the URL part.
                $out[] = preg_split('/\s+/', $candidate)[0] ?? '';
            }
        }

        return $out;
    }

    /**
     * URLs from lazy-load background attributes (data-bg / data-background…),
     * whose value is either a plain URL or a CSS url(...) expression.
     *
     * @param string $html HTML.
     * @return array<int,string>
     */
    private static function background_refs(string $html): array {
        $out = [];

        foreach (self::match($html, '#\sdata-(?:bg|background|background-image|bg-image)\s*=\s*("[^"]*"|\'[^\']*\')#i') as $value) {
            if (stripos($value, 'url(') !== false) {
                foreach (self::match($value, '#url\(\s*("[^"]*"|\'[^\']*\'|[^)\s]+)\s*\)#i') as $inner) {
                    $out[] = $inner;
                }
            } else {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * Run a capture-group regex and return cleaned (unquoted) values.
     *
     * @param string $content Content to search.
     * @param string $regex   Regex whose group 1 holds the (possibly quoted) value.
     * @return array<int,string>
     */
    private static function match(string $content, string $regex): array {
        $out = [];

        if (preg_match_all($regex, $content, $matches)) {
            foreach ($matches[1] as $raw) {
                $raw = trim(trim($raw), "\"'");
                if ($raw !== '') {
                    $out[] = $raw;
                }
            }
        }

        return $out;
    }

    /**
     * Absolutise, filter to internal, and dedupe a list of raw references.
     *
     * @param array<int,string> $raw  Raw URLs/paths.
     * @param string            $base Document base URL.
     * @return array<int,string>
     */
    private static function resolve(array $raw, string $base): array {
        $out = [];

        foreach ($raw as $ref) {
            if (!Url::is_internal($ref)) {
                continue;
            }
            $abs = Url::absolutize($ref, $base);
            if ($abs !== '') {
                $out[$abs] = true;
            }
        }

        return array_keys($out);
    }
}
