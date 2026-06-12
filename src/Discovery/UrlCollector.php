<?php
/**
 * Seeds the crawl from published WordPress content.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Discovery;

defined('ABSPATH') || exit;

/**
 * Enumerates the site's published, public URLs: the home page, every public
 * post type, and every public taxonomy term. The crawler then augments this by
 * following internal links found on each rendered page.
 */
final class UrlCollector {

    /**
     * Collect seed URLs.
     *
     * @return array<int,string> Absolute URLs (deduped).
     */
    public static function collect(): array {
        $urls = [home_url('/')];

        $urls = array_merge($urls, self::post_urls());
        $urls = array_merge($urls, self::term_urls());

        /**
         * Filters the seed URLs before crawling.
         *
         * @param array<int,string> $urls Seed URLs.
         */
        $urls = (array) apply_filters('bs_seed_urls', $urls);

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Post types that are registered `public` (so they'd be seeded) but are not
     * real site content — builder previews, design templates, etc. Bricks
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
