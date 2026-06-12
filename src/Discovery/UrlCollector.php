<?php
/**
 * Seeds the crawl.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Discovery;

defined('ABSPATH') || exit;

/**
 * Provides the crawl's starting points. Two modes:
 *
 *  - `linked` (default): seed only the home page. Every other URL is reached by
 *    following internal links — a page nothing links to isn't reachable by a
 *    visitor either, so it stays out of the static copy. This also keeps
 *    builder-only URLs (e.g. Bricks `/template/...` previews) out by construction.
 *  - `all`: additionally enumerate every published public post and taxonomy term,
 *    so even unlinked/orphaned content is captured. Builder/preview post types
 *    (see excluded_post_types()) are still skipped.
 */
final class UrlCollector {

    /**
     * Option storing the discovery mode (`linked` | `all`).
     */
    public const MODE_OPTION = 'bs_discovery_mode';

    /**
     * Current discovery mode, defaulting to link-driven crawling.
     */
    public static function mode(): string {
        return get_option(self::MODE_OPTION) === 'all' ? 'all' : 'linked';
    }

    /**
     * Persist the discovery mode.
     *
     * @param string $mode `linked` | `all`.
     */
    public static function set_mode(string $mode): void {
        update_option(self::MODE_OPTION, $mode === 'all' ? 'all' : 'linked', false);
    }

    /**
     * Collect seed URLs.
     *
     * @return array<int,string> Absolute URLs (deduped).
     */
    public static function collect(): array {
        $urls = [home_url('/')];

        if (self::mode() === 'all') {
            $urls = array_merge($urls, self::post_urls(), self::term_urls());
        }

        /**
         * Filters the seed URLs before crawling. Use this to add entry points
         * that aren't linked from the home page (the crawler follows links from
         * each seed onward).
         *
         * @param array<int,string> $urls Seed URLs.
         */
        $urls = (array) apply_filters('bs_seed_urls', $urls);

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Post types that are registered `public` (so they'd be enumerated) but are
     * not real site content — builder previews, design templates, etc. Bricks
     * registers `bricks_template` as public with a `/template/...` rewrite; those
     * URLs 301-redirect and must never end up in the static export.
     *
     * @return array<int,string>
     */
    private static function excluded_post_types(): array {
        /**
         * Filters post types excluded from the crawl seed.
         *
         * @param array<int,string> $types Post type names to skip.
         */
        return (array) apply_filters('bs_excluded_post_types', ['attachment', 'bricks_template']);
    }

    /**
     * Permalinks of all published public posts (public types bar builder/preview
     * types — see excluded_post_types()).
     *
     * @return array<int,string>
     */
    private static function post_urls(): array {
        $types = get_post_types(['public' => true], 'names');
        foreach (self::excluded_post_types() as $skip) {
            unset($types[$skip]);
        }

        if (empty($types)) {
            return [];
        }

        $ids = get_posts([
            'post_type'        => array_values($types),
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        $urls = [];
        foreach ($ids as $id) {
            $link = get_permalink($id);
            if (is_string($link) && $link !== '') {
                $urls[] = $link;
            }
        }

        return $urls;
    }

    /**
     * Term archive links for all public taxonomies (non-empty terms).
     *
     * @return array<int,string>
     */
    private static function term_urls(): array {
        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (empty($taxonomies)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => array_values($taxonomies),
            'hide_empty' => true,
            'number'     => 0,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $urls = [];
        foreach ($terms as $term) {
            $link = get_term_link($term);
            if (is_string($link) && $link !== '') {
                $urls[] = $link;
            }
        }

        return $urls;
    }
}
