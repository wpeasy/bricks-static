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
 * Provides the crawl's starting point: the home page. Every other URL is reached
 * by following internal links from each rendered page (see AssetExtractor).
 *
 * This is deliberately link-driven, not an enumeration of all content: a page
 * that nothing links to isn't reachable by a visitor either, so it doesn't
 * belong in the static copy. It also keeps builder-only URLs (e.g. Bricks
 * `/template/...` previews) out of the export, since nothing public links to them.
 */
final class UrlCollector {

    /**
     * Collect seed URLs.
     *
     * @return array<int,string> Absolute URLs (deduped).
     */
    public static function collect(): array {
        $urls = [home_url('/')];

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
}
