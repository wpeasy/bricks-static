<?php
/**
 * Puts the site-search script into exported pages.
 *
 * @package WPEasy\ShipStatic
 * @since   2.2.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Frontend;

use WPEasy\ShipStatic\Render\PageRenderer;
use WPEasy\ShipStatic\Search\IndexBuilder;
use WPEasy\ShipStatic\Search\SearchForm;
use WPEasy\ShipStatic\Support\Assets;

defined('ABSPATH') || exit;

/**
 * Enqueues the site-search script, ONLY into the render (the HTML captured
 * for the static export). The live WordPress site is untouched and keeps its
 * own search. In the export, the script upgrades every search form (any form
 * with an `s` field) to search the destination's search-index.json.
 */
final class Search {

    private const SCRIPT_HANDLE = 'shipstatic-search';

    private const ENTRY = 'src-svelte/search/main.ts';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('script_loader_tag', [self::class, 'script_as_module'], 10, 2);
        add_shortcode('shipstatic_search', [SearchForm::class, 'shortcode']);
        // ShipStatic Search element for Bricks (only when Bricks is the theme).
        add_action('init', [self::class, 'register_bricks_element'], 11);
    }

    /**
     * Register the ShipStatic Search Bricks element.
     */
    public static function register_bricks_element(): void {
        if (class_exists('\Bricks\Elements')) {
            // Name the element panel's category.
            add_filter('bricks/builder/i18n', static function (array $i18n): array {
                $i18n['shipstatic'] = esc_html__('ShipStatic', 'shipstatic');
                return $i18n;
            });
            \Bricks\Elements::register_element(SHIPSTATIC_PLUGIN_DIR . 'src/Bricks/SearchElement.php', 'shipstatic-search', '\WPEasy\ShipStatic\Bricks\SearchElement');
        }
    }

    /**
     * Enqueue the script + its settings during a render request.
     */
    public static function enqueue(): void {
        if (!IndexBuilder::enabled() || !PageRenderer::is_render_request()) {
            return;
        }

        $entry = Assets::entry(self::ENTRY);
        if ($entry === null) {
            return; // Assets not built.
        }

        // Results dropdown look (also styles plain WordPress/Bricks search forms).
        SearchForm::enqueue_style();
        wp_enqueue_script(self::SCRIPT_HANDLE, SHIPSTATIC_PLUGIN_URL . 'assets/dist/' . $entry['file'], [], SHIPSTATIC_VERSION, true);

        // The index URL is an ordinary site URL here, so the export's URL
        // rewriting gives every destination its own path to it. Slashes stay
        // unescaped so it reads like any other link in the page.
        $config = [
            'index' => home_url('/' . IndexBuilder::FILE),
            'i18n'  => [
                /* translators: %s: what the visitor searched for. */
                'noResults' => __('No results for “%s”', 'shipstatic'),
                /* translators: %d: number of search results (2 or more). */
                'results'   => __('%d results', 'shipstatic'),
                'oneResult' => __('1 result', 'shipstatic'),
                'label'     => __('Search results', 'shipstatic'),
            ],
        ];
        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.shipStaticSearch = ' . wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';',
            'before'
        );
    }

    /**
     * Load the script as an ES module (Vite output).
     *
     * @param string $tag    Script tag.
     * @param string $handle Script handle.
     */
    public static function script_as_module(string $tag, string $handle): string {
        if ($handle !== self::SCRIPT_HANDLE) {
            return $tag;
        }

        // Only the external tag, not the inline settings block before it.
        return (string) preg_replace('#<script (?=[^>]*\bsrc=)#', '<script type="module" ', $tag, 1);
    }
}
