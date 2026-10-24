<?php
/**
 * Builds a destination's site-search index.
 *
 * @package WPEasy\ShipStatic
 * @since   2.2.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Search;

defined('ABSPATH') || exit;

/**
 * The site-search index (`search-index.json`) for one destination.
 *
 * Built at deploy, not at render: Runner::build_deploy_manifest() hands over
 * each page AFTER that destination's replacements and sub-path are applied,
 * so every destination's index has its own wording and its own URLs. The
 * browser side is src-svelte/search/main.ts (see docs/SEARCH_PLAN.md).
 */
final class IndexBuilder {

    /** Bump when the index shape changes (folded into the sync signature). */
    public const VERSION = 1;

    /** Index file, at the site root (inside a destination's sub-path folder). */
    public const FILE = 'search-index.json';

    /** '1' / '0' (same convention as shipstatic_fab_enabled). */
    public const OPTION = 'shipstatic_search_enabled';

    /**
     * Per-request memo: md5(page HTML) => record, so a Check followed by a
     * Sync (or several destinations with identical pages) parses each page once.
     *
     * @var array<string,array<string,mixed>|null>
     */
    private static array $memo = [];

    /**
     * Whether site search is turned on.
     */
    public static function enabled(): bool {
        return get_option(self::OPTION, '0') === '1';
    }

    /**
     * Turn site search on or off.
     *
     * @param bool $on New state.
     */
    public static function set_enabled(bool $on): void {
        update_option(self::OPTION, $on ? '1' : '0', false);
    }

    /**
     * One page's index record, or null when it isn't searchable.
     *
     * @param string $html     Final page HTML at this destination.
     * @param string $relative Render path, e.g. "about/index.html".
     * @param string $served   Destination's served sub-path ("" or "/sub").
     * @return array{u:string,t:string,h:array<int,string>,x:string}|null
     */
    public static function record(string $html, string $relative, string $served): ?array {
        $relative = ltrim($relative, '/');
        if (strtolower($relative) === '404.html') {
            return null;
        }

        /**
         * Filters whether an exported page goes into the site-search index.
         *
         * @param bool   $include  Default true.
         * @param string $relative Render path, e.g. "about/index.html".
         */
        if (!apply_filters('shipstatic_search_include_page', true, $relative)) {
            return null;
        }

        $hash = md5($html);
        if (!array_key_exists($hash, self::$memo)) {
            self::$memo[$hash] = TextExtractor::extract($html);
        }
        $parts = self::$memo[$hash];
        if ($parts === null || ($parts['t'] === '' && $parts['x'] === '')) {
            return null;
        }

        return ['u' => self::url_for($relative, $served)] + $parts;
    }

    /**
     * The index file's contents. No timestamp: identical content must give an
     * identical file, so an unchanged index is never re-uploaded.
     *
     * @param array<int,array<string,mixed>> $records From record().
     */
    public static function encode(array $records): string {
        usort($records, static fn(array $a, array $b): int => strcmp((string) $a['u'], (string) $b['u']));

        return (string) wp_json_encode(
            ['v' => self::VERSION, 'pages' => array_values($records)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * A page's URL path at the destination: "about/index.html" → "/sub/about/".
     *
     * @param string $relative Render path.
     * @param string $served   "" or "/sub".
     */
    private static function url_for(string $relative, string $served): string {
        $path = (string) preg_replace('#(^|/)index\.html$#i', '$1', $relative);

        return $served . '/' . $path;
    }
}
