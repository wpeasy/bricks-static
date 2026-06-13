<?php
/**
 * Builds a dummy sitemap set (index + per-type urlsets) from discovered content.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sitemap;

defined('ABSPATH') || exit;

/**
 * Generates a standards-shaped sitemap set for testing the parse → discover →
 * upload pipeline before real SEO-plugin sitemaps are wired in.
 *
 * A sitemap *index* (`sitemap.xml`) points at one urlset per post type
 * (`sitemap-pages.xml`, `sitemap-posts.xml`). URLs are sourced from our own
 * discovery (published pages/posts) — "populate with our link discovery for now"
 * — and emitted as absolute source-origin URLs; the deploy step rewrites the
 * origin to each destination later.
 */
final class SitemapGenerator {

    /**
     * The sitemaps.org schema namespace.
     */
    public const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * Post types that get their own child sitemap (label => post type).
     *
     * @return array<string,string>
     */
    private static function sections(): array {
        /**
         * Filters the sitemap sections (file label => post type).
         *
         * @param array<string,string> $sections Map of child-sitemap label to post type.
         */
        return (array) apply_filters('bs_sitemap_sections', ['pages' => 'page', 'posts' => 'post']);
    }

    /**
     * Build a sitemap.xml (urlset) + robots.txt from an explicit list of exported
     * page URLs — so the sitemap reflects exactly what was deployed (honouring the
     * discovery mode) rather than every published post. URLs are absolute-to-source;
     * the deploy step rewrites them to each destination's origin.
     *
     * @param array<int,string> $urls Absolute page URLs.
     * @return array<string,string> Relative path => contents.
     */
    public static function from_urls(array $urls): array {
        $seen    = [];
        $entries = [];
        foreach ($urls as $url) {
            $url = (string) $url;
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $entries[]  = ['loc' => $url, 'lastmod' => ''];
        }
        usort($entries, static fn(array $a, array $b): int => strcmp($a['loc'], $b['loc']));

        return [
            'sitemap.xml' => self::urlset($entries),
            'robots.txt'  => self::robots(),
        ];
    }

    /**
     * Generate the dummy sitemap set (every published page/post, split by type).
     * Used by the diagnostic /sitemap/test endpoint; the live export uses
     * from_urls() so the sitemap matches what was actually deployed.
     *
     * @return array<string,string> Relative path (e.g. "sitemap.xml") => XML.
     */
    public static function generate(): array {
        $files     = [];
        $index_locs = [];

        foreach (self::sections() as $label => $type) {
            $entries = self::entries($type);
            if (empty($entries)) {
                continue; // No content of this type — skip the empty child.
            }
            $path          = 'sitemap-' . $label . '.xml';
            $files[$path]  = self::urlset($entries);
            $index_locs[]  = home_url('/' . $path);
        }

        $files['sitemap.xml'] = self::index($index_locs);
        $files['robots.txt']  = self::robots();

        return $files;
    }

    /**
     * A dummy robots.txt whose `Sitemap:` line is the canonical discovery entry
     * point — a crawler reads robots.txt to find the sitemap(s).
     */
    private static function robots(): string {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Sitemap: ' . home_url('/sitemap.xml'),
        ];

        /**
         * Filters the generated robots.txt lines.
         *
         * @param array<int,string> $lines robots.txt lines (no trailing newlines).
         */
        $lines = (array) apply_filters('bs_robots_lines', $lines);

        return implode("\n", $lines) . "\n";
    }

    /**
     * Published permalinks for a post type, newest-modified first.
     *
     * @param string $type Post type.
     * @return array<int,array{loc:string,lastmod:string}>
     */
    private static function entries(string $type): array {
        $ids = get_posts([
            'post_type'        => $type,
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'orderby'          => 'modified',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);

        $out = [];
        foreach ($ids as $id) {
            $loc = get_permalink($id);
            if (!is_string($loc) || $loc === '') {
                continue;
            }
            $mod = get_post_modified_time('c', true, $id);
            $out[] = ['loc' => $loc, 'lastmod' => is_string($mod) ? $mod : ''];
        }

        return $out;
    }

    /**
     * A `<urlset>` document for the given entries.
     *
     * @param array<int,array{loc:string,lastmod:string}> $entries Entries.
     */
    private static function urlset(array $entries): string {
        $rows = '';
        foreach ($entries as $e) {
            $rows .= "  <url>\n    <loc>" . esc_url($e['loc']) . "</loc>\n";
            if ($e['lastmod'] !== '') {
                $rows .= '    <lastmod>' . esc_html($e['lastmod']) . "</lastmod>\n";
            }
            $rows .= "  </url>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<urlset xmlns="' . self::XMLNS . "\">\n"
            . $rows
            . "</urlset>\n";
    }

    /**
     * A `<sitemapindex>` document pointing at the child sitemaps.
     *
     * @param array<int,string> $locs Absolute child-sitemap URLs.
     */
    private static function index(array $locs): string {
        $rows = '';
        foreach ($locs as $loc) {
            $rows .= "  <sitemap>\n    <loc>" . esc_url($loc) . "</loc>\n  </sitemap>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<sitemapindex xmlns="' . self::XMLNS . "\">\n"
            . $rows
            . "</sitemapindex>\n";
    }
}
