<?php
/**
 * Parses robots.txt + sitemaps (index-aware) into a flat list of page URLs.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sitemap;

defined('ABSPATH') || exit;

/**
 * Reads sitemap XML (and robots.txt) without a DOM dependency. A sitemap may be
 * a `<sitemapindex>` (points at child sitemaps) or a `<urlset>` (the page URLs);
 * `collect()` expands an index recursively into the flat page-URL list.
 *
 * `<loc>` extraction is intentionally regex-based: real-world sitemaps carry
 * varied namespaces/prefixes that make SimpleXML brittle, and we only need the
 * locations.
 */
final class SitemapParser {

    /**
     * Max index nesting to follow (guards against a cyclic/hostile index).
     */
    private const MAX_DEPTH = 10;

    /**
     * Whether a document is a sitemap index (vs a urlset).
     *
     * @param string $xml Sitemap XML.
     */
    public static function is_index(string $xml): bool {
        return stripos($xml, '<sitemapindex') !== false;
    }

    /**
     * All `<loc>` values in a document (works for both index and urlset).
     *
     * @param string $xml Sitemap XML.
     * @return array<int,string>
     */
    public static function locs(string $xml): array {
        if (!preg_match_all('#<loc\b[^>]*>\s*(.*?)\s*</loc>#is', $xml, $m)) {
            return [];
        }

        $out = [];
        foreach ($m[1] as $loc) {
            $loc = html_entity_decode(trim($loc), ENT_QUOTES | ENT_XML1);
            if ($loc !== '') {
                $out[] = $loc;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * `Sitemap:` URLs declared in a robots.txt body.
     *
     * @param string $robots robots.txt contents.
     * @return array<int,string>
     */
    public static function sitemaps_from_robots(string $robots): array {
        if (!preg_match_all('#^\s*Sitemap\s*:\s*(\S+)#im', $robots, $m)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', $m[1]))));
    }

    /**
     * Expand an entry sitemap into its flat list of page URLs, recursing through
     * index documents. `$fetch` resolves a child sitemap URL to its XML (return
     * null if it can't be fetched — that branch is skipped).
     *
     * @param string                   $xml   Entry sitemap XML.
     * @param callable(string):?string $fetch Resolver: loc => XML|null.
     * @param int                      $depth Internal recursion guard.
     * @return array<int,string>
     */
    public static function collect(string $xml, callable $fetch, int $depth = 0): array {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        if (!self::is_index($xml)) {
            return self::locs($xml);
        }

        $urls = [];
        foreach (self::locs($xml) as $child_loc) {
            $child_xml = $fetch($child_loc);
            if (is_string($child_xml) && $child_xml !== '') {
                $urls = array_merge($urls, self::collect($child_xml, $fetch, $depth + 1));
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Test helper: parse an in-memory generated set (path => contents), starting
     * from robots.txt if present (else sitemap.xml), resolving every child
     * sitemap by its path basename. Returns the flat list of discovered page URLs.
     *
     * @param array<string,string> $files Generated set (e.g. SitemapGenerator::generate()).
     * @return array<int,string>
     */
    public static function collect_set(array $files): array {
        $by_basename = [];
        foreach ($files as $path => $contents) {
            $by_basename[basename($path)] = $contents;
        }

        $resolve = static function (string $loc) use ($by_basename): ?string {
            $name = basename((string) wp_parse_url($loc, PHP_URL_PATH));
            return $by_basename[$name] ?? null;
        };

        // Prefer the robots.txt entry point; fall back to sitemap.xml.
        $entries = [];
        if (isset($by_basename['robots.txt'])) {
            $entries = self::sitemaps_from_robots($by_basename['robots.txt']);
        }
        if (empty($entries) && isset($by_basename['sitemap.xml'])) {
            $entries = [home_url('/sitemap.xml')];
        }

        $urls = [];
        foreach ($entries as $entry_loc) {
            $xml = $resolve($entry_loc);
            if (is_string($xml) && $xml !== '') {
                $urls = array_merge($urls, self::collect($xml, $resolve));
            }
        }

        return array_values(array_unique($urls));
    }
}
